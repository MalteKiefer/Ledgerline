<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use App\Models\Album;
use App\Models\AppSettings;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\Contact;
use App\Models\Photo;
use App\Models\PublicShare;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Sabre\VObject\Reader;

/**
 * Public, tokenised read-only links to a calendar or address book for people
 * without an account: an HTML view, an ICS subscription feed and a vCard export.
 * The authenticated half (create/revoke/email) is owner-only.
 */
class PublicShareController extends Controller
{
    private const TYPES = ['calendars' => Calendar::class, 'address-books' => AddressBook::class, 'albums' => Album::class];

    /** Create (or return) the public link for an owned calendar/address book. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'id' => ['required'],
        ]);

        $resource = $this->ownedResource($data['type'], $data['id'], $request->user()->id);
        $share = PublicShare::forResource($resource, $request->user()->id);

        return response()->json(['ok' => true, 'url' => $share->url()], 201);
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
        $link = $publicShare->url();
        try {
            $notifier->mailTo(AppSettings::current(), $to, __('shares.mail_subject', ['user' => $owner]), __('shares.mail_body', ['user' => $owner, 'link' => $link]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    // ---- public (no auth) --------------------------------------------------

    /** Public HTML gallery page for a shared album. */
    public function album(PublicShare $publicShare): View
    {
        $resource = $publicShare->shareable;
        abort_unless($resource instanceof Album, 404);
        $photos = $resource->photos()->get(['photos.id']);

        return view('public-share.album', ['share' => $publicShare, 'album' => $resource, 'photos' => $photos]);
    }

    /** Stream a photo of a shared album (thumb/medium/original), no auth. */
    public function photo(PublicShare $publicShare, Photo $photo, string $size): Response
    {
        $album = $publicShare->shareable;
        abort_unless($album instanceof Album, 404);
        abort_unless($album->photos()->whereKey($photo->id)->exists(), 404);

        $path = match ($size) {
            'thumb' => $photo->thumb_path,
            'medium' => $photo->medium_path,
            default => $photo->disk_path,
        };
        $disk = Storage::disk(config('files.disk'));
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, $photo->name, [
            'Content-Type' => $size === 'original' ? $photo->mime_type : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $size === 'original' ? 'attachment' : 'inline');
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
