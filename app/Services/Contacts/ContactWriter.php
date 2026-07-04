<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Support\Str;

/**
 * Writes contacts from the web UI: builds the vCard, mirrors group names into
 * CATEGORIES, keeps the denormalised columns and group pivot in sync, and bumps
 * the address book's DAV sync token + change log so CardDAV clients see edits.
 */
class ContactWriter
{
    public function __construct(
        private readonly VCardService $vcards,
        private readonly DavChangeLog $changes,
    ) {}

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

        $contact->groups()->sync($this->ownedGroupIds($book->user_id, $groupIds));
        $this->changes->record($book, $uri, DavChangeOperation::Added);

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
        $contact->groups()->sync($this->ownedGroupIds($book->user_id, $groupIds));
        $this->changes->record($book, $contact->uri, DavChangeOperation::Modified);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $book = $contact->addressBook;
        $uri = $contact->uri;
        $contact->delete();
        $this->changes->record($book, $uri, DavChangeOperation::Deleted);
    }

    /**
     * @param  list<string>  $groupIds
     * @return list<string>
     */
    private function groupNames(int $userId, array $groupIds): array
    {
        return ContactGroup::where('user_id', $userId)->whereIn('id', $groupIds)->pluck('name')->all();
    }

    /**
     * Only the caller's own group ids — never sync a contact into another user's
     * group via a forged group_id (IDOR on the pivot).
     *
     * @param  list<string>  $groupIds
     * @return list<string>
     */
    private function ownedGroupIds(int $userId, array $groupIds): array
    {
        return ContactGroup::where('user_id', $userId)->whereIn('id', $groupIds)->pluck('id')->all();
    }
}
