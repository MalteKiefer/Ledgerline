<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactDuplicateDismissal;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-user data contributor for the contacts/CardDAV module: exports and erases
 * a user's address books, contact cards and groups. Address books and groups
 * own their data via the `user_id` column; contacts hang off an address book by
 * `address_book_id` and are scoped transitively through it. The vCard 4.0 is the
 * authoritative payload and any PHOTO/avatar is embedded inline in that vcard
 * text, so there are no separate file blobs to move or delete.
 */
final class ContactsData implements UserDataContributor
{
    public function key(): string
    {
        return 'contacts';
    }

    public function export(User $user): array
    {
        $userId = $user->getKey();

        $addressBooks = AddressBook::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (AddressBook $book): array => $book->attributesToArray())
            ->all();

        $addressBookIds = array_column($addressBooks, 'id');

        $contacts = Contact::query()
            ->withoutGlobalScopes()
            ->whereIn('address_book_id', $addressBookIds)
            ->orderBy('id')
            ->get()
            ->map(function (Contact $contact): array {
                // attributesToArray() already includes the authoritative vcard
                // (with any embedded PHOTO) plus the denormalised key fields.
                $row = $contact->attributesToArray();
                $row['groups'] = $contact->groups()
                    ->withoutGlobalScopes()
                    ->pluck('contact_groups.id')
                    ->all();

                return $row;
            })
            ->all();

        $groups = ContactGroup::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (ContactGroup $group): array => $group->attributesToArray())
            ->all();

        return [
            'address_books' => $addressBooks,
            'contacts' => $contacts,
            'groups' => $groups,
        ];
    }

    public function purge(User $user): void
    {
        $userId = $user->getKey();

        $addressBookIds = AddressBook::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->pluck('id');

        // Contacts (and their contact_group pivot rows) come first: they sit
        // below address books in FK terms. Both the pivot and the DAV change log
        // cascade on delete at the DB level, but we clear them explicitly so the
        // erasure does not lean on cascade behaviour.
        if ($addressBookIds->isNotEmpty()) {
            $contactIds = Contact::query()
                ->withoutGlobalScopes()
                ->whereIn('address_book_id', $addressBookIds)
                ->pluck('id');

            if ($contactIds->isNotEmpty()) {
                DB::table('contact_group')->whereIn('contact_id', $contactIds)->delete();
            }

            DB::table('dav_changes')->whereIn('address_book_id', $addressBookIds)->delete();

            Contact::query()
                ->withoutGlobalScopes()
                ->whereIn('address_book_id', $addressBookIds)
                ->delete();

            AddressBook::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $addressBookIds)
                ->delete();
        }

        // Groups are user-level and independent of address books; their pivot
        // rows are already gone with the contacts above.
        $groupIds = ContactGroup::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->pluck('id');

        if ($groupIds->isNotEmpty()) {
            DB::table('contact_group')->whereIn('group_id', $groupIds)->delete();

            ContactGroup::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $groupIds)
                ->delete();
        }

        // Duplicate-review dismissals are per-user contacts-module state.
        ContactDuplicateDismissal::query()
            ->where('user_id', $userId)
            ->delete();
    }
}
