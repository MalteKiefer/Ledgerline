<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FilePublicLink;
use App\Models\StoredFile;
use App\Support\BlobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, tokenised read-only download links for a single owned file. Owner
 * endpoints create/rotate/revoke a link; the public endpoints (no auth) serve
 * the file, optionally behind a password gate. The download is streamed with a
 * script-less CSP and attachment disposition.
 */
class FilePublicLinkController extends Controller
{
    /** Allowed expiry presets, in seconds (null = never). */
    private const EXPIRY = [3600, 86400, 604800, 2592000];

    /** Create or update the public link for an owned file. */
    public function store(Request $request, StoredFile $file): JsonResponse
    {
        $this->authorizeFile($file);
        $data = $request->validate([
            'expires_in' => ['nullable', 'integer', Rule::in(self::EXPIRY)],
            'password' => ['nullable', 'string', 'min:10', 'max:255'],
        ]);

        $link = FilePublicLink::firstOrNew(['stored_file_id' => $file->id]);
        if (! $link->exists) {
            $link->token = $this->newToken();
        }
        $link->expires_at = isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null;
        if ($request->has('password')) {
            $link->password = filled($data['password'] ?? null) ? $data['password'] : null;
        }
        $link->save();

        return response()->json($this->toArray($link));
    }

    public function rotate(FilePublicLink $link): JsonResponse
    {
        $this->authorizeLink($link);
        $link->update(['token' => $this->newToken()]);

        return response()->json($this->toArray($link));
    }

    public function destroy(FilePublicLink $link): JsonResponse
    {
        $this->authorizeLink($link);
        $link->delete();

        return response()->json(['ok' => true]);
    }

    /** The link for an owned file (or null), for the share modal. */
    public function show(StoredFile $file): JsonResponse
    {
        $this->authorizeFile($file);
        $link = FilePublicLink::where('stored_file_id', $file->id)->first();

        return response()->json(['link' => $link ? $this->toArray($link) : null]);
    }

    // ---- Public (no auth) ----

    /** Serve the file (or the password gate) for a public token. */
    public function download(Request $request, string $token): StreamedResponse|View|Response
    {
        $link = FilePublicLink::withoutGlobalScopes()->where('token', $token)->first();
        abort_if($link === null, 404);
        abort_if($link->isExpired(), 410, __('shares.public_expired'));

        if ($link->isProtected() && ! $this->unlocked($request, $link)) {
            return response()->view('public-file.password', ['token' => $token, 'error' => false])
                ->withHeaders($this->pageHeaders());
        }

        // withoutGlobalScopes() strips the SoftDeletingScope, so a trashed file
        // must be excluded explicitly — a public link must not keep serving a
        // file the owner has since deleted.
        $file = StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->find($link->stored_file_id);
        abort_if($file === null, 404);
        $disk = BlobStore::disk();
        abort_unless($disk->exists('files/'.$file->blob), 404);

        $link->increment('downloads');

        // Force a neutral type (not the client-controlled stored mime) so a
        // link can never be used to serve active content in-origin.
        return $disk->download('files/'.$file->blob, $file->name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** Check the password and unlock the link for this session. */
    public function unlock(Request $request, string $token): Response|RedirectResponse
    {
        $link = FilePublicLink::withoutGlobalScopes()->where('token', $token)->first();
        abort_if($link === null, 404);
        abort_if($link->isExpired(), 410);

        // Per-token brute-force cap on top of the per-IP route throttle, so a
        // botnet can't distribute a guessing attack across many IPs.
        $key = 'filelink-unlock:'.sha1($token);
        abort_if(RateLimiter::tooManyAttempts($key, 10), 429);

        $given = (string) $request->input('password', '');
        if (! $link->isProtected() || ! Hash::check($given, $link->password)) {
            RateLimiter::hit($key, 900);

            return response()->view('public-file.password', ['token' => $token, 'error' => true])
                ->withHeaders($this->pageHeaders());
        }

        RateLimiter::clear($key);
        $request->session()->put($this->sessionKey($token), true);

        return redirect()->route('file-link.download', $token);
    }

    private function unlocked(Request $request, FilePublicLink $link): bool
    {
        return (bool) $request->session()->get($this->sessionKey($link->token));
    }

    private function sessionKey(string $token): string
    {
        return 'filelink:'.$token;
    }

    /** @return array<string,string> */
    private function pageHeaders(): array
    {
        return [
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function newToken(): string
    {
        return Str::random(48);
    }

    private function authorizeFile(StoredFile $file): void
    {
        abort_unless($file->isOwnedBy(auth()->id()), 403);
    }

    private function authorizeLink(FilePublicLink $link): void
    {
        abort_unless((int) $link->user_id === (int) auth()->id(), 403);
    }

    /** @return array<string,mixed> */
    private function toArray(FilePublicLink $link): array
    {
        return [
            'id' => $link->id,
            'token' => $link->token,
            'url' => route('file-link.download', $link->token),
            'expiresAt' => $link->expires_at?->toIso8601String(),
            'hasPassword' => $link->isProtected(),
            'downloads' => (int) $link->downloads,
        ];
    }
}
