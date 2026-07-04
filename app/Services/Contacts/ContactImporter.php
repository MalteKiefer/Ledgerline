<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private readonly VCardService $vcards) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(AddressBook $book, string $vcf): array
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
                $denorm = $this->vcards->denormalize($vcard);

                $existing = Contact::where('address_book_id', $book->id)
                    ->whereRaw('vcard LIKE ?', ['%UID:'.$uid.'%'])->first();

                if ($existing !== null) {
                    $existing->forceFill(array_merge(['etag' => md5($vcard), 'vcard' => $vcard], $denorm))->save();
                    $this->syncGroups($existing, $card, $book->user_id);
                    $this->logChange($book, $existing->uri, 2);
                    $updated++;
                } else {
                    $uri = Str::uuid().'.vcf';
                    $contact = Contact::create(array_merge([
                        'address_book_id' => $book->id, 'uri' => $uri, 'etag' => md5($vcard), 'vcard' => $vcard,
                    ], $denorm));
                    $this->syncGroups($contact, $card, $book->user_id);
                    $this->logChange($book, $uri, 1);
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

    private function logChange(AddressBook $book, string $uri, int $operation): void
    {
        $token = (int) $book->synctoken + 1;
        $book->forceFill(['synctoken' => $token])->save();
        DB::table('dav_changes')->insert([
            'address_book_id' => $book->id, 'uri' => $uri, 'operation' => $operation, 'synctoken' => $token,
        ]);
    }
}
