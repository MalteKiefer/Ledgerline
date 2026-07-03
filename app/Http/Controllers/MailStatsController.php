<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Services\Mail\ImapStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only IMAP statistics.
 *
 * The account is referenced by id; the (encrypted) credentials are loaded
 * server-side and never travel to the browser. Nothing is persisted or logged;
 * the password lives only in memory during the connection. TLS is required.
 */
class MailStatsController extends Controller
{
    public function show(Request $request, ImapStats $imap): JsonResponse
    {
        $request->validate(['account_id' => ['required', 'exists:mail_accounts,id']]);
        $account = MailAccount::findOrFail($request->integer('account_id'));

        try {
            $stats = $imap->fetch($account->credentials());
        } catch (\Throwable) {
            // Do not surface raw connection errors (they can leak host details);
            // and never log the credentials that were posted.
            return response()->json(['message' => __('mail.connect_failed')], 422);
        }

        return response()->json($stats);
    }
}
