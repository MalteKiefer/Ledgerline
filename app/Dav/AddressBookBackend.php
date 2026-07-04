<?php

declare(strict_types=1);

namespace App\Dav;

use App\Dav\Concerns\ResolvesResourceShares;
use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\DavCredential;
use App\Models\ResourceShare;
use App\Services\Contacts\ContactPersister;
use App\Services\Contacts\DavChangeLog;
use Illuminate\Support\Facades\DB;
use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\DAV\PropPatch;

/**
 * CardDAV storage backed by Eloquent. Cards keep their raw vCard; write
 * operations bump the address book's sync token and append a change row so
 * clients can sync incrementally.
 */
class AddressBookBackend extends AbstractBackend implements SyncSupport
{
    use ResolvesResourceShares;

    public function __construct(
        private readonly DavContext $context,
        private readonly DavChangeLog $changes,
        private readonly ContactPersister $persister,
    ) {}

    /** The principal may see this book (owns it or it's shared with them). */
    private function ownsBook(string $addressBookId): bool
    {
        $userId = $this->context->userId();
        if ($userId === null) {
            return false;
        }
        if (AddressBook::where('id', $addressBookId)->where('user_id', $userId)->exists()) {
            return true;
        }

        return $this->shareLevel(AddressBook::class, $addressBookId, $userId) !== null;
    }

    /** The principal may write cards in this book (owner or write-share). */
    private function canWriteBook(string $addressBookId): bool
    {
        $userId = $this->context->userId();
        if ($userId === null) {
            return false;
        }
        if (AddressBook::where('id', $addressBookId)->where('user_id', $userId)->exists()) {
            return true;
        }

        return $this->shareLevel(AddressBook::class, $addressBookId, $userId) === ResourceShare::WRITE;
    }

    /** Only the owner may rename/delete the book collection itself. */
    private function ownsBookCollection(string $addressBookId): bool
    {
        $userId = $this->context->userId();

        return $userId !== null && AddressBook::where('id', $addressBookId)->where('user_id', $userId)->exists();
    }

