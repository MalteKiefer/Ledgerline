<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes contacts from the web UI: builds the vCard, mirrors group names into
 * CATEGORIES, keeps the denormalised columns and group pivot in sync, and bumps
 * the address book's DAV sync token + change log so CardDAV clients see edits.
 */
class ContactWriter
{
    public function __construct(private readonly VCardService $vcards) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $groupIds
     */
    public function create(AddressBook $book, array $data, array $groupIds = []): Contact
    {
        $data['categories'] = $this->groupNames($book->user_id, $groupIds);
        $vcard = $this->vcards->build($data);
        $uri = Str::uuid().'.vcf';

        $contact = Contact::create(array_merge([
            'address_book_id' => $book->id,
            'uri' => $uri,
            'etag' => md5($vcard),
            'vcard' => $vcard,
        ], $this->vcards->denormalize($vcard)));

        $contact->groups()->sync($groupIds);
        $this->logChange($book, $uri, 1);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $groupIds
     */
    public function update(Contact $contact, array $data, array $groupIds = []): Contact
    {
        $book = $contact->addressBook;
        $data['categories'] = $this->groupNames($book->user_id, $groupIds);
        $existing = $this->vcards->parse($contact->vcard);
        $vcard = $this->vcards->build($data, $existing['uid'] ?? null);

        $contact->forceFill(array_merge(['etag' => md5($vcard), 'vcard' => $vcard], $this->vcards->denormalize($vcard)))->save();
        $contact->groups()->sync($groupIds);
        $this->logChange($book, $contact->uri, 2);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $book = $contact->addressBook;
        $uri = $contact->uri;
        $contact->delete();
        $this->logChange($book, $uri, 3);
    }

    /**
     * @param  list<string>  $groupIds
     * @return list<string>
     */
    private function groupNames(int $userId, array $groupIds): array
    {
        return ContactGroup::where('user_id', $userId)->whereIn('id', $groupIds)->pluck('name')->all();
    }

    private function logChange(AddressBook $book, string $uri, int $operation): void
    {
        $token = (int) $book->synctoken + 1;
        $book->forceFill(['synctoken' => $token])->save();

        DB::table('dav_changes')->insert([
            'address_book_id' => $book->id,
            'uri' => $uri,
            'operation' => $operation,
            'synctoken' => $token,
        ]);
    }
}
