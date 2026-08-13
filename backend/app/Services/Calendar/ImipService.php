<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Mail\ImipMail;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\CalendarAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use Throwable;

/**
 * iMIP (RFC 6047 / 5546) meeting messaging. Outbound: e-mail REQUEST/CANCEL to
 * attendees and REPLY to the organizer, best-effort (never breaks the calendar
 * write). Inbound: ingest a received iCalendar payload (REQUEST/REPLY/CANCEL)
 * into the user's calendars. All parsing is defensive (untrusted input).
 */
class ImipService
{
    public function __construct(private readonly CalendarEventService $events, private readonly CalendarWriter $writer) {}

    /** Narrow a mixed (sabre node / model attr / parsed value) to a string. */
    private function str(mixed $v): string
    {
        return is_scalar($v) || $v instanceof \Stringable ? (string) $v : '';
    }

    /** Wrap the event's ICS in a VCALENDAR carrying the given METHOD. */
    public function methodIcs(CalendarEvent $event, string $method): string
    {
        try {
            $cal = Reader::read($event->ics);
            if ($cal instanceof VCalendar) {
                $cal->remove('METHOD');
                $cal->add('METHOD', $method);
                $s = $cal->serialize();

                return is_string($s) ? $s : '';
            }
        } catch (Throwable) {
            // fall through
        }

        return '';
    }

    /** E-mail a REQUEST (or CANCEL) to every attendee. Best-effort. */
    public function notifyAttendees(CalendarEvent $event, string $method): void
    {
        $parsed = $this->events->parse($event->ics);
        $attendees = is_array($parsed['attendees'] ?? null) ? $parsed['attendees'] : [];
        if ($attendees === []) {
            return;
        }
        $ics = $this->methodIcs($event, $method);
        if ($ics === '') {
            return;
        }
        $summary = is_string($parsed['summary'] ?? null) ? $parsed['summary'] : '';
        $subject = ($method === 'CANCEL' ? '['.__('calendar.imip.cancelled').'] ' : '').$summary;
        $body = $method === 'CANCEL' ? __('calendar.imip.cancel_body', ['event' => $summary]) : __('calendar.imip.request_body', ['event' => $summary]);

        foreach ($attendees as $a) {
            $email = is_array($a) && is_string($a['email'] ?? null) ? $a['email'] : '';
            if ($email === '' || ! str_contains($email, '@')) {
                continue;
            }
            $this->deliver($email, (string) $subject, (string) $body, $ics, $method);
        }
    }

