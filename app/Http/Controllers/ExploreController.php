<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The Explore map view. Zero-knowledge: tracks, photo↔track couplings and the
 * coupling tolerances all live in the user's sealed `explore` module store; the
 * server never sees any of it. This controller only renders the shell — the
 * Alpine `explore` component decrypts and does all the work in the browser.
 */
class ExploreController extends Controller
{
    public function __invoke(): View
    {
        return view('explore');
    }
}
