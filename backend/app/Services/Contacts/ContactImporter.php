<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Imports a .vcf file (one or many cards) into an address book. Each card is
 * normalised to vCard 4.0, deduped by UID (update in place), and its CATEGORIES
 * become groups. Malformed cards are skipped, not fatal.
 */
class ContactImporter
{
    public function __construct(
        private readonly ContactPersister $persister,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(AddressBook $book, string $vcf): array
    {
        // Suppress per-save side effects during the bulk loop.
        return Contact::withoutEvents(fn (): array => $this->importCards($book, $vcf));
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importCards(AddressBook $book, string $vcf): array
    {
        $created = $updated = $skipped = 0;

        // Natural-key index of existing contacts, so a re-import of cards that
        // carry no UID (or a freshly regenerated one) updates the matching
        // contact instead of creating a duplicate.
        $byKey = [];
        $byFallback = [];
        $byName = [];
        $nameCount = [];
        foreach (Contact::where('address_book_id', $book->id)->get() as $existing) {
            $emails = is_array($existing->emails) ? $existing->emails : [];
            $phones = is_array($existing->phones) ? $existing->phones : [];
            $key = $this->naturalKey($existing->fn, $emails, $phones);
            if ($key !== '' && ! isset($byKey[$key])) {
                $byKey[$key] = $existing;
            }
            // Secondary key for contactless cards (no email/phone): name + org +
            // birthday, so re-importing them still updates rather than dupes.
            $fk = $this->fallbackKey($existing->fn, $existing->org, $existing->bday);
            if ($fk !== '' && ! isset($byFallback[$fk])) {
                $byFallback[$fk] = $existing;
            }
            // Last resort: an unambiguous full name (used only when a card has
            // nothing else to match on AND the name is unique in the book).
            $nm = strtolower(trim(preg_replace('/\s+/', ' ', (string) $existing->fn) ?? ''));
            if ($nm !== '') {
                $nameCount[$nm] = ($nameCount[$nm] ?? 0) + 1;
                $byName[$nm] = $existing;
            }
        }

        // Reader::readAll yields each VCARD in a multi-card document.
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }
        try {
            fwrite($stream, $vcf);
            rewind($stream);
            $splitter = new \Sabre\VObject\Splitter\VCard($stream);
        } catch (Throwable) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        while (true) {
            try {
                $card = $splitter->getNext();
            } catch (Throwable) {
                $skipped++;

                continue;
            }
            if ($card === null) {
                break;
            }
            if (! $card instanceof VCard) {
                $skipped++;

                continue;
            }

            try {
                $rawUid = $card->UID ?? null;
                $uid = is_scalar($rawUid) || $rawUid instanceof \Stringable ? trim((string) $rawUid) : '';

                // Match an existing contact: first by UID, then — when the card
                // has no UID or the UID is new — by natural key (name + contacts).
                $existing = $uid !== ''
                    ? Contact::where('address_book_id', $book->id)->where('uid', $uid)->first()
                    : null;

                $key = $this->naturalKey(
                    $this->s($card->FN ?? null),
                    $this->partValues($card->select('EMAIL')),
                    $this->partValues($card->select('TEL')),
                );
                if ($existing === null && $key !== '' && isset($byKey[$key])) {
                    $existing = $byKey[$key];
                }
                // No email/phone to match on → fall back to name + org + birthday.
                $fallback = '';
                if ($existing === null && $key === '') {
                    $fallback = $this->fallbackKey(
                        $this->s($card->FN ?? null),
                        $this->s($card->ORG ?? null),
                        $this->s($card->BDAY ?? null),
                    );
                    if ($fallback !== '' && isset($byFallback[$fallback])) {
                        $existing = $byFallback[$fallback];
                    } elseif ($fallback === '') {
                        // Nothing distinctive at all: match a unique existing name.
                        $nm = strtolower(trim(preg_replace('/\s+/', ' ', (string) $this->s($card->FN ?? null)) ?? ''));
                        if ($nm !== '' && ($nameCount[$nm] ?? 0) === 1 && isset($byName[$nm])) {
                            $existing = $byName[$nm];
                        }
                    }
                }

                // Reuse the matched contact's UID so its vCard identity stays
                // stable; otherwise mint one for a genuinely new card.
                if ($existing !== null) {
                    $uid = (string) $existing->uid;
                } elseif ($uid === '') {
                    $uid = (string) Str::uuid();
                }
                $card->remove('VERSION');
                $card->add('VERSION', '4.0');
                $card->remove('UID');
                $card->add('UID', $uid);
                $serialized = $card->serialize();
                $vcard = is_string($serialized) ? $serialized : '';

                if ($existing !== null) {
                    $this->persister->persistUpdate($existing, $vcard);
                    $this->syncGroups($existing, $card, $book->user_id);
                    $updated++;
                } else {
                    $contact = $this->persister->persistNew($book, Str::uuid().'.vcf', $vcard);
                    $this->syncGroups($contact, $card, $book->user_id);
                    if ($key !== '') {
                        $byKey[$key] = $contact;
                    } elseif ($fallback !== '') {
                        $byFallback[$fallback] = $contact;
                    }
                    $nm = strtolower(trim(preg_replace('/\s+/', ' ', (string) $this->s($card->FN ?? null)) ?? ''));
                    if ($nm !== '') {
                        $nameCount[$nm] = ($nameCount[$nm] ?? 0) + 1;
                        $byName[$nm] = $contact;
                    }
                    $created++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * A stable dedup key from name + contact points. Uses email (case-folded)
     * and the last 8 phone digits (format-agnostic); empty when there's nothing
     * distinctive to match on (never dedupes on a bare name alone).
     *
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     */
    private function naturalKey(?string $fn, array $emails, array $phones): string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $fn) ?? ''));
        $mails = array_values(array_unique(array_filter(array_map(
            fn (string $e): string => strtolower(trim($e)),
            $emails,
        ))));
        sort($mails);
        $tels = array_values(array_unique(array_filter(array_map(
            function (string $p): string {
                $d = preg_replace('/\D/', '', $p) ?? '';

                return strlen($d) >= 6 ? substr($d, -8) : '';
            },
            $phones,
        ))));
        sort($tels);

        if ($mails === [] && $tels === []) {
            return '';
        }

        return $name.'|'.implode(',', $mails).'|'.implode(',', $tels);
    }

    /**
     * Secondary dedup key for a card with no email/phone: name + org + the
     * year-agnostic MM-DD birthday. Empty unless a birthday OR org distinguishes
     * it, so we never merge two same-named contactless people on the name alone.
     */
    private function fallbackKey(?string $fn, ?string $org, ?string $bday): string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $fn) ?? ''));
        // Normalise ORG the same way the denormalised column is built (drop the
        // trailing structured-empty ";" parts) so raw-card vs stored match.
        $oParts = array_filter(array_map('trim', explode(';', (string) $org)));
        $o = strtolower(implode(' · ', $oParts));
        $mmdd = $this->birthMonthDay((string) $bday);
        if ($name === '' || ($o === '' && $mmdd === '')) {
            return '';
        }

        return 'fb|'.$name.'|'.$o.'|'.$mmdd;
    }

    /** Reduce any BDAY form (YYYYMMDD, YYYY-MM-DD, --MMDD, MM-DD) to "MM-DD". */
    private function birthMonthDay(string $bday): string
    {
        $digits = preg_replace('/\D/', '', $bday) ?? '';
        if (str_starts_with(ltrim($bday), '--') || strlen($digits) === 4) {
            $mmdd = substr($digits, 0, 4);
        } elseif (strlen($digits) >= 8) {
            $mmdd = substr($digits, 4, 4);
        } else {
            return '';
        }

        return strlen($mmdd) === 4 ? substr($mmdd, 0, 2).'-'.substr($mmdd, 2, 2) : '';
    }

    private function s(mixed $value): ?string
    {
        $v = is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';

        return $v !== '' ? $v : null;
    }

    /**
     * The string values of every instance of a vCard property (from select()).
     *
     * @param  array<array-key, mixed>  $props
     * @return list<string>
     */
    private function partValues(array $props): array
    {
        $out = [];
        foreach ($props as $item) {
            if ($item instanceof Property) {
                $val = trim((string) $item);
                if ($val !== '') {
                    $out[] = $val;
                }
            }
        }

        return $out;
    }

    private function syncGroups(Contact $contact, VCard $card, int $userId): void
    {
        $categories = $card->CATEGORIES ?? null;
        if (! $categories instanceof Property) {
            return;
        }
        $ids = [];
        $parts = $categories->getParts();
        foreach (is_iterable($parts) ? $parts : [] as $name) {
            $name = trim(is_scalar($name) ? (string) $name : '');
            if ($name !== '') {
                $ids[] = ContactGroup::firstOrCreate(['user_id' => $userId, 'name' => $name])->id;
            }
        }
        $contact->groups()->sync($ids);
    }
}
