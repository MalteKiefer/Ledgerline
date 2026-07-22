<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\PublicShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Owner-side CRUD for public share links, shared by the gallery and files
 * controllers. The client seals the share manifest (the shared item's structure
 * + each blob's per-file key re-wrapped under the link's fragment key) before it
 * arrives, so every persisted field is either ciphertext or a coarse access
 * control. Explicitly owner-scoped — PublicShare has no global read scope so the
 * public routes can resolve a link without an authenticated user.
 */
trait ManagesPublicShares
{
    /** @return array<string, mixed> */
    protected function shareRules(): array
    {
        $maxManifest = config('gallery.share_max_manifest_bytes');
        $maxBlobs = config('gallery.share_max_blobs');

        return [
            'sealed_manifest' => ['required', 'string', 'max:'.(is_numeric($maxManifest) ? (int) $maxManifest : 0)],
            'blob_refs' => ['required', 'array', 'max:'.(is_numeric($maxBlobs) ? (int) $maxBlobs : 0)],
            'blob_refs.*' => ['string', 'uuid'],
            'allow_download' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:200'],
        ];
    }

    protected function createShare(Request $request, string $kind): JsonResponse
    {
        $user = $this->requireUser($request);
        $data = $request->validate($this->shareRules());

        $token = $this->uniqueShareToken();
        $share = new PublicShare;
        $share->token = $token;
        $share->user_id = (int) $user->id;
        $share->kind = $kind;
        $share->sealed_manifest = $data['sealed_manifest'];
        $share->blob_refs = array_values(array_unique($data['blob_refs']));
        $share->allow_download = (bool) ($data['allow_download'] ?? false);
        $share->expires_at = $data['expires_at'] ?? null;
        $share->password_hash = ! empty($data['password']) ? Hash::make($data['password']) : null;
        $share->save();

        // NEVER log the full token — it grants access. Log the row id + kind only.
        AuditLog::record('share.public.created', $share, [
            'kind' => $kind,
            'has_password' => $share->password_hash !== null,
        ]);

        return response()->json(['token' => $token]);
    }

    protected function updateShareRecord(Request $request, string $token): JsonResponse
    {
        $share = $this->ownedShare($request, $token);
        $data = $request->validate($this->shareRules() + ['clear_password' => ['boolean']]);

        $share->sealed_manifest = $data['sealed_manifest'];
        $share->blob_refs = array_values(array_unique($data['blob_refs']));
        $share->allow_download = (bool) ($data['allow_download'] ?? false);
        $share->expires_at = $data['expires_at'] ?? null;
        if ($request->boolean('clear_password')) {
            $share->password_hash = null;
        } elseif (! empty($data['password'])) {
            $share->password_hash = Hash::make($data['password']);
        }
        $share->save();

        return response()->json(['token' => $share->token]);
    }

    protected function destroyShareRecord(Request $request, string $token): JsonResponse
    {
        $this->ownedShare($request, $token)->delete();

        return response()->json(['ok' => true]);
    }

    private function ownedShare(Request $request, string $token): PublicShare
    {
        $user = $this->requireUser($request);

        return PublicShare::query()
            ->where('token', $token)
            ->where('user_id', (int) $user->id)
            ->firstOrFail();
    }

    private function uniqueShareToken(): string
    {
        do {
            $token = Str::random(22);
        } while (PublicShare::where('token', $token)->exists());

        return $token;
    }
}
