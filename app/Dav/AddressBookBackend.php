<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\DavCredential;
use App\Services\Contacts\VCardService;
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
    public function __construct(private readonly VCardService $vcards) {}

    public function getAddressBooksForUser($principalUri): array
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return [];
        }

        return AddressBook::where('user_id', $userId)->get()->map(fn (AddressBook $b): array => [
            'id' => $b->id,
            'uri' => $b->uri,
            'principaluri' => $principalUri,
            '{DAV:}displayname' => $b->name,
            '{urn:ietf:params:xml:ns:carddav}addressbook-description' => (string) $b->description,
            '{http://sabredav.org/ns}sync-token' => (string) $b->synctoken,
        ])->all();
    }

    public function updateAddressBook($addressBookId, PropPatch $propPatch): void
    {
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
        AddressBook::where('id', $addressBookId)->delete();
    }

    public function getCards($addressbookId): array
    {
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
        $etag = md5($cardData);
        Contact::create(array_merge([
            'address_book_id' => $addressBookId,
            'uri' => $cardUri,
            'etag' => $etag,
            'vcard' => $cardData,
        ], $this->vcards->denormalize($cardData)));

        $this->logChange($addressBookId, $cardUri, 1);

        return '"'.$etag.'"';
    }

    public function updateCard($addressBookId, $cardUri, $cardData): ?string
    {
        $contact = Contact::where('address_book_id', $addressBookId)->where('uri', $cardUri)->first();
        if ($contact === null) {
            return null;
        }

        $etag = md5($cardData);
        $contact->forceFill(array_merge(['etag' => $etag, 'vcard' => $cardData], $this->vcards->denormalize($cardData)))->save();
        $this->logChange($addressBookId, $cardUri, 2);

        return '"'.$etag.'"';
    }

    public function deleteCard($addressBookId, $cardUri): bool
    {
        $deleted = Contact::where('address_book_id', $addressBookId)->where('uri', $cardUri)->delete();
        if ($deleted) {
            $this->logChange($addressBookId, $cardUri, 3);
        }

        return $deleted > 0;
    }

    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null): ?array
    {
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

        $rows = DB::table('dav_changes')
            ->where('address_book_id', $addressBookId)
            ->where('synctoken', '>=', (int) $syncToken)
            ->orderBy('synctoken')
            ->get(['uri', 'operation']);

        // Latest operation per uri wins.
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->uri] = $row->operation;
        }

        $result = ['syncToken' => (string) $current, 'added' => [], 'modified' => [], 'deleted' => []];
        foreach ($latest as $uri => $op) {
            $result[match ($op) {
                1 => 'added', 2 => 'modified', default => 'deleted'
            }][] = $uri;
        }

        return $result;
    }

    private function logChange(string $addressBookId, string $uri, int $operation): void
    {
        $book = AddressBook::find($addressBookId);
        if ($book === null) {
            return;
        }
        $token = (int) $book->synctoken + 1;
        $book->forceFill(['synctoken' => $token])->save();

        DB::table('dav_changes')->insert([
            'address_book_id' => $addressBookId,
            'uri' => $uri,
            'operation' => $operation,
            'synctoken' => $token,
        ]);
    }

    private function userId(string $principalUri): ?int
    {
        $username = basename($principalUri);

        return DavCredential::where('username', $username)->value('user_id');
    }
}
