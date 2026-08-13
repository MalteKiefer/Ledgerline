<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\Contact;

/**
 * Persists a contact's vCard consistently: the etag + denormalised columns are
 * derived the same way everywhere, and the matching DAV change is logged. Shared
 * by the web writer, the importer and the CardDAV backend so persistence and the
 * sync-collection log never drift apart.
 */
class ContactPersister
{
    public function __construct(
        private readonly VCardService $vcards,
        private readonly DavChangeLog $changes,
    ) {}

    public function persistNew(AddressBook $book, string $uri, string $vcard): Contact
    {
        $contact = Contact::create(array_merge([
            'address_book_id' => $book->id,
            'uri' => $uri,
            'etag' => md5($vcard),
            'vcard' => $vcard,
        ], $this->vcards->denormalize($vcard)));

        $this->changes->record($book, $uri, DavChangeOperation::Added);

        return $contact;
    }

    public function persistUpdate(Contact $contact, string $vcard): Contact
    {
        $contact->forceFill(array_merge(
            ['etag' => md5($vcard), 'vcard' => $vcard],
            $this->vcards->denormalize($vcard),
        ))->save();

        // Explicit relation query (never an implicit lazy load, which is
        // disabled app-wide and would throw during a bulk import).
        $book = $contact->addressBook()->first();
        if ($book !== null) {
            $this->changes->record($book, $contact->uri, DavChangeOperation::Modified);
        }

        return $contact;
    }
}
