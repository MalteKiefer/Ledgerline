<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CryptoRecipient;
use App\Models\MailPgpKey;
use App\Support\Crypto\FileCipher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shared encryption keyring (profile-level, not mail-gated): the user's OWN keys
 * (from mail_pgp_keys — private material stays encrypted + hidden) plus saved
 * RECIPIENTS (other people's public keys/certs). Files encryption reads this to
 * offer "encrypt to …"; own-key management (generate/import/delete) is the mail
 * key controller mounted under /crypto/keys.
 */
class CryptoController extends Controller
{
    public function __construct(private FileCipher $cipher) {}

    /** Own keys (public info) + saved recipients — the "encrypt to" picker. */
    public function keyring(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        $keys = MailPgpKey::query()->where('user_id', $uid)->orderBy('label')->get()
            ->map(fn (MailPgpKey $k): array => [
                'id' => $k->id,
                'type' => $k->type,
                'label' => $k->label,
                'fingerprint' => $k->key_fingerprint,
                'has_private' => $k->private_key !== null && $k->private_key !== '',
                'is_own' => true,
            ])->all();

        $recipients = CryptoRecipient::query()->where('user_id', $uid)->orderBy('label')->get()
            ->map(fn (CryptoRecipient $r): array => [
                'id' => $r->id,
                'type' => $r->type,
                'label' => $r->label,
                'fingerprint' => $r->fingerprint,
            ])->all();

        return response()->json(['keys' => $keys, 'recipients' => $recipients]);
    }

    /** Import a recipient: an armored PGP public key or an S/MIME certificate (PEM). */
    public function storeRecipient(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'type' => ['required', Rule::in(CryptoRecipient::TYPES)],
            'label' => ['required', 'string', 'max:200'],
            'material' => ['required', 'string', 'max:100000'],
        ]);
        $type = (string) $request->string('type');
        $material = trim($request->string('material')->value());

        $attrs = ['user_id' => $uid, 'type' => $type, 'label' => $request->string('label')->value()];
        if ($type === 'pgp') {
            $fpr = $this->cipher->pgpFingerprint($material);
            if ($fpr === null) {
                return response()->json(['error' => 'invalid_key'], 422);
            }
            $attrs['public_key'] = $material;
            $attrs['fingerprint'] = $fpr;
        } else {
            if (! str_contains($material, 'BEGIN CERTIFICATE')) {
                return response()->json(['error' => 'invalid_cert'], 422);
            }
            $attrs['cert_pem'] = $material;
        }

        $r = new CryptoRecipient;
        $r->forceFill($attrs)->save();

        return response()->json(['recipient' => ['id' => $r->id, 'type' => $r->type, 'label' => $r->label, 'fingerprint' => $r->fingerprint]]);
    }

    public function destroyRecipient(Request $request, CryptoRecipient $recipient): JsonResponse
    {
        $this->requireUser($request);
        $recipient->delete(); // owner-scoped by the global scope + route binding

        return response()->json(['ok' => true]);
    }
}
