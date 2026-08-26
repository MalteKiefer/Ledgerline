<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BrandIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fetches a site's icon (BIMI logo, else favicon) — used by the finance module
 * for bank / business-partner logos. This is a deliberate, user-opted boundary
 * crossing: the domain is sent here transiently to fetch the icon, never stored
 * server-side. The fetch goes through the SSRF guard; the result is returned as
 * a data URI which the client stores alongside the record, so it never has to
 * ask again.
 */
class PasswordIconController extends Controller
{
    /**
     * The ladder itself now lives in Support\BrandIcon, because mail sender
     * avatars want exactly the same one and a second copy would drift.
     */
    public function fetch(Request $request): JsonResponse
    {
        $domain = (string) $request->query('domain', '');

        return response()->json(['icon' => BrandIcon::forDomain($domain)]);
    }
}
