<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Addresses to suggest when writing, ranked by how much mail you have from
 * them.
 *
 * The address book was the only source, and most people you correspond with are
 * not in it — so the field stayed empty for exactly the addresses you type most
 * often.
 *
 * Ranked by the number of messages received, which is a decent stand-in for
 * "someone you actually write to" and, being a plain column, costs one grouped
 * query rather than a scan.
 *
 * It covers who has written TO you, not who you have written to: recipients
 * live in a JSON column, and scanning that on every keystroke across twenty
 * thousand rows would be the wrong trade. In practice correspondence goes both
 * ways, so the addresses that matter are nearly all in here; the one case it
 * misses is someone you have only ever written to and never heard back from.
 */
class MailRecipientController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $q = trim($request->string('q')->value());

        // Two characters: below that the ranking is just "your busiest senders",
        // which is not what someone typing a name is asking for.
        if (mb_strlen($q) < 2) {
            return response()->json(['recipients' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q)).'%';

        $rows = MailMessage::query()
            ->where('user_id', $user->id)
            ->whereNotNull('from_email')
            ->where('from_email', '!=', '')
            // lower() on both sides: Postgres LIKE is case-sensitive and
            // SQLite's is not, so without it this behaves differently in
            // production than in the tests.
            ->where(function ($w) use ($like): void {
                $w->whereRaw('lower(from_email) like ?', [$like])
                    ->orWhereRaw('lower(from_name) like ?', [$like]);
            })
            ->selectRaw('lower(from_email) as email, max(from_name) as name, count(*) as hits')
            ->groupByRaw('lower(from_email)')
            ->orderByRaw('count(*) desc')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'recipients' => $rows->map(function (MailMessage $r): array {
                // Raw select aliases, so each one is narrowed before it is cast
                // rather than trusted to be the shape we asked for.
                $email = $r->getAttribute('email');
                $name = $r->getAttribute('name');
                $hits = $r->getAttribute('hits');

                return [
                    'email' => is_scalar($email) ? (string) $email : '',
                    'name' => is_scalar($name) ? (string) $name : '',
                    'hits' => is_numeric($hits) ? (int) $hits : 0,
                ];
            })->all(),
        ]);
    }
}
