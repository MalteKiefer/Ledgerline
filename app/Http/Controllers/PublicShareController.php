<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileBlob;
use App\Models\GalleryBlob;
use App\Models\PublicShare;
use App\Support\BlobStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unauthenticated public gallery-album share links (/s/{token}). The server is
 * blind: it holds the sealed share manifest and streams opaque ciphertext blobs
 * from the owner's allow-list. The decryption key lives only in the URL fragment
 * (never sent here), so the browser does all rendering. The optional password is
 * a rate-limited access gate on top — not the encryption root.
 */
class PublicShareController extends Controller
{
    /** The share landing page (shell only; the browser fetches the rest). The
     *  gallery and file/folder shares use different viewer shells. */
    public function show(string $token): View
    {
        $share = $this->resolve($token);
        $view = $share !== null && $share->kind !== 'gallery_album' ? 'public.file-share' : 'public.share';

        return view($view, [
            'token' => $share?->token ?? $token,
            'found' => $share !== null,
            'expired' => $share !== null && $share->isExpired(),
            'needsPassword' => $share !== null && $share->needsPassword(),
        ]);
    }

    /** Access state for the client (does it need a password, is it unlocked?). */
    public function meta(Request $request, string $token): JsonResponse
    {
        $share = $this->resolve($token);
        if ($share === null) {
            return response()->json(['found' => false], 404);
        }
        if ($share->isExpired()) {
            return response()->json(['found' => true, 'expired' => true], 410);
        }

        return response()->json([
            'found' => true,
            'expired' => false,
            'needsPassword' => $share->needsPassword(),
            'unlocked' => $this->unlocked($request, $share),
            'allowDownload' => $share->allow_download,
        ]);
    }

    /** Verify the password gate (hard-throttled at the route). */
    public function unlock(Request $request, string $token): JsonResponse
    {
        $share = $this->resolve($token);
        abort_if($share === null || $share->isExpired(), 404);
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);

        if (! $share->needsPassword() || ! Hash::check($data['password'], (string) $share->password_hash)) {
            return response()->json(['ok' => false], 422);
        }

        $request->session()->put($this->gateKey($share), true);

        return response()->json(['ok' => true]);
    }

    /** The sealed (client-decryptable) share manifest — only past the gate. */
    public function manifest(Request $request, string $token): JsonResponse
    {
        $share = $this->resolve($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->unlocked($request, $share), 403);

        // Count one view per session, not per manifest poll.
        $counted = 'share_viewed.'.$share->id;
        if (! $request->session()->get($counted)) {
            $request->session()->put($counted, true);
            $share->forceFill(['views' => $share->views + 1, 'last_viewed_at' => now()])->saveQuietly();
        }

        return response()->json([
            'sealed' => $share->sealed_manifest,
            'allowDownload' => $share->allow_download,
        ]);
    }

    /** Stream one opaque ciphertext blob from the share's allow-list. */
    public function blob(Request $request, string $token, string $ref): StreamedResponse
    {
        $share = $this->resolve($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->unlocked($request, $share), 403);
        abort_unless(Str::isUuid($ref), 404);
        // The link may only stream blobs the owner explicitly put in it, and only
        // blobs that owner actually owns (defence in depth against a stale ref).
        abort_unless(in_array($ref, $share->blob_refs ?? [], true), 404);
        [$prefix, $ledger] = $this->blobSource($share);
        abort_unless($ledger::where('blob', $ref)->where('user_id', $share->user_id)->exists(), 404);

        $disk = BlobStore::disk();
        $path = $prefix.'/'.$ref;
        abort_unless($disk->exists($path), 404);

        // Ciphertext; the browser decrypts in memory. Force a script-less sandbox
        // and immutable caching (blobs are content-addressed and never mutate).
        return BlobStore::immutableResponse(
            $disk->response($path, 'file', ['Content-Type' => 'application/octet-stream'], 'attachment'),
            $ref,
        );
    }

    private function resolve(string $token): ?PublicShare
    {
        if (! preg_match('/^[A-Za-z0-9]{1,32}$/', $token)) {
            return null;
        }

        return PublicShare::where('token', $token)->first();
    }

    /** Where a share's opaque blobs live: [disk prefix, ownership-ledger model]. */
    private function blobSource(PublicShare $share): array
    {
        return $share->kind === 'gallery_album'
            ? ['gallery', GalleryBlob::class]
            : ['files', FileBlob::class];
    }

    private function unlocked(Request $request, PublicShare $share): bool
    {
        return ! $share->needsPassword() || (bool) $request->session()->get($this->gateKey($share));
    }

    private function gateKey(PublicShare $share): string
    {
        return 'share_unlocked.'.$share->id;
    }
}
