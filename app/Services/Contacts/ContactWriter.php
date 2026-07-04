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
        private readonly ContactPersister $persister,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $groupIds
     */
    public function create(AddressBook $book, array $data, array $groupIds = []): Contact
    {
        $data['categories'] = $this->groupNames($book->user_id, $groupIds);
        $vcard = $this->vcards->build($data);

        $contact = $this->persister->persistNew($book, Str::uuid().'.vcf', $vcard);
        $contact->groups()->sync($this->ownedGroupIds($book->user_id, $groupIds));

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

        $this->persister->persistUpdate($contact, $vcard);
        $contact->groups()->sync($this->ownedGroupIds($book->user_id, $groupIds));

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
