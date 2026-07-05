<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use App\Models\AppSettings;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\Contact;
use App\Models\PublicShare;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Sabre\VObject\Reader;

/**
 * Public, tokenised read-only links to a calendar or address book for people
 * without an account: an HTML view, an ICS subscription feed and a vCard export.
 * The authenticated half (create/revoke/email) is owner-only.
 */
class PublicShareController extends Controller
{
    private const TYPES = ['calendars' => Calendar::class, 'address-books' => AddressBook::class];

    /** Create (or return) the public link for an owned calendar/address book. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'id' => ['required'],
        ]);

        $resource = $this->ownedResource($data['type'], $data['id'], $request->user()->id);
        $share = PublicShare::forResource($resource, $request->user()->id);

        return response()->json(['ok' => true, 'url' => route('public-share.show', $share->token)], 201);
    }

    public function destroy(Request $request, PublicShare $publicShare): JsonResponse
    {
        abort_unless($publicShare->owner_id === $request->user()->id, 403);
        $publicShare->delete();

        return response()->json(['ok' => true]);
    }

    /** Email the public link to any address (SMTP required). */
    public function email(Request $request, PublicShare $publicShare, ChannelNotifier $notifier): JsonResponse
    {
        abort_unless($publicShare->owner_id === $request->user()->id, 403);
        abort_unless(ChannelNotifier::mailConfigured(), 422, __('shares.mail_unavailable'));
        $to = $request->validate(['email' => ['required', 'email']])['email'];

        $owner = $request->user()->name ?: $request->user()->email;
        $link = route('public-share.show', $publicShare->token);
        try {
            $notifier->mailTo(AppSettings::current(), $to, __('shares.mail_subject', ['user' => $owner]), __('shares.mail_body', ['user' => $owner, 'link' => $link]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    // ---- public (no auth) ---------------------------------------------------

    public function show(PublicShare $publicShare): View
    {
        $resource = $publicShare->shareable;
        abort_if($resource === null, 404);

        if ($resource instanceof Calendar) {
            $events = CalendarObject::where('calendar_id', $resource->id)->where('component', 'VEVENT')
                ->orderByRaw('coalesce(starts_at, created_at) asc')->limit(500)->get();

            return view('public-share.calendar', ['share' => $publicShare, 'calendar' => $resource, 'events' => $events]);
        }

        $contacts = Contact::where('address_book_id', $resource->id)->orderBy('fn')->get();

        return view('public-share.addressbook', ['share' => $publicShare, 'book' => $resource, 'contacts' => $contacts]);
    }

    /** ICS subscription feed for a shared calendar. */
    public function ics(PublicShare $publicShare): Response
    {
        $resource = $publicShare->shareable;
        abort_unless($resource instanceof Calendar, 404);

        $blocks = [];
        CalendarObject::where('calendar_id', $resource->id)->orderBy('id')
            ->chunk(200, function ($chunk) use (&$blocks): void {
                foreach ($chunk as $object) {
                    foreach ($this->innerComponents($object->ics) as $b) {
                        $blocks[] = $b;
                    }
                }
            });

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Ledgerline//Public//EN\r\nX-WR-CALNAME:".$this->esc($resource->name)."\r\n".implode('', $blocks)."END:VCALENDAR\r\n";

        return response($ics, 200, ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    /** vCard export for a shared address book. */
    public function vcf(PublicShare $publicShare): Response
    {
        $resource = $publicShare->shareable;
        abort_unless($resource instanceof AddressBook, 404);

        $body = '';
        Contact::where('address_book_id', $resource->id)->orderBy('id')->chunk(200, function ($chunk) use (&$body): void {
            foreach ($chunk as $contact) {
                $body .= rtrim($contact->vcard, "\r\n")."\r\n";
            }
        });

        return response($body, 200, ['Content-Type' => 'text/vcard; charset=utf-8']);
    }

    /** Inner VEVENT/VTIMEZONE blocks of a stored per-object VCALENDAR. */
    private function innerComponents(string $ics): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($vcal->getComponents() as $component) {
            if (in_array($component->name, ['VEVENT', 'VTIMEZONE'], true)) {
                $out[] = $component->serialize();
            }
        }

        return $out;
    }

    private function esc(string $v): string
    {
        return str_replace(["\r", "\n"], '', $v);
    }

    private function ownedResource(string $type, mixed $id, int $userId): Model
    {
        $class = self::TYPES[$type];
        $resource = $class::withoutGlobalScopes()->findOrFail($id);
        abort_unless($resource->isOwnedBy($userId), 403);
        if ($resource instanceof Calendar && ($resource->isVirtual() || $resource->isReadOnly())) {
            abort(422, 'This calendar cannot be shared.');
        }

        return $resource;
    }
}
