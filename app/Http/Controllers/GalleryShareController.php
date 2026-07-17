<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PublicShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Owner-side management of public gallery-album share links. The client seals
 * the share manifest (album photo list + per-blob keys re-wrapped under the
 * link's share key) before it ever reaches here, so every field this controller
 * persists is either ciphertext or a coarse access control. Explicitly
 * owner-scoped: PublicShare has no global read scope (the public routes resolve
 * links without an authenticated user), so every query here filters on the
 * signed-in user's id.
 */
class GalleryShareController extends Controller
{
    private function rules(): array
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $token = $this->uniqueToken();
        $share = new PublicShare;
        $share->token = $token;
        $share->user_id = (int) $request->user()->id;
        $share->kind = 'gallery_album';
        $share->sealed_manifest = $data['sealed_manifest'];
        $share->blob_refs = array_values(array_unique($data['blob_refs']));
        $share->allow_download = (bool) ($data['allow_download'] ?? false);
        $share->expires_at = $data['expires_at'] ?? null;
        $share->password_hash = ! empty($data['password']) ? Hash::make($data['password']) : null;
        $share->save();

        return response()->json(['token' => $token]);
    }

    public function update(Request $request, string $token): JsonResponse
    {
        $share = $this->owned($token);
        $data = $request->validate($this->rules() + [
            'clear_password' => ['boolean'],
        ]);

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

    public function destroy(Request $request, string $token): JsonResponse
    {
        $this->owned($token)->delete();

        return response()->json(['ok' => true]);
    }

    private function owned(string $token): PublicShare
    {
        return PublicShare::query()
            ->where('token', $token)
            ->where('user_id', (int) request()->user()->id)
            ->firstOrFail();
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(22);
        } while (PublicShare::where('token', $token)->exists());

        return $token;
    }
}
