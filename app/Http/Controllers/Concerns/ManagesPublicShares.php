<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

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
        return [
            'sealed_manifest' => ['required', 'string', 'max:'.(int) config('gallery.share_max_manifest_bytes')],
            'blob_refs' => ['required', 'array', 'max:'.(int) config('gallery.share_max_blobs')],
            'blob_refs.*' => ['string', 'uuid'],
            'allow_download' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:200'],
        ];
    }

    protected function createShare(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate($this->shareRules());

        $token = $this->uniqueShareToken();
        $share = new PublicShare;
        $share->token = $token;
        $share->user_id = (int) $request->user()->id;
        $share->kind = $kind;
        $share->sealed_manifest = $data['sealed_manifest'];
        $share->blob_refs = array_values(array_unique($data['blob_refs']));
        $share->allow_download = (bool) ($data['allow_download'] ?? false);
        $share->expires_at = $data['expires_at'] ?? null;
        $share->password_hash = ! empty($data['password']) ? Hash::make($data['password']) : null;
        $share->save();

        return response()->json(['token' => $token]);
    }

    protected function updateShareRecord(Request $request, string $token): JsonResponse
    {
        $share = $this->ownedShare($token);
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
        $this->ownedShare($token)->delete();

        return response()->json(['ok' => true]);
    }

    private function ownedShare(string $token): PublicShare
    {
        return PublicShare::query()
            ->where('token', $token)
            ->where('user_id', (int) request()->user()->id)
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
