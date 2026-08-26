<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\UserSetting;
use App\Services\Contacts\VCardService;
use App\Support\BrandIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * A picture for a mail sender, resolved in order of how much it costs you.
 *
 * 1. The address book. Your own data, no request leaves the machine, and it is
 *    the only source that can show an actual person rather than a company mark.
 * 2. The sender's domain — BIMI first (the standard by which a company
 *    publishes a logo *for mail*, in DNS, by the domain owner), then favicons.
 *    This tells a favicon service which domains write to you, never which
 *    address, and only for domains not already answered from step 1.
 *
 * Gravatar and Libravatar are deliberately NOT in the ladder. They are keyed by
 * a hash of the address itself, so asking them is telling them "this exact
 * mailbox exists and someone is reading its mail" — for every correspondent, on
 * every page of the list. The domain is a far smaller thing to reveal for the
 * same benefit. Libravatar can be self-hosted and would be defensible then;
 * that is a decision with its own switch, not something to slip in here.
 *
 * Which rungs are used is a per-user setting, and the default is the one that
 * sends nothing.
 *
 * Answers in batches because a page of the list asks about fifty senders at
 * once; fifty requests for fifty little pictures would be its own problem.
 */
class MailAvatarController extends Controller
{
    /** A page of the list, plus room for a long thread. */
    private const MAX_ADDRESSES = 60;

    /** Long: a company logo changes about never, and a miss is worth remembering too. */
    private const TTL_DAYS = 14;

    public function __invoke(Request $request, VCardService $vcards): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'emails' => ['required', 'array', 'max:'.self::MAX_ADDRESSES],
            'emails.*' => ['string', 'max:320'],
        ]);

        $mode = UserSetting::for((int) $user->id)->mail_avatars ?? 'contacts';
        if ($mode === 'off') {
            return response()->json(['avatars' => []]);
        }

        /** @var list<string> $emails */
        $emails = array_values(array_unique(array_filter(
            array_map(
                fn (mixed $e): string => is_string($e) ? strtolower(trim($e)) : '',
                (array) $request->input('emails'),
            ),
            fn (string $e): bool => $e !== '' && str_contains($e, '@'),
        )));

        $out = [];
        foreach ($emails as $email) {
            $icon = $this->resolve((int) $user->id, $email, $mode, $vcards);
            if ($icon !== null) {
                $out[$email] = $icon;
            }
        }

        return response()->json(['avatars' => $out])->header('Cache-Control', 'private, max-age=3600');
    }

    private function resolve(int $userId, string $email, string $mode, VCardService $vcards): ?string
    {
        // Cached per user, because step 1 reads that user's address book.
        return Cache::remember(
            'mail.avatar:'.$userId.':'.sha1($email.'|'.$mode),
            now()->addDays(self::TTL_DAYS),
            function () use ($userId, $email, $mode, $vcards): ?string {
                $photo = $this->fromAddressBook($userId, $email, $vcards);
                if ($photo !== null) {
                    return $photo;
                }

                if ($mode !== 'domain') {
                    return null;
                }

                $domain = substr($email, strrpos($email, '@') + 1);

                return BrandIcon::forDomain($domain);
            },
        );
    }

    /**
     * A photo from the owner's own address book, matched on the address.
     *
     * Owner-scoped through the address book relation: a contact belongs to a
     * book, and a book to a user.
     */
    private function fromAddressBook(int $userId, string $email, VCardService $vcards): ?string
    {
        // `emails` is a JSON column, so the LIKE is a coarse pre-filter that the
        // database can run — the exact match happens in PHP below. Matching on
        // the raw JSON alone would also hit a partial address.
        $candidates = Contact::query()
            ->withoutGlobalScopes()
            ->whereHas('addressBook', fn ($q) => $q->where('user_id', $userId))
            ->where('has_photo', true)
            ->whereRaw('lower(cast(emails as text)) like ?', ['%'.str_replace(['%', '_'], ['\%', '\_'], $email).'%'])
            ->limit(5)
            ->get(['id', 'emails', 'vcard']);

        foreach ($candidates as $contact) {
            // The model declares no property types, so the cast result arrives
            // as mixed and is narrowed here rather than trusted.
            $raw = $contact->getAttribute('emails');
            /** @var list<mixed> $emails */
            $emails = is_array($raw) ? array_values($raw) : [];
            $hit = false;
            foreach ($emails as $entry) {
                // An entry is either a bare address or {value|email: …}.
                $value = '';
                if (is_array($entry)) {
                    $inner = $entry['value'] ?? $entry['email'] ?? null;
                    $value = is_scalar($inner) ? (string) $inner : '';
                } elseif (is_scalar($entry)) {
                    $value = (string) $entry;
                }
                if (strtolower(trim($value)) === $email) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                continue;
            }

            // The photo lives in the vCard; the parser is the one place that
            // knows how to get it out of either encoding.
            $parsed = $vcards->parse((string) $contact->vcard);
            $photo = $parsed['photo'] ?? null;
            if (is_string($photo) && str_starts_with($photo, 'data:image/')) {
                return $photo;
            }
        }

        return null;
    }
}
