<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FileEntry;
use App\Models\MailPgpKey;
use App\Support\BlobStore;
use App\Support\Mail\PgpDecryptor;
use App\Support\Mail\PgpKeyGenerator;
use App\Support\Mail\SmimeDecryptor;
use App\Support\Mail\SmimeKeyGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner-scoped CRUD for the user's PGP / S-MIME decryption keys, used only
 * server-side to read encrypted archived mail. Keys can be IMPORTED (armored PGP
 * secret key or S/MIME PKCS#12 — from a computer upload OR from a file already
 * stored in the Files module) or GENERATED server-side (PGP via gpg, S/MIME
 * self-signed via openssl). The private key + passphrase are write-only: stored
 * `encrypted` + `$hidden`, NEVER returned by any action here (present() emits
 * public material only). A foreign / unknown id is a 404.
 */
class MailKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keys = MailPgpKey::query()
            ->ownedBy($this->requireUser($request)->id)
            ->orderBy('type')
            ->orderBy('label')
            ->get();

        return response()->json(['keys' => $keys->map(fn (MailPgpKey $k): array => $this->present($k))->all()]);
    }

    public function store(Request $request, PgpDecryptor $pgp, SmimeDecryptor $smime): JsonResponse
    {
        if ($fail = $this->guard($request, [
            'type' => ['required', Rule::in(MailPgpKey::TYPES)],
            'label' => ['required', 'string', 'max:200'],
            'passphrase' => ['nullable', 'string', 'max:2000'],
            // Where the key material comes from: an inline upload (default) or a
            // file already stored in the Files module.
            'source' => ['nullable', Rule::in(['upload', 'files'])],
            'file_id' => ['required_if:source,files', 'nullable', 'integer'],
            // PGP: armored secret key. S/MIME: base64-encoded PKCS#12 bundle.
            // Only required for the inline-upload source.
            'armored_private_key' => ['nullable', 'string'],
            'p12_base64' => ['nullable', 'string'],
        ])) {
            return $fail;
        }

        $this->requireUser($request); // fail-closed 401
        $type = $request->string('type')->value();
        $passphrase = $request->filled('passphrase') ? $request->string('passphrase')->value() : null;
        $source = $request->filled('source') ? $request->string('source')->value() : 'upload';

        // Resolve the raw key material for this source: PGP → armored string,
        // S/MIME → raw PKCS#12 bytes.
        $fileBytes = null;
        if ($source === 'files') {
            // FileEntry is owner-scoped (global scope) — a foreign / unknown id
            // resolves to null → 404. Bytes live plaintext on the files disk.
            $file = FileEntry::query()->whereKey($request->integer('file_id'))->first();
            abort_if($file === null, 404);
            if (! BlobStore::disk()->exists($file->storage_path)) {
                abort(404);
            }
            $fileBytes = BlobStore::disk()->get($file->storage_path);
        }

        $key = new MailPgpKey;
        $key->fill([]); // AssignsOwner stamps user_id on save.

        if ($type === 'pgp') {
            $armored = $source === 'files'
                ? (string) $fileBytes
                : $request->string('armored_private_key')->value();
            if (trim($armored) === '') {
                return response()->json(['ok' => false, 'detail' => 'missing_key_material'], 422);
            }
            $info = $pgp->importInfo($armored);
            $createdAt = is_int($info['created_at'] ?? null) ? $info['created_at'] : null;
            $expiresAt = is_int($info['expires_at'] ?? null) ? $info['expires_at'] : null;
            $key->forceFill([
                'type' => 'pgp',
                'label' => $request->string('label')->value(),
                'private_key' => $armored,
                'passphrase' => $passphrase,
                'key_fingerprint' => $info['fingerprint'] ?? null,
                'key_id' => $info['key_id'] ?? null,
                'public_key' => $info['public_key'] ?? null,
                'algorithm' => $info['algorithm'] ?? null,
                'key_length' => $info['key_length'] ?? null,
                'curve' => $info['curve'] ?? null,
                'identities_json' => $info !== null ? ($info['identities'] ?? []) : null,
                'valid_from' => $createdAt !== null ? Carbon::createFromTimestamp($createdAt) : null,
                'expires_at' => $expiresAt !== null ? Carbon::createFromTimestamp($expiresAt) : null,
            ]);
        } else {
            if ($source === 'files') {
                $p12 = (string) $fileBytes;
            } else {
                $decoded = base64_decode($request->string('p12_base64')->value(), true);
                if ($decoded === false) {
                    return response()->json(['ok' => false, 'detail' => 'invalid_p12'], 422);
                }
                $p12 = $decoded;
            }
            if ($p12 === '') {
                return response()->json(['ok' => false, 'detail' => 'missing_key_material'], 422);
            }
            $pem = $smime->pkcs12ToPem($p12, $passphrase);
            if ($pem === null) {
                return response()->json(['ok' => false, 'detail' => 'p12_decode_failed'], 422);
            }
            $cert = $smime->certInfo($pem['cert']);
            $notBefore = is_int($cert['not_before'] ?? null) ? $cert['not_before'] : null;
            $notAfter = is_int($cert['not_after'] ?? null) ? $cert['not_after'] : null;
            $identity = $cert !== null && ($cert['email'] ?? null) !== null
                ? [['name' => $cert['name'] ?? null, 'email' => $cert['email']]]
                : [];
            $key->forceFill([
                'type' => 'smime',
                'label' => $request->string('label')->value(),
                'private_key' => $pem['key'],
                'cert_pem' => $pem['cert'],
                // The PKCS#12 passphrase protected the bundle only; the extracted
                // PEM key is unencrypted (stored under the app's encrypted cast).
                'passphrase' => null,
                'key_fingerprint' => $cert['sha256_fingerprint'] ?? null,
                'algorithm' => $cert['algorithm'] ?? null,
                'key_length' => $cert['key_length'] ?? null,
                'curve' => $cert['curve'] ?? null,
                'issuer' => $cert['issuer'] ?? null,
                'serial' => $cert['serial'] ?? null,
                'identities_json' => $identity,
                'valid_from' => $notBefore !== null ? Carbon::createFromTimestamp($notBefore) : null,
                'expires_at' => $notAfter !== null ? Carbon::createFromTimestamp($notAfter) : null,
            ]);
        }

        $key->save();

        return response()->json(['key' => $this->present($key)], 201);
    }

    /**
     * Generate a fresh PGP keypair (gpg) or a self-signed S/MIME cert+key
     * (openssl) server-side and persist it. The private key is never returned.
     */
    public function generate(Request $request, PgpKeyGenerator $pgpGen, SmimeKeyGenerator $smimeGen, SmimeDecryptor $smime): JsonResponse
    {
        if ($fail = $this->guard($request, [
            'type' => ['required', Rule::in(MailPgpKey::TYPES)],
            'label' => ['required', 'string', 'max:200'],
            'passphrase' => ['nullable', 'string', 'max:2000'],
            'expire_years' => ['nullable', 'integer', 'min:1', 'max:100'],
            'identities' => ['required', 'array', 'min:1', 'max:20'],
            'identities.*.name' => ['nullable', 'string', 'max:200'],
            'identities.*.email' => ['required', 'email', 'max:254'],
            'identities.*.comment' => ['nullable', 'string', 'max:200'],
            // PGP-only.
            'algorithm' => ['nullable', Rule::in(['rsa', 'ecc'])],
            'key_length' => ['nullable', 'integer', Rule::in(PgpKeyGenerator::RSA_LENGTHS)],
            'curve' => ['nullable', Rule::in(PgpKeyGenerator::CURVES)],
            'signing_subkey' => ['nullable', 'boolean'],
        ])) {
            return $fail;
        }

        $type = $request->string('type')->value();
        $passphrase = $request->filled('passphrase') ? $request->string('passphrase')->value() : null;
        $expireYears = $request->filled('expire_years') ? $request->integer('expire_years') : null;

        /** @var list<array{name?:?string, email:string, comment?:?string}> $identities */
        $identities = $this->identities($request);
        if ($identities === []) {
            return response()->json(['ok' => false, 'detail' => 'no_identities'], 422);
        }

        $key = new MailPgpKey;
        $key->fill([]); // AssignsOwner stamps user_id on save.

        if ($type === 'pgp') {
            if (! $pgpGen->available()) {
                return response()->json(['ok' => false, 'detail' => 'toolchain_unavailable'], 501);
            }

            $algorithm = $request->filled('algorithm') ? $request->string('algorithm')->value() : 'ecc';
            $keyLength = $request->filled('key_length') ? $request->integer('key_length') : 3072;
            $curve = $request->filled('curve') ? $request->string('curve')->value() : 'ed25519';

            $result = $pgpGen->generate([
                'algorithm' => $algorithm,
                'key_length' => $keyLength,
                'curve' => $curve,
                'identities' => $identities,
                'expire' => $expireYears !== null ? $expireYears.'y' : '0',
                'passphrase' => $passphrase,
                'signing_subkey' => $request->boolean('signing_subkey'),
            ]);
            if ($result === null) {
                return response()->json(['ok' => false, 'detail' => 'generation_failed'], 422);
            }

            $key->forceFill([
                'type' => 'pgp',
                'label' => $request->string('label')->value(),
                'private_key' => $result['private_key'],
                'passphrase' => $passphrase,
                'public_key' => $result['public_key'],
                'key_fingerprint' => $result['fingerprint'],
                'key_id' => $result['key_id'],
                'identities_json' => $identities,
                'algorithm' => $algorithm === 'rsa' ? 'RSA' : ($curve === 'ed25519' ? 'EdDSA' : 'ECDSA'),
                'key_length' => $algorithm === 'rsa' ? $keyLength : null,
                'curve' => $algorithm === 'rsa' ? null : $curve,
                'valid_from' => Carbon::now(),
                'expires_at' => $expireYears !== null ? Carbon::now()->addYears($expireYears) : null,
            ]);
        } else {
            if (! $smimeGen->available()) {
                return response()->json(['ok' => false, 'detail' => 'toolchain_unavailable'], 501);
            }

            $primary = $identities[0];
            $days = $expireYears !== null ? $expireYears * 365 : 730;
            $keyLength = $request->filled('key_length') ? $request->integer('key_length') : 3072;
            $result = $smimeGen->generate([
                'name' => $primary['name'] ?? null,
                'email' => $primary['email'],
                'key_length' => $keyLength,
                'days' => $days,
                'passphrase' => $passphrase,
            ]);
            if ($result === null) {
                return response()->json(['ok' => false, 'detail' => 'generation_failed'], 422);
            }

            // Self-signed → the same parser that reads issuer/serial for an
            // imported cert applies unchanged here (issuer == subject).
            $cert = $smime->certInfo($result['cert']);

            $key->forceFill([
                'type' => 'smime',
                'label' => $request->string('label')->value(),
                'private_key' => $result['key'],
                'cert_pem' => $result['cert'],
                // Passphrase stored only when the generated key PEM is encrypted
                // with it (so decryption can unlock it); null otherwise.
                'passphrase' => $result['protected'] ? $passphrase : null,
                'identities_json' => [['name' => $primary['name'] ?? null, 'email' => $primary['email']]],
                'algorithm' => 'RSA',
                'key_length' => $keyLength,
                'issuer' => $cert['issuer'] ?? null,
                'serial' => $cert['serial'] ?? null,
                'valid_from' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays($days),
            ]);
        }

        $key->save();

        return response()->json(['key' => $this->present($key)], 201);
    }

    public function destroy(Request $request, MailPgpKey $key): JsonResponse
    {
        abort_if((int) $key->user_id !== (int) $this->requireUser($request)->id, 404);
        $key->delete();

        return response()->json([], 204);
    }

    /**
     * Export an OWN key's private material — the one deliberate, explicit
     * exception to "the private key is never returned by any action here"
     * (see the class docblock): a client-side sync (e.g. mirroring the
     * account's keys into a local GnuPG keyring) needs the actual secret key,
     * not just its public half. Gated behind a fresh current_password check
     * (the same step-up TwoFactorController::requireCurrentPassword uses for
     * disabling 2FA / reading recovery codes — a stolen bearer token alone
     * must not be enough) and audit-logged, so an export is always
     * attributable and visible to the account owner afterwards. Never returns
     * the stored passphrase — the caller already knows it (they set it, or
     * the key has none); this hands out only what is needed to use the key.
     */
    public function export(Request $request, MailPgpKey $key): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_if((int) $key->user_id !== (int) $user->id, 404);
        $this->requireCurrentPassword($request, (string) $user->password);

        AuditLog::record('crypto.key.exported', $key, ['type' => $key->type]);

        if ($key->type === 'smime') {
            return response()->json(['private_key' => $key->private_key, 'cert_pem' => $key->cert_pem]);
        }

        return response()->json(['private_key' => $key->private_key]);
    }

    private function requireCurrentPassword(Request $request, string $currentHash): void
    {
        $pw = $request->string('current_password')->value();
        if ($pw === '' || ! Hash::check($pw, $currentHash)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }
    }

    /**
     * Validate and, on failure, return a JSON 422 (never a thrown
     * ValidationException — which on the web/session route renders as a 302
     * redirect instead of the JSON contract the app + mobile expect). Returns
     * null when validation passes.
     *
     * @param  array<string, array<int, mixed>>  $rules
     */
    private function guard(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'validation',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return null;
    }

    /**
     * Normalise the validated identities into the generator's shape.
     *
     * @return list<array{name?:?string, email:string, comment?:?string}>
     */
    private function identities(Request $request): array
    {
        $raw = $request->input('identities');
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['email'] ?? null) || $entry['email'] === '') {
                continue;
            }
            $out[] = [
                'name' => is_string($entry['name'] ?? null) && $entry['name'] !== '' ? $entry['name'] : null,
                'email' => $entry['email'],
                'comment' => is_string($entry['comment'] ?? null) && $entry['comment'] !== '' ? $entry['comment'] : null,
            ];
        }

        return $out;
    }

    /**
     * Public material only — private_key / passphrase are NEVER emitted.
     *
     * @return array<string, mixed>
     */
    private function present(MailPgpKey $key): array
    {
        return [
            'id' => $key->id,
            'type' => $key->type,
            'label' => $key->label,
            'key_fingerprint' => $key->key_fingerprint,
            'key_id' => $key->key_id,
            'public_key' => $key->public_key,
            'identities' => $key->identities_json,
            'has_cert' => $key->cert_pem !== null,
            // The certificate is the CERT's public half — non-secret, same class
            // as public_key above (never the private_key/passphrase, which stay
            // #[Hidden] on the model and are never touched here).
            'cert_pem' => $key->cert_pem,
            'algorithm' => $key->algorithm,
            'key_length' => $key->key_length,
            'curve' => $key->curve,
            'issuer' => $key->issuer,
            'serial' => $key->serial,
            'valid_from' => $key->valid_from?->toIso8601String(),
            'expires_at' => $key->expires_at?->toIso8601String(),
            'created_at' => $key->created_at?->toIso8601String(),
        ];
    }
}
