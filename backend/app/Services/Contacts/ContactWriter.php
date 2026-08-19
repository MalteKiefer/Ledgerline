<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactVersion;
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
        private readonly ContactReplication $replication,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $groupIds
     */
    public function create(AddressBook $book, array $data, array $groupIds = []): Contact
    {
        $data['categories'] = $this->groupNames($book->user_id, $groupIds);
        $vcard = $this->vcards->build($data);

        $contact = $this->persister->persistNew($book, Str::uuid().'.vcf', $vcard);
        $contact->groups()->sync($this->ownedGroupIds($book->user_id, $groupIds));
        $this->version($book->user_id, $contact, 'created');
        $this->replication->queue($contact);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $groupIds
     */
    public function update(Contact $contact, array $data, array $groupIds = []): Contact
    {
        $book = $contact->addressBook;
        if ($book === null) {
            throw new \RuntimeException('Contact has no address book.');
        }
        // Preserve every section the caller didn't submit: a partial editor
        // payload (the SPA doesn't yet expose addresses/urls/bday/photo/custom
        // fields) must NOT wipe properties set via CardDAV or another client.
        // Present keys — including an empty array meant to clear — stay
        // authoritative; absent keys fall back to the stored card.
        $existing = $this->vcards->parse($contact->vcard);
        $uid = $existing['uid'] ?? null;
        $merged = array_merge($existing, $data);
        $merged['categories'] = $this->groupNames($book->user_id, $groupIds);
        $vcard = $this->vcards->build($merged, is_string($uid) ? $uid : null);

        $this->version($book->user_id, $contact, 'updated');
        $this->persister->persistUpdate($contact, $vcard);
        $contact->groups()->sync($this->ownedGroupIds($book->user_id, $groupIds));
        $this->replication->queue($contact);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $book = $contact->addressBook;
        if ($book === null) {
            $contact->delete();

            return;
        }
        $uri = $contact->uri;
        $this->version($book->user_id, $contact, 'deleted');
        $id = $contact->id;
        $contact->delete();
        $this->changes->record($book, $uri, DavChangeOperation::Deleted);
        $this->replication->queueDeletion($id);
    }

    /**
     * @param  array<int, string>  $groupIds
     * @return list<mixed>
     */
    private function groupNames(int $userId, array $groupIds): array
    {
        return array_values(ContactGroup::where('user_id', $userId)->whereIn('id', $groupIds)->pluck('name')->all());
    }

    /**
     * Only the caller's own group ids — never sync a contact into another user's
     * group via a forged group_id (IDOR on the pivot).
     *
     * @param  array<int, string>  $groupIds
     * @return list<mixed>
     */
    private function ownedGroupIds(int $userId, array $groupIds): array
    {
        return array_values(ContactGroup::where('user_id', $userId)->whereIn('id', $groupIds)->pluck('id')->all());
    }

    private function version(int $userId, Contact $contact, string $action): void
    {
        ContactVersion::query()->create(['user_id' => $userId, 'contact_id' => $contact->id, 'action' => $action, 'vcard' => $contact->vcard]);
    }
}
