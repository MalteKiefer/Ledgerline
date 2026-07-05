<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Contacts\DavCredentialService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Shows the signed-in user's profile.
 *
 * Identity data is owned by Pocket-ID and refreshed on each login (read-only).
 * The page also surfaces the per-user CardDAV/CalDAV sync credentials — one
 * login serves both address books and calendars over /dav/.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request, DavCredentialService $credentials): View
    {
        $credential = $credentials->for($request->user()->id);
        $davUrl = url('/dav/');

        return view('profile', [
            'user' => $request->user(),
            'credential' => $credential,
            'davUrl' => $davUrl,
            'davQr' => $credential !== null ? $this->qr($davUrl) : null,
        ]);
    }

    private function qr(string $text): string
    {
        $renderer = new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($text);
    }
}