    /** E-mail a REPLY (the responder's PARTSTAT) to the organizer. Best-effort. */
    public function sendReply(CalendarEvent $event, User $responder, string $partstat): void
    {
        $parsed = $this->events->parse($event->ics);
        $organizer = is_string($parsed['organizer'] ?? null) ? $parsed['organizer'] : '';
        if ($organizer === '' || ! str_contains($organizer, '@')) {
            return;
        }
        try {
            $cal = new VCalendar;
            $cal->add('METHOD', 'REPLY');
            /** @var VEvent $ev */
            $ev = $cal->add('VEVENT', []);
            $ev->remove('UID');
            $ev->add('UID', is_string($parsed['uid'] ?? null) ? $parsed['uid'] : '');
            $seqRaw = $parsed['sequence'] ?? 0;
            $seq = is_numeric($seqRaw) ? (int) $seqRaw : 0;
            $ev->add('SEQUENCE', (string) $seq);
            $ev->add('DTSTAMP', gmdate('Ymd\THis\Z'));
            $ev->add('ORGANIZER', 'mailto:'.$organizer);
            $ev->add('ATTENDEE', 'mailto:'.$responder->email, ['PARTSTAT' => $partstat, 'CN' => $this->str($responder->name)]);
            if (is_string($parsed['summary'] ?? null)) {
                $ev->add('SUMMARY', $parsed['summary']);
            }
            $ics = $cal->serialize();
            if (is_string($ics)) {
                $this->deliver($organizer, __('calendar.imip.reply_subject', ['event' => $this->str($parsed['summary'] ?? '')]), __('calendar.imip.reply_body', ['status' => $partstat]), $ics, 'REPLY');
            }
        } catch (Throwable $e) {
            Log::warning('iMIP reply failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Ingest a received iCalendar payload into the user's calendars.
     *
     * $senderEmail is the AUTHENTICATED sender of the message carrying this
     * payload (the SMTP/From identity for the mail auto-hook). When provided it
     * is enforced against the iCalendar identity so a spoofed .ics cannot flip an
     * attendee's PARTSTAT (REPLY) or fake an organizer's REQUEST/CANCEL. Null =
     * trusted, user-initiated ingest (the authenticated owner pasting an .ics).
     *
     * @return array{method:?string,action:string}
     */
    public function ingest(int $uid, string $ics, ?string $senderEmail = null): array
    {
        try {
            $cal = Reader::read($ics);
        } catch (Throwable) {
            return ['method' => null, 'action' => 'invalid'];
        }
        if (! $cal instanceof VCalendar) {
            return ['method' => null, 'action' => 'invalid'];
        }
        $method = strtoupper($this->str($cal->METHOD ?? ''));
        $vevent = null;
        foreach ($this->events->parseCalendarStream($ics) as $ev) {
            $vevent = $ev;
            break;
        }
        if (! $vevent instanceof VEvent) {
            return ['method' => $method ?: null, 'action' => 'no_event'];
        }
        $uidValue = trim($this->str($vevent->UID ?? ''));
        if ($uidValue === '') {
            return ['method' => $method ?: null, 'action' => 'no_uid'];
        }
        $sender = $senderEmail !== null ? strtolower(trim($senderEmail)) : null;

        return match ($method) {
            'REPLY' => ['method' => 'REPLY', 'action' => $this->applyReply($uid, $uidValue, $vevent, $sender)],
            'CANCEL' => ['method' => 'CANCEL', 'action' => $this->applyCancel($uid, $uidValue, $vevent, $sender)],
            default => ['method' => $method ?: 'REQUEST', 'action' => $this->applyRequest($uid, $uidValue, $ics, $vevent, $sender)],
        };
    }

    /** Reject when a verified sender does not match the expected iCalendar identity. */
    private function senderMismatch(?string $sender, ?string $expected, string $context): bool
    {
        if ($sender === null) {
            return false; // trusted (user-initiated) path — no external sender to check
        }
        $exp = $expected !== null ? strtolower(trim($expected)) : '';
        if ($exp === '' || $sender !== $exp) {
            Log::warning('iMIP sender mismatch', ['context' => $context, 'sender' => $sender, 'expected' => $exp]);

            return true;
        }

        return false;
    }

    /** REPLY: update the replying attendee's PARTSTAT on the organizer's event. */
    private function applyReply(int $uid, string $uidValue, VEvent $vevent, ?string $sender): string
    {
        $event = $this->findOwnEventByUid($uid, $uidValue);
        if ($event === null) {
            return 'not_found';
        }
        $replyAttendees = $this->events->parseAttendees($vevent);
        // A genuine REPLY carries exactly one ATTENDEE (the responder). Refuse a
        // multi-attendee / zero-attendee payload — it cannot be authenticated.
        if (count($replyAttendees) !== 1) {
            return 'invalid_reply';
        }
        $reply = $replyAttendees[0];
        // The verified sender must be the attendee whose status is changing.
        if ($this->senderMismatch($sender, $reply['email'], 'reply')) {
            return 'sender_mismatch';
        }
        $parsed = $this->events->parse($event->ics);
        $attendees = is_array($parsed['attendees'] ?? null) ? $parsed['attendees'] : [];
        $changed = false;
        foreach ($attendees as &$a) {
            if (is_array($a) && strcasecmp(is_string($a['email'] ?? null) ? $a['email'] : '', $reply['email']) === 0) {
                $a['partstat'] = $reply['partstat'];
                $changed = true;
            }
        }
        unset($a);
        if (! $changed) {
            return 'attendee_unknown';
        }
        $parsed['attendees'] = $attendees;
        $this->writer->update($event, $parsed);

        return 'updated';
    }

    private function applyCancel(int $uid, string $uidValue, VEvent $vevent, ?string $sender): string
    {
        $event = $this->findEventByUid($uid, $uidValue);
        if ($event === null) {
            return 'not_found';
        }
        // Only the stored organizer may cancel — verified against the message sender.
        $organizer = $this->organizerOf($event->ics);
        if ($this->senderMismatch($sender, $organizer, 'cancel')) {
            return 'sender_mismatch';
        }
        $parsed = $this->events->parse($event->ics);
        $parsed['status'] = 'CANCELLED';
        $this->writer->update($event, $parsed);

        return 'cancelled';
    }

    /** REQUEST: create the invitation in the user's first writable calendar (or update). */
    private function applyRequest(int $uid, string $uidValue, string $ics, VEvent $vevent, ?string $sender): string
    {
        $existing = $this->findEventByUid($uid, $uidValue);
        $data = $this->events->parse($this->wrapVevent($vevent));
        // The organizer of record is the sender: for an update, the EXISTING
        // event's organizer (a stranger cannot hijack it); for a new invite, the
        // payload's ORGANIZER.
        $authority = $existing !== null ? $this->organizerOf($existing->ics) : (is_string($data['organizer'] ?? null) ? $data['organizer'] : null);
        if ($this->senderMismatch($sender, $authority, 'request')) {
            return 'sender_mismatch';
        }
        if ($existing !== null) {
            $this->writer->update($existing, $data);

            return 'updated';
        }
        $writable = CalendarAccess::writableIds($uid);
        $calId = $writable[0] ?? null;
        if ($calId === null) {
            return 'no_calendar';
        }
        $calendar = Calendar::query()->withoutGlobalScopes()->find($calId);
        if ($calendar === null) {
            return 'no_calendar';
        }
        $this->writer->create($calendar, $data);

        return 'created';
    }

    private function organizerOf(string $ics): ?string
    {
        $org = $this->events->parse($ics)['organizer'] ?? null;

        return is_string($org) ? $org : null;
    }

    private function wrapVevent(VEvent $vevent): string
    {
        $cal = new VCalendar;
        $cal->add($vevent);
        $s = $cal->serialize();

        return is_string($s) ? $s : '';
    }

    private function findOwnEventByUid(int $uid, string $uidValue): ?CalendarEvent
    {
        return $this->findEventByUid($uid, $uidValue);
    }

    private function findEventByUid(int $uid, string $uidValue): ?CalendarEvent
    {
        $ids = CalendarAccess::writableIds($uid);
        foreach (CalendarEvent::query()->whereIn('calendar_id', $ids)->where('uid', $uidValue)->get() as $e) {
            return $e;
        }

        return null;
    }

    private function deliver(string $to, string $subject, string $body, string $ics, string $method): void
    {
        try {
            Mail::to($to)->send(new ImipMail($subject, $body, $ics, $method));
        } catch (Throwable $e) {
            Log::warning('iMIP send failed', ['to' => $to, 'method' => $method, 'error' => $e->getMessage()]);
        }
    }
}
