<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OutboundUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * k-anonymity proxy to Have I Been Pwned's range API for the password manager's
 * breach check. The client sends only the first 5 hex chars of a password's
 * SHA-1 — never the password, never which item — and gets back the list of
 * matching suffixes + counts to compare locally. The outbound call goes through
 * the SSRF guard. Nothing is stored.
 */
class PasswordBreachController extends Controller
{
    public function range(Request $request): Response
    {
        $prefix = strtoupper((string) $request->query('prefix', ''));
        abort_unless(preg_match('/^[0-9A-F]{5}$/', $prefix) === 1, 422);

        $body = '';
        try {
            $res = OutboundUrl::client('https://api.pwnedpasswords.com/range/'.$prefix, 8)
                ->withHeaders(['Add-Padding' => 'true', 'User-Agent' => 'Ledgerline'])
                ->get('https://api.pwnedpasswords.com/range/'.$prefix);
            if ($res->ok()) {
                $body = (string) $res->body();
            }
        } catch (Throwable) {
            $body = '';
        }

        // Plain text (SUFFIX:count per line); never cached in a shared proxy.
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
