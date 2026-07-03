<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NoteShare;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Public viewing of a shared note (no auth). A share is a frozen server-side
 * snapshot (title + markdown); the server renders it, enforcing expiry, view
 * limit and an optional password. Creating/revoking shares lives in
 * NoteController (owner-only).
 */
class ShareController extends Controller
{
    public function show(Request $request, NoteShare $share): View|Response
    {
        if ($share->isGone()) {
            $share->delete();

            return response()->view('share.gone', [], Response::HTTP_GONE)
                ->header('Referrer-Policy', 'no-referrer');
        }

        // Password gate: unlocked flag is held in the session per share.
        if ($share->has_password && ! $request->session()->get($this->unlockedKey($share))) {
            return response()->view('share.password', ['share' => $share, 'error' => false])
                ->header('Referrer-Policy', 'no-referrer');
        }

        // Count the view atomically so a burst of concurrent requests to a
        // burn-after-read share (max_views) cannot all render before any
        // increment lands. A conditional UPDATE that matches zero rows means
        // the limit was reached first, so this request is gone too.
        if ($share->max_views !== null) {
            $counted = NoteShare::whereKey($share->getKey())
                ->whereColumn('views', '<', 'max_views')
                ->update(['views' => DB::raw('views + 1')]);

            if ($counted === 0) {
                $share->delete();

                return response()->view('share.gone', [], Response::HTTP_GONE)
                    ->header('Referrer-Policy', 'no-referrer');
            }
        } else {
            $share->increment('views');
        }

        return response()->view('share.show', [
            'share' => $share,
            'html' => NoteController::render($share->content),
        ])->header('Referrer-Policy', 'no-referrer');
    }

    public function unlock(Request $request, NoteShare $share): RedirectResponse|View|Response
    {
        if ($share->isGone()) {
            $share->delete();

            return response()->view('share.gone', [], Response::HTTP_GONE);
        }
        $request->validate(['password' => ['required', 'string']]);

        if (! $share->has_password || ! Hash::check($request->input('password'), (string) $share->password_hash)) {
            return response()->view('share.password', ['share' => $share, 'error' => true], Response::HTTP_UNPROCESSABLE_ENTITY)
                ->header('Referrer-Policy', 'no-referrer');
        }

        $request->session()->put($this->unlockedKey($share), true);

        return redirect()->route('shares.show', $share);
    }

    private function unlockedKey(NoteShare $share): string
    {
        return 'share_unlocked_'.$share->id;
    }
}
