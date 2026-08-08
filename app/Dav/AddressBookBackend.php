<?php

declare(strict_types=1);

namespace App\Dav;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Services\Contacts\ContactPersister;
use App\Services\Contacts\DavChangeLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\DAV\PropPatch;

/**
 * CardDAV storage backed by Eloquent. Cards keep their raw vCard; write
 * operations bump the address book's sync token and append a change row so
 * clients can sync incrementally.
 *
 * Every operation is owner-scoped to the request's authenticated user (set by
 * WebDavAuth via Auth::login), defence-in-depth on top of the DAVACL plugin:
 * the user may only reach their own books plus those explicitly shared with them.
 */
class AddressBookBackend extends AbstractBackend implements SyncSupport
{
    public function __construct(
        private readonly DavChangeLog $changes,
        private readonly ContactPersister $persister,
    ) {}

    /** The authenticated user id, or null when unauthenticated. */
    private function currentUserId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    /** The principal may see this book (owner-only in Phase 1; sharing is Phase 3). */
    private function ownsBook(string $addressBookId): bool
    {
        return $this->ownsBookCollection($addressBookId);
    }

    /** The principal may write cards in this book (owner-only in Phase 1). */
    private function canWriteBook(string $addressBookId): bool
    {
        return $this->ownsBookCollection($addressBookId);
    }

    /** Only the owner may rename/delete the book collection itself. */
    private function ownsBookCollection(string $addressBookId): bool
    {
        $userId = $this->currentUserId();

        return $userId !== null && AddressBook::query()->ownedBy($userId)->whereKey($addressBookId)->exists();
    }

    /**
     * @param  string  $principalUri
     * @return array<int, array<string, mixed>>
     */
    public function getAddressBooksForUser($principalUri): array
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return [];
        }

        return AddressBook::query()->ownedBy($userId)->get()
            ->map(fn (AddressBook $b): array => $this->bookRow($b, $principalUri, $b->uri))->all();
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

    /** @param  string  $addressBookId */
    public function updateAddressBook($addressBookId, PropPatch $propPatch): void
    {
        if (! $this->ownsBookCollection($addressBookId)) {
            return;
        }
        $book = AddressBook::query()->withoutGlobalScopes()->find($addressBookId);
        if ($book === null) {
            return;
        }

        $propPatch->handle(['{DAV:}displayname', '{urn:ietf:params:xml:ns:carddav}addressbook-description'],
            function (array $mutations) use ($book): bool {
                $dn = $mutations['{DAV:}displayname'] ?? null;
                if ($dn !== null) {
                    $book->name = is_scalar($dn) ? (string) $dn : '';
                }
                $desc = $mutations['{urn:ietf:params:xml:ns:carddav}addressbook-description'] ?? null;
                if ($desc !== null) {
                    $book->description = is_scalar($desc) ? (string) $desc : '';
                }
                $book->save();

                return true;
            });
    }

    /**
     * @param  string  $principalUri
     * @param  string  $url
     * @param  array<string, mixed>  $properties
     */
    public function createAddressBook($principalUri, $url, array $properties): void
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return;
        }

        $dn = $properties['{DAV:}displayname'] ?? null;
        $desc = $properties['{urn:ietf:params:xml:ns:carddav}addressbook-description'] ?? null;
        AddressBook::create([
            'user_id' => $userId,
            'uri' => $url,
            'name' => is_scalar($dn) ? (string) $dn : $url,
            'description' => is_scalar($desc) ? (string) $desc : null,
            'synctoken' => 1,
        ]);
    }

    /** @param  string  $addressBookId */
    public function deleteAddressBook($addressBookId): void
    {
        if (! $this->ownsBookCollection($addressBookId)) {
            return;
        }
        AddressBook::query()->withoutGlobalScopes()->whereKey($addressBookId)->delete();
    }

    /**
     * @param  string  $addressbookId
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @param  string  $addressBookId
     * @param  string  $cardUri
     * @return array<string, mixed>|false
     */
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

    /**
     * @param  string  $addressBookId
     * @param  string  $cardUri
     * @param  string  $cardData
     */
    public function createCard($addressBookId, $cardUri, $cardData): ?string
    {
        if (! $this->canWriteBook($addressBookId)) {
            return null;
        }
        $book = AddressBook::query()->withoutGlobalScopes()->find($addressBookId);
        if ($book === null) {
            return null;
        }
        $this->persister->persistNew($book, $cardUri, $cardData);

        return '"'.md5($cardData).'"';
    }

    /**
     * @param  string  $addressBookId
     * @param  string  $cardUri
     * @param  string  $cardData
     */
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

    /**
     * @param  string  $addressBookId
     * @param  string  $cardUri
     */
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

    /**
     * @param  string  $addressBookId
     * @return array<string, mixed>|null
     */
    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null): ?array
    {
        if (! $this->ownsBook($addressBookId)) {
            return null;
        }
        $book = AddressBook::query()->withoutGlobalScopes()->find($addressBookId);
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
        if (is_numeric($oldestKept) && (int) $syncToken < (int) $oldestKept && (int) $syncToken < $current) {
            return null;
        }

        $rows = DB::table('dav_changes')
            ->where('address_book_id', $addressBookId)
            ->where('synctoken', '>=', (int) $syncToken)
            ->orderBy('synctoken')
            ->when($limit, fn ($q) => $q->limit((int) $limit))
            ->get(['uri', 'operation']);

        // Latest operation per uri wins.
        /** @var array<string, int> $latest */
        $latest = [];
        foreach ($rows as $row) {
            $uri = is_scalar($row->uri) ? (string) $row->uri : '';
            $latest[$uri] = is_numeric($row->operation) ? (int) $row->operation : 0;
        }

        $result = ['syncToken' => (string) $current, 'added' => [], 'modified' => [], 'deleted' => []];
        foreach ($latest as $uri => $op) {
            $result[match (DavChangeOperation::from($op)) {
                DavChangeOperation::Added => 'added',
                DavChangeOperation::Modified => 'modified',
                DavChangeOperation::Deleted => 'deleted',
            }][] = (string) $uri;
        }

        return $result;
    }

    private function logChange(string $addressBookId, string $uri, DavChangeOperation $op): void
    {
        $book = AddressBook::query()->withoutGlobalScopes()->find($addressBookId);
        if ($book !== null) {
            $this->changes->record($book, $uri, $op);
        }
    }

    /**
     * Resolve the principal to the authenticated user's id. The principal path is
     * always the caller's own (PrincipalBackend never exposes another), so this
     * only accepts the request's authenticated user.
     */
    private function userId(string $principalUri): ?int
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $key = $user->getKey();

        return basename($principalUri) === (string) $user->email && is_numeric($key) ? (int) $key : null;
    }
}
