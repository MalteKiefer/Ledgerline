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
        $request->validate($this->shareRules());

        $token = $this->uniqueShareToken();
        $share = new PublicShare;
        $share->token = $token;
        $share->user_id = (int) $user->id;
        $share->kind = $kind;
        $this->applyShareData($share, $request);
        $password = $request->string('password')->value();
        $share->password_hash = $password !== '' ? Hash::make($password) : null;
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
        $request->validate($this->shareRules() + ['clear_password' => ['boolean']]);

        $this->applyShareData($share, $request);
        if ($request->boolean('clear_password')) {
            $share->password_hash = null;
        } else {
            $password = $request->string('password')->value();
            if ($password !== '') {
                $share->password_hash = Hash::make($password);
            }
        }
        $share->save();

        return response()->json(['token' => $share->token]);
    }

    /**
     * Copy the already-validated (and thus type-checked) share fields onto the
     * model, reading each through a typed request accessor so nothing is `mixed`.
     */
    private function applyShareData(PublicShare $share, Request $request): void
    {
        $share->sealed_manifest = $request->string('sealed_manifest')->value();

        /** @var list<string> $refs */
        $refs = array_values(array_unique($request->collect('blob_refs')
            ->map(fn (mixed $v): string => is_string($v) ? $v : '')
            ->all()));
        $share->blob_refs = $refs;

        $share->allow_download = $request->boolean('allow_download');

        // 'expires_at' passed the 'nullable|date' rule; date() yields Carbon|null.
        $share->expires_at = $request->date('expires_at');
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
