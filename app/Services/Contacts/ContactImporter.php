<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Services\Calendar\ContactDerivedCalendars;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCard;
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
        private readonly ContactDerivedCalendars $derived,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(AddressBook $book, string $vcf): array
    {
        // Suppress the per-save derived-calendar observer during the bulk loop and
        // rebuild that user's calendars once at the end (avoids O(N^2)).
        $result = Contact::withoutEvents(fn (): array => $this->importCards($book, $vcf));
        $this->derived->sync($book->user_id);

        return $result;
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importCards(AddressBook $book, string $vcf): array
    {
        $created = $updated = $skipped = 0;

        // Reader::readAll yields each VCARD in a multi-card document.
        try {
            $stream = fopen('php://temp', 'r+');
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
                $card->VERSION = '4.0';
                $uid = isset($card->UID) ? (string) $card->UID : (string) Str::uuid();
                $card->UID = $uid;
                $vcard = $card->serialize();

                $existing = Contact::where('address_book_id', $book->id)
                    ->where('uid', $uid)->first();

                if ($existing !== null) {
                    $this->persister->persistUpdate($existing, $vcard);
                    $this->syncGroups($existing, $card, $book->user_id);
                    $updated++;
                } else {
                    $contact = $this->persister->persistNew($book, Str::uuid().'.vcf', $vcard);
                    $this->syncGroups($contact, $card, $book->user_id);
                    $created++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function syncGroups(Contact $contact, VCard $card, int $userId): void
    {
        if (! isset($card->CATEGORIES)) {
            return;
        }
        $ids = [];
        foreach ($card->CATEGORIES->getParts() as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $ids[] = ContactGroup::firstOrCreate(['user_id' => $userId, 'name' => $name])->id;
            }
        }
        $contact->groups()->sync($ids);
    }
}
