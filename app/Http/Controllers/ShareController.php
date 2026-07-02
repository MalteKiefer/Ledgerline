<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NoteShare;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Time-limited public sharing of notes.
 *
 * A share is a frozen, client-encrypted snapshot. The store/destroy endpoints
 * are for the authenticated owner; the show/data endpoints are public so a
 * recipient without an account can open the link. The server only ever handles
 * ciphertext — it can never read a shared note.
 */
class ShareController extends Controller
{
    /** Allowed lifetimes in seconds: 1 hour, 1 day, 1 week, 30 days. */
    private const LIFETIMES = [3600, 86400, 604800, 2592000];

    /**
     * Create a share from a ciphertext produced in the browser (auth).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cipher' => ['required', 'string'],
            'nonce' => ['required', 'string', 'max:255'],
            'expires_in' => ['required', 'integer', Rule::in(self::LIFETIMES)],
            'max_views' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'has_password' => ['required', 'boolean'],
            'wrapped_key' => ['required_if:has_password,true', 'nullable', 'string'],
            'wrap_salt' => ['required_if:has_password,true', 'nullable', 'string', 'max:255'],
            'wrap_nonce' => ['required_if:has_password,true', 'nullable', 'string', 'max:255'],
            'wrap_ops' => ['required_if:has_password,true', 'nullable', 'integer', 'min:1'],
            'wrap_mem' => ['required_if:has_password,true', 'nullable', 'integer', 'min:1'],
        ]);

        $share = NoteShare::create([
            'cipher' => $validated['cipher'],
            'nonce' => $validated['nonce'],
            'has_password' => $validated['has_password'],
            'wrapped_key' => $validated['wrapped_key'] ?? null,
            'wrap_salt' => $validated['wrap_salt'] ?? null,
            'wrap_nonce' => $validated['wrap_nonce'] ?? null,
            'wrap_ops' => $validated['wrap_ops'] ?? null,
            'wrap_mem' => $validated['wrap_mem'] ?? null,
            'max_views' => $validated['max_views'] ?? null,
            'expires_at' => now()->addSeconds($validated['expires_in']),
        ]);

        return response()->json([
            'id' => $share->id,
            'url' => route('shares.show', $share),
            'expires_at' => $share->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Serve the public viewer shell (no auth). The ciphertext is fetched
     * separately by the browser and decrypted with the key from the fragment.
     */
    public function show(NoteShare $share): Response
    {
        return response()
            ->view('share.show', ['id' => $share->id])
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Return the ciphertext for a share (no auth). Counts the retrieval and,
     * once expired or over its view limit, deletes the row and returns 410.
     */
    public function data(NoteShare $share): JsonResponse
    {
        if ($share->isGone()) {
            $share->delete();

            return response()->json(['message' => 'gone'], Response::HTTP_GONE);
        }

        $share->increment('views');

        $payload = [
            'cipher' => $share->cipher,
            'nonce' => $share->nonce,
            'has_password' => $share->has_password,
        ];

        if ($share->has_password) {
            $payload += [
                'wrapped_key' => $share->wrapped_key,
                'wrap_salt' => $share->wrap_salt,
                'wrap_nonce' => $share->wrap_nonce,
                'wrap_ops' => $share->wrap_ops,
                'wrap_mem' => $share->wrap_mem,
            ];
        }

        return response()->json($payload)->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Revoke a share (auth): delete the ciphertext immediately.
     */
    public function destroy(NoteShare $share): JsonResponse
    {
        $share->delete();

        return response()->json(['deleted' => true]);
    }
}