    public function getAddressBooksForUser($principalUri): array
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return [];
        }

        $rows = AddressBook::where('user_id', $userId)->get()
            ->map(fn (AddressBook $b): array => $this->bookRow($b, $principalUri, $b->uri))->all();

        // Address books other users shared with this principal.
        $sharedIds = ResourceShare::query()
            ->where('shareable_type', (new AddressBook)->getMorphClass())
            ->where('shared_with_user_id', $userId)
            ->pluck('shareable_id');
        foreach (AddressBook::whereIn('id', $sharedIds)->get() as $b) {
            $rows[] = $this->bookRow($b, $principalUri, 'shared-'.$b->id, ' ('.__('calendar.ui.shared').')');
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function bookRow(AddressBook $b, string $principalUri, string $uri, string $nameSuffix = ''): array
    {
        return [
            'id' => $b->id,
            'uri' => $uri,
            'principaluri' => $principalUri,
            '{DAV:}displayname' => $b->name.$nameSuffix,
            '{urn:ietf:params:xml:ns:carddav}addressbook-description' => (string) $b->description,
            '{http://sabredav.org/ns}sync-token' => (string) $b->synctoken,
        ];
    }

    public function updateAddressBook($addressBookId, PropPatch $propPatch): void
    {
        if (! $this->ownsBookCollection($addressBookId)) {
            return;
        }
        $book = AddressBook::find($addressBookId);
        if ($book === null) {
            return;
        }

        $propPatch->handle(['{DAV:}displayname', '{urn:ietf:params:xml:ns:carddav}addressbook-description'],
            function (array $mutations) use ($book): bool {
                if (isset($mutations['{DAV:}displayname'])) {
                    $book->name = (string) $mutations['{DAV:}displayname'];
                }
                if (isset($mutations['{urn:ietf:params:xml:ns:carddav}addressbook-description'])) {
                    $book->description = (string) $mutations['{urn:ietf:params:xml:ns:carddav}addressbook-description'];
                }
                $book->save();

                return true;
            });
    }

    public function createAddressBook($principalUri, $url, array $properties): void
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return;
        }

        AddressBook::create([
            'user_id' => $userId,
            'uri' => $url,
            'name' => (string) ($properties['{DAV:}displayname'] ?? $url),
            'description' => $properties['{urn:ietf:params:xml:ns:carddav}addressbook-description'] ?? null,
            'synctoken' => 1,
        ]);
    }

    public function deleteAddressBook($addressBookId): void
    {
        if (! $this->ownsBookCollection($addressBookId)) {
            return;
        }
        AddressBook::where('id', $addressBookId)->delete();
    }

    public function getCards($addressbookId): array
    {
        if (! $this->ownsBook($addressbookId)) {
            return [];
        }

        return Contact::where('address_book_id', $addressbookId)->get()->map(fn (Contact $c): array => [
            'id' => $c->id,
            'uri' => $c->uri,
            'lastmodified' => $c->updated_at?->getTimestamp(),
            'etag' => '"'.$c->etag.'"',
            'size' => strlen($c->vcard),
        ])->all();
    }

    public function getCard($addressBookId, $cardUri): array|false
    {
        if (! $this->ownsBook($addressBookId)) {
            return false;
        }
        $contact = Contact::where('address_book_id', $addressBookId)->where('uri', $cardUri)->first();
        if ($contact === null) {
            return false;
        }

        return [
            'id' => $contact->id,
            'uri' => $contact->uri,
            'carddata' => $contact->vcard,
            'lastmodified' => $contact->updated_at?->getTimestamp(),
            'etag' => '"'.$contact->etag.'"',
            'size' => strlen($contact->vcard),
        ];
    }

    public function createCard($addressBookId, $cardUri, $cardData): ?string
    {
        if (! $this->canWriteBook($addressBookId)) {
            return null;
        }
        $book = AddressBook::find($addressBookId);
        if ($book === null) {
            return null;
        }
        $this->persister->persistNew($book, $cardUri, $cardData);

        return '"'.md5($cardData).'"';
    }

    public function updateCard($addressBookId, $cardUri, $cardData): ?string
    {
        if (! $this->canWriteBook($addressBookId)) {
            return null;
        }
        $contact = Contact::where('address_book_id', $addressBookId)->where('uri', $cardUri)->first();
        if ($contact === null) {
            return null;
        }

        $this->persister->persistUpdate($contact, $cardData);

        return '"'.md5($cardData).'"';
    }

    public function deleteCard($addressBookId, $cardUri): bool
    {
        if (! $this->canWriteBook($addressBookId)) {
            return false;
        }
        $deleted = Contact::where('address_book_id', $addressBookId)->where('uri', $cardUri)->delete();
        if ($deleted) {
            $this->logChange($addressBookId, $cardUri, DavChangeOperation::Deleted);
        }

        return $deleted > 0;
    }

    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null): ?array
    {
        if (! $this->ownsBook($addressBookId)) {
            return null;
        }
        $book = AddressBook::find($addressBookId);
        if ($book === null) {
            return null;
        }

        $current = (int) $book->synctoken;

        if ($syncToken === null || $syncToken === '') {
            // Initial sync: every current card is "added".
            return [
                'syncToken' => (string) $current,
                'added' => Contact::where('address_book_id', $addressBookId)->pluck('uri')->all(),
                'modified' => [],
                'deleted' => [],
            ];
        }

        // Stale/foreign or pruned-away token → null so Sabre triggers a full
        // resync (RFC 6578 valid-sync-token).
        if (! ctype_digit((string) $syncToken) || (int) $syncToken > $current) {
            return null;
        }
        $oldestKept = DB::table('dav_changes')->where('address_book_id', $addressBookId)->min('synctoken');
        if ($oldestKept !== null && (int) $syncToken < (int) $oldestKept && (int) $syncToken < $current) {
            return null;
        }

        $rows = DB::table('dav_changes')
            ->where('address_book_id', $addressBookId)
            ->where('synctoken', '>=', (int) $syncToken)
            ->orderBy('synctoken')
            ->when($limit, fn ($q) => $q->limit((int) $limit))
            ->get(['uri', 'operation']);

        // Latest operation per uri wins.
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->uri] = $row->operation;
        }

        $result = ['syncToken' => (string) $current, 'added' => [], 'modified' => [], 'deleted' => []];
        foreach ($latest as $uri => $op) {
            $result[match (DavChangeOperation::from((int) $op)) {
                DavChangeOperation::Added => 'added',
                DavChangeOperation::Modified => 'modified',
                DavChangeOperation::Deleted => 'deleted',
            }][] = $uri;
        }

        return $result;
    }

    private function logChange(string $addressBookId, string $uri, DavChangeOperation $op): void
    {
        $book = AddressBook::find($addressBookId);
        if ($book !== null) {
            $this->changes->record($book, $uri, $op);
        }
    }

    private function userId(string $principalUri): ?int
    {
        $username = basename($principalUri);

        return DavCredential::where('username', $username)->value('user_id');
    }
}
