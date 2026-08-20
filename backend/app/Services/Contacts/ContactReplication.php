<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Jobs\Contacts\SyncContactSource;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactSyncRemoteCard;
use App\Models\ContactSyncSource;
use App\Models\ContactVersion;
use App\Support\Redactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Coordinates Ledgerline-first three-way replication through each CardDAV peer. */
final class ContactReplication
{
    public function __construct(
        private readonly CardDavReplicaClient $client,
        private readonly ContactPersister $persister,
        private readonly VCardService $vcards,
    ) {}

    /** Queue all replicas of a changed local contact after its transaction commits. */
    public function queue(Contact $contact): void
    {
        foreach (ContactSyncSource::query()->where('address_book_id', $contact->address_book_id)->where('enabled', true)->pluck('id') as $id) {
            if (is_string($id)) {
                SyncContactSource::dispatch($id);
            }
        }
    }

    /** Preserve mappings for a deleted local contact, then reconcile remote delete intent. */
    public function queueDeletion(string $contactId): void
    {
        foreach (ContactSyncRemoteCard::query()->where('contact_id', $contactId)->pluck('source_id')->unique() as $id) {
            if (is_string($id)) {
                SyncContactSource::dispatch($id);
            }
        }
    }

    /** Pull remote state, record recoverable versions, then make the peer match Ledgerline. */
    public function sync(ContactSyncSource $source): void
    {
        $source->forceFill(['status' => 'syncing', 'last_error' => null])->save();
        try {
            $remoteCards = $this->client->cards($source);
            $seen = [];
            foreach ($remoteCards as $remote) {
                $seen[$remote['uri']] = true;
                $this->applyRemoteCard($source, $remote['uri'], $remote['etag'], $remote['vcard']);
            }
            $this->applyRemoteDeletions($source, $seen);
            $this->pushCanonicalCards($source);
            $source->forceFill(['status' => 'idle', 'last_error' => null, 'last_synced_at' => now()])->save();
        } catch (Throwable $e) {
            // A transport error can carry the endpoint (and with it credential
            // material) in its message, and last_error is handed back to the
            // UI — redact before it is logged or stored, as every other
            // outbound integration in this app does.
            $message = Redactor::redact($e->getMessage());
            report($e);
            $source->forceFill(['status' => 'error', 'last_error' => Str::limit($message, 1000)])->save();
        }
    }

    private function applyRemoteCard(ContactSyncSource $source, string $uri, ?string $remoteEtag, string $vcard): void
    {
        $mapping = ContactSyncRemoteCard::query()->where('source_id', $source->id)->where('remote_uri', $uri)->first();
        $contact = $mapping === null ? null : Contact::query()->find($mapping->contact_id);
        if ($mapping !== null && $mapping->remote_etag === $remoteEtag) {
            return;
        }
        // A known mapping without a local row is an intentional Ledgerline
        // deletion. Do not resurrect it from an older remote replica; the push
        // phase below will issue the configured remote DELETE instead.
        if ($mapping !== null && $contact === null) {
            return;
        }
        if ($contact !== null && $mapping !== null && $mapping->local_etag !== null && $mapping->local_etag !== $contact->etag && $contact->vcard !== $vcard) {
            $this->version($source, $contact->id, 'conflict', $uri, $remoteEtag, $vcard, ['reason' => 'local_and_remote_changed']);

            return;
        }
        if ($contact === null) {
            $uid = $this->vcards->denormalize($vcard)['uid'] ?? null;
            if (is_string($uid) && $uid !== '') {
                $contact = Contact::query()->where('address_book_id', $source->address_book_id)->where('uid', $uid)->first();
            }
        }
        DB::transaction(function () use ($source, $uri, $remoteEtag, $vcard, $contact): void {
            if ($contact === null) {
                $book = AddressBook::query()->findOrFail($source->address_book_id);
                $contact = $this->persister->persistNew($book, Str::uuid().'.vcf', $vcard);
                $action = 'created';
            } else {
                $this->version($source, $contact->id, 'updated', $uri, $remoteEtag, $contact->vcard, ['direction' => 'before_remote_import']);
                $this->persister->persistUpdate($contact, $vcard);
                $action = 'updated';
            }
            ContactSyncRemoteCard::query()->updateOrCreate(
                ['source_id' => $source->id, 'remote_uri' => $uri],
                ['contact_id' => $contact->id, 'remote_etag' => $remoteEtag, 'remote_uid' => $contact->uid, 'local_etag' => $contact->etag, 'remote_deleted_at' => null],
            );
            $this->version($source, $contact->id, $action, $uri, $remoteEtag, $contact->vcard, ['direction' => 'remote_to_ledgerline']);
        });
    }

    /** Remote deletion is recorded, never applied locally; Ledgerline restores the replica. */
    /** @param array<string, true> $seen */
    private function applyRemoteDeletions(ContactSyncSource $source, array $seen): void
    {
        ContactSyncRemoteCard::query()->where('source_id', $source->id)->get()->each(function (ContactSyncRemoteCard $mapping) use ($source, $seen): void {
            if (isset($seen[$mapping->remote_uri])) {
                return;
            }
            $contact = Contact::query()->find($mapping->contact_id);
            if ($mapping->remote_deleted_at === null && $contact !== null) {
                $this->version($source, $contact->id, 'deleted', $mapping->remote_uri, $mapping->remote_etag, $contact->vcard, ['direction' => 'remote_deleted_preserved_locally']);
            }
            $mapping->forceFill(['remote_deleted_at' => now(), 'remote_etag' => null])->save();
        });
    }

    /** Ledgerline is authoritative: upsert every local card to this source. */
    private function pushCanonicalCards(ContactSyncSource $source): void
    {
        $contacts = Contact::query()->where('address_book_id', $source->address_book_id)->get();
        foreach ($contacts as $contact) {
            $mapping = ContactSyncRemoteCard::query()->where('source_id', $source->id)->where('contact_id', $contact->id)->first();
            if ($mapping !== null && $mapping->local_etag === $contact->etag && $mapping->remote_deleted_at === null) {
                continue;
            }
            $uri = $mapping?->remote_uri ?? rawurlencode((string) $contact->uid).'.vcf';
            $etag = $this->client->put($source, $uri, $contact->vcard, $mapping?->remote_etag);
            ContactSyncRemoteCard::query()->updateOrCreate(
                ['source_id' => $source->id, 'remote_uri' => $uri],
                ['contact_id' => $contact->id, 'remote_etag' => $etag !== '' ? $etag : null, 'remote_uid' => $contact->uid, 'local_etag' => $contact->etag, 'remote_deleted_at' => null],
            );
        }
        if (! $source->propagate_deletes) {
            return;
        }
        ContactSyncRemoteCard::query()->where('source_id', $source->id)->get()->each(function (ContactSyncRemoteCard $mapping) use ($source): void {
            if (Contact::query()->whereKey($mapping->contact_id)->exists()) {
                return;
            }
            $this->client->delete($source, $mapping->remote_uri, $mapping->remote_etag);
            $mapping->delete();
        });
    }

    /** @param array<string, scalar> $metadata */
    private function version(ContactSyncSource $source, ?string $contactId, string $action, ?string $uri, ?string $etag, string $vcard, array $metadata = []): void
    {
        ContactVersion::query()->create(['user_id' => $source->user_id, 'contact_id' => $contactId, 'source_id' => $source->id, 'action' => $action, 'remote_uri' => $uri, 'remote_etag' => $etag, 'vcard' => $vcard, 'metadata' => $metadata]);
    }
}
