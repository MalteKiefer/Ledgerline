<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use App\Models\AddressBookShare;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Contacts sharing + subscribeable birthday feed.
 *
 *  - Internal cross-user address-book shares (viewer-only): the owner grants a
 *    registered user read access to one address book; the recipient browses it
 *    under "shared with me" but never mutates it.
 *  - A per-user secret .ics URL exposing every contact's birthday as a yearly
 *    all-day event, subscribeable in any calendar client (unauthenticated — the
 *    token in the path is the capability; rotatable/revocable).
 */
class ContactShareController extends Controller
{
    // ---- Owner side ----

    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $rows = AddressBookShare::query()->where('owner_id', $uid)->latest('id')->get()
            ->map(fn (AddressBookShare $s): array => [
                'id' => $s->id,
                'book_id' => $s->address_book_id,
                'book' => $s->book?->name,
                'recipient' => $s->recipient?->email,
            ])->values();

        return response()->json(['shares' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'email' => ['required', 'email'],
            'book_id' => ['required', 'string'],
        ]);
        $book = AddressBook::query()->where('user_id', $user->id)->findOrFail($request->string('book_id')->value());

        $recipient = User::query()->where('email', $request->string('email')->value())->first();
        if (! $recipient instanceof User || $recipient->id === $user->id) {
            return response()->json(['message' => 'recipient_invalid'], 422);
        }

        $existing = AddressBookShare::query()
            ->where('owner_id', $user->id)->where('recipient_id', $recipient->id)->where('address_book_id', $book->id)->first();
        if ($existing instanceof AddressBookShare) {
            return response()->json(['ok' => true, 'id' => $existing->id]);
        }

        $share = new AddressBookShare;
        $share->forceFill(['owner_id' => $user->id, 'recipient_id' => $recipient->id, 'address_book_id' => $book->id])->save();

        return response()->json(['ok' => true, 'id' => $share->id], 201);
    }

    public function destroy(Request $request, int $share): JsonResponse
    {
        $user = $this->requireUser($request);
        AddressBookShare::query()->where('owner_id', $user->id)->findOrFail($share)->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Recipient side ----

    public function sharedWithMe(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $rows = AddressBookShare::query()->withoutGlobalScopes()->where('recipient_id', $uid)->latest('id')->get()
            ->map(fn (AddressBookShare $s): array => [
                'id' => $s->id,
                'name' => AddressBook::query()->withoutGlobalScopes()->find($s->address_book_id)?->name,
                'owner' => $s->owner?->name,
                'count' => Contact::query()->withoutGlobalScopes()->where('address_book_id', $s->address_book_id)->count(),
            ])->values();

        return response()->json(['shares' => $rows]);
    }

    public function browse(Request $request, int $share): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $row = AddressBookShare::query()->withoutGlobalScopes()->where('recipient_id', $uid)->findOrFail($share);

        $contacts = Contact::query()->withoutGlobalScopes()
            ->where('address_book_id', $row->address_book_id)
            ->orderBy('fn')->get()
            ->map(fn (Contact $c): array => [
                'id' => $c->id,
                'fn' => $c->fn,
                'org' => $c->org,
                'emails' => $c->emails ?? [],
                'phones' => $c->phones ?? [],
            ])->values();

        $bookName = AddressBook::query()->withoutGlobalScopes()->find($row->address_book_id)?->name;

        return response()->json(['name' => $bookName, 'contacts' => $contacts]);
    }

    // ---- Birthday feed ----

    public function feed(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $token = is_string($user->birthday_feed_token) ? $user->birthday_feed_token : null;

        return response()->json([
            'enabled' => $token !== null,
            'url' => $token !== null ? url("/contacts/birthdays/{$token}.ics") : null,
        ]);
    }

    public function enableFeed(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $user->forceFill(['birthday_feed_token' => Str::random(40)])->save();

        return response()->json(['enabled' => true, 'url' => url('/contacts/birthdays/'.$user->birthday_feed_token.'.ics')]);
    }

    public function disableFeed(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $user->forceFill(['birthday_feed_token' => null])->save();

        return response()->json(['enabled' => false]);
    }

    /** Public, unauthenticated birthday .ics feed (token in path = capability). */
    public function ics(string $token): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{10,64}$/', $token) === 1, 404);
        $user = User::query()->where('birthday_feed_token', $token)->first();
        abort_unless($user instanceof User, 404);

        $year = (int) date('Y');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Ledgerline//Birthdays//EN', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:'.$this->esc(__('contacts.ui.birthdays'))];

        $bookIds = AddressBook::query()->where('user_id', $user->id)->pluck('id')->all();
        if ($bookIds !== []) {
            $contacts = Contact::query()->withoutGlobalScopes()
                ->whereIn('address_book_id', $bookIds)
                ->whereNotNull('bday')->where('bday', '!=', '')
                ->orderBy('fn')->get();
            foreach ($contacts as $c) {
                $md = $this->monthDay(is_string($c->bday) ? $c->bday : '');
                if ($md === null) {
                    continue;
                }
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:bday-'.$c->id.'@ledgerline';
                $lines[] = 'DTSTART;VALUE=DATE:'.sprintf('%04d%02d%02d', $year, $md[0], $md[1]);
                $lines[] = 'DURATION:P1D';
                $lines[] = 'RRULE:FREQ=YEARLY';
                $lines[] = 'SUMMARY:'.$this->esc('🎂 '.(is_string($c->fn) ? $c->fn : ''));
                $lines[] = 'TRANSP:TRANSPARENT';
                $lines[] = 'END:VEVENT';
            }
        }
        $lines[] = 'END:VCALENDAR';
        $body = implode("\r\n", $lines)."\r\n";

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="birthdays.ics"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Parse a denormalized MM-DD (or YYYY-MM-DD) birthday to [month, day].
     *
     * @return array{0:int, 1:int}|null
     */
    private function monthDay(string $bday): ?array
    {
        if (preg_match('/(\d{2})-(\d{2})$/', $bday, $m) !== 1) {
            return null;
        }
        $mo = (int) $m[1];
        $day = (int) $m[2];

        return ($mo >= 1 && $mo <= 12 && $day >= 1 && $day <= 31) ? [$mo, $day] : null;
    }

    /** iCalendar text escaping (RFC 5545). */
    private function esc(string $s): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
    }
}
