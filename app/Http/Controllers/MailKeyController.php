<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailPgpKey;
use App\Support\Mail\PgpDecryptor;
use App\Support\Mail\SmimeDecryptor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner-scoped CRUD for the user's PGP / S-MIME decryption keys, used only
 * server-side to read encrypted archived mail. IMPORT only (PGP armored secret
 * key; S/MIME PKCS#12 → PEM). The private key + passphrase are write-only: they
 * are stored `encrypted` + `$hidden` and NEVER returned by any action here
 * (present() emits public material only). A foreign / unknown id is a 404.
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
        $request->validate([
            'type' => ['required', Rule::in(MailPgpKey::TYPES)],
            'label' => ['required', 'string', 'max:200'],
            'passphrase' => ['nullable', 'string', 'max:2000'],
            // PGP: armored secret key. S/MIME: base64-encoded PKCS#12 bundle.
            'armored_private_key' => ['required_if:type,pgp', 'nullable', 'string'],
            'p12_base64' => ['required_if:type,smime', 'nullable', 'string'],
        ]);

        $type = $request->string('type')->value();
        $passphrase = $request->filled('passphrase') ? $request->string('passphrase')->value() : null;

        $key = new MailPgpKey;
        $key->fill([]); // AssignsOwner stamps user_id on save.

        if ($type === 'pgp') {
            $armored = $request->string('armored_private_key')->value();
            $info = $pgp->importInfo($armored);
            $key->forceFill([
                'type' => 'pgp',
                'label' => $request->string('label')->value(),
                'private_key' => $armored,
                'passphrase' => $passphrase,
                'key_fingerprint' => $info['fingerprint'] ?? null,
                'key_id' => $info['key_id'] ?? null,
                'public_key' => $info['public_key'] ?? null,
            ]);
        } else {
            $p12 = base64_decode($request->string('p12_base64')->value(), true);
            if ($p12 === false) {
                return response()->json(['ok' => false, 'detail' => 'invalid_p12'], 422);
            }
            $pem = $smime->pkcs12ToPem($p12, $passphrase);
            if ($pem === null) {
                return response()->json(['ok' => false, 'detail' => 'p12_decode_failed'], 422);
            }
            $key->forceFill([
                'type' => 'smime',
                'label' => $request->string('label')->value(),
                'private_key' => $pem['key'],
                'cert_pem' => $pem['cert'],
                // The PKCS#12 passphrase protected the bundle only; the extracted
                // PEM key is unencrypted (stored under the app's encrypted cast).
                'passphrase' => null,
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
            'expires_at' => $key->expires_at?->toIso8601String(),
            'created_at' => $key->created_at?->toIso8601String(),
        ];
    }
}
