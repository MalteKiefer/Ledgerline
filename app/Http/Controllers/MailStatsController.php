<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only IMAP statistics.
 *
 * Stateless by design: the browser decrypts the account credentials from the
 * vault manifest and posts them here only for a single fetch. Nothing is
 * stored or logged — the server sees the password only in memory during the
 * connection. TLS is required (plaintext logins are rejected).
 */
class MailStatsController extends Controller
{
    public function show(Request $request, ImapStats $imap): JsonResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(['ssl', 'starttls'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'validate_cert' => ['sometimes', 'boolean'],
        ]);

        try {
            $stats = $imap->fetch(new ImapCredentials(
                host: $validated['host'],
                port: (int) $validated['port'],
                encryption: $validated['encryption'],
                username: $validated['username'],
                password: $validated['password'],
                validateCert: (bool) ($validated['validate_cert'] ?? true),
            ));
        } catch (\Throwable) {
            // Do not surface raw connection errors (they can leak host details);
            // and never log the credentials that were posted.
            return response()->json(['message' => __('mail.connect_failed')], 422);
        }

        return response()->json($stats);
    }
}
