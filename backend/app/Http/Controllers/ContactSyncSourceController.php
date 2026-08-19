<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\Contacts\SyncContactSource;
use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactSyncSource;
use App\Models\ContactVersion;
use App\Support\OutboundUrl;
use App\Services\Contacts\ContactPersister;
use App\Services\Contacts\ContactReplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** External CardDAV source setup; passwords/tokens never leave the server. */
class ContactSyncSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $this->requireUser($request)->id;
        $sources = ContactSyncSource::query()->orderBy('name')->get()->map(fn (ContactSyncSource $source): array => $this->present($source));

        return response()->json(['sources' => $sources, 'versions' => ContactVersion::query()->where('user_id', $userId)->latest()->limit(50)->get(['id', 'contact_id', 'source_id', 'action', 'remote_uri', 'created_at'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'address_book_id' => ['required', 'uuid'],
            'provider' => ['required', 'in:carddav,icloud,google'], 'endpoint' => ['nullable', 'url', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'], 'password' => ['nullable', 'string', 'max:1024'],
            'access_token' => ['nullable', 'string', 'max:8192'], 'oauth_client_id' => ['nullable', 'string', 'max:1024'],
            'oauth_client_secret' => ['nullable', 'string', 'max:2048'], 'propagate_deletes' => ['nullable', 'boolean'],
        ]);
        /** @var array{name:string,address_book_id:string,provider:'carddav'|'icloud'|'google',endpoint?:string,username?:string,password?:string,access_token?:string,oauth_client_id?:string,oauth_client_secret?:string,propagate_deletes?:bool} $data */
        $user = $this->requireUser($request);
        AddressBook::query()->where('user_id', $user->id)->findOrFail($data['address_book_id']);
        $provider = (string) $data['provider'];
        $endpoint = (string) ($data['endpoint'] ?? '');
        if ($provider === 'google' && $endpoint === '') {
            $endpoint = 'https://www.googleapis.com/.well-known/carddav';
        }
        abort_if($endpoint === '', 422, 'A CardDAV endpoint is required.');
        abort_unless(OutboundUrl::safe($endpoint), 422, 'The CardDAV endpoint is not allowed.');
        if ($provider === 'google') {
            abort_unless(is_string($data['oauth_client_id'] ?? null) && is_string($data['oauth_client_secret'] ?? null), 422, 'Google requires an OAuth client id and secret.');
        } else {
            abort_unless(is_string($data['username'] ?? null) && is_string($data['password'] ?? null), 422, 'CardDAV needs username and password.');
        }
        $source = ContactSyncSource::create([
            'user_id' => $user->id, 'address_book_id' => $data['address_book_id'], 'name' => $data['name'],
            'provider' => $provider, 'endpoint' => $endpoint, 'auth_type' => $provider === 'google' ? 'oauth2' : 'basic',
            'username' => $data['username'] ?? null, 'password' => $data['password'] ?? null,
            'access_token' => $data['access_token'] ?? null, 'oauth_client_id' => $data['oauth_client_id'] ?? null,
            'oauth_client_secret' => $data['oauth_client_secret'] ?? null,
            'propagate_deletes' => (bool) ($data['propagate_deletes'] ?? true),
        ]);

        return response()->json(['source' => $this->present($source), 'authorize_url' => $provider === 'google' ? route('contacts.sources.authorize', $source) : null], 201);
    }

    public function destroy(ContactSyncSource $source): JsonResponse
    {
        $this->authorizeSource($source);
        $source->delete();

        return response()->json(['ok' => true]);
    }

    public function sync(ContactSyncSource $source): JsonResponse
    {
        $this->authorizeSource($source);
        SyncContactSource::dispatch($source->id);

        return response()->json(['ok' => true]);
    }

    /** Restore a captured vCard locally, then queue it for every configured replica. */
    public function restoreVersion(Request $request, ContactVersion $version, ContactPersister $persister, ContactReplication $replication): JsonResponse
    {
        abort_unless($version->user_id === $this->requireUser($request)->id, 403);
        $contact = $version->contact_id !== null ? Contact::query()->find($version->contact_id) : null;
        if ($contact === null) {
            $bookId = ContactSyncSource::query()->whereKey($version->source_id)->value('address_book_id');
            $book = is_string($bookId) ? AddressBook::query()->whereKey($bookId)->first() : AddressBook::query()->first();
            abort_if($book === null, 422, 'Create an address book before restoring a contact.');
            $contact = $persister->persistNew($book, Str::uuid().'.vcf', $version->vcard);
        } else {
            $persister->persistUpdate($contact, $version->vcard);
        }
        ContactVersion::query()->create(['user_id' => $version->user_id, 'contact_id' => $contact->id, 'source_id' => $version->source_id, 'action' => 'restored', 'remote_uri' => $version->remote_uri, 'remote_etag' => $version->remote_etag, 'vcard' => $contact->vcard, 'metadata' => ['restored_from' => $version->id]]);
        $replication->queue($contact);

        return response()->json(['ok' => true, 'contact_id' => $contact->id]);
    }

    /** Redirect the signed-in owner to Google's consent screen. */
    public function authorizeGoogle(Request $request, ContactSyncSource $source): RedirectResponse
    {
        $this->authorizeSource($source);
        abort_unless($source->provider === 'google', 404);
        $nonce = Str::random(48);
        $source->forceFill(['oauth_state_hash' => hash('sha256', $nonce)])->save();
        $state = $source->id.'.'.$nonce;
        $query = http_build_query([
            'client_id' => $source->oauth_client_id, 'redirect_uri' => route('contacts.sources.google.callback'),
            'response_type' => 'code', 'access_type' => 'offline', 'prompt' => 'consent',
            'scope' => 'https://www.googleapis.com/auth/carddav', 'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    /** OAuth callback validates state against the session owner before storing encrypted tokens. */
    public function googleCallback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        [$id, $nonce] = array_pad(explode('.', $state, 2), 2, '');
        $source = ContactSyncSource::query()->findOrFail($id);
        $this->authorizeSource($source);
        abort_unless($nonce !== '' && hash_equals((string) $source->oauth_state_hash, hash('sha256', $nonce)), 403);
        $code = (string) $request->query('code', '');
        abort_unless($code !== '', 422);
        $token = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $source->oauth_client_id, 'client_secret' => $source->oauth_client_secret,
            'redirect_uri' => route('contacts.sources.google.callback'), 'grant_type' => 'authorization_code',
        ]);
        $accessToken = $token->json('access_token');
        abort_unless($token->successful() && is_string($accessToken), 422, 'Google authorization failed.');
        $refreshToken = $token->json('refresh_token');
        $expiresIn = $token->json('expires_in');
        $source->forceFill([
            'access_token' => $accessToken, 'refresh_token' => is_string($refreshToken) ? $refreshToken : $source->refresh_token,
            'access_token_expires_at' => now()->addSeconds(is_numeric($expiresIn) ? max(60, (int) $expiresIn) : 3600), 'oauth_state_hash' => null,
        ])->save();
        SyncContactSource::dispatch($source->id);

        return redirect('/contacts?carddav=connected');
    }

    /** @return array<string, mixed> */
    private function present(ContactSyncSource $source): array
    {
        return ['id' => $source->id, 'name' => $source->name, 'provider' => $source->provider, 'address_book_id' => $source->address_book_id,
            'endpoint' => $source->endpoint, 'username' => $source->username, 'enabled' => $source->enabled,
            'propagate_deletes' => $source->propagate_deletes, 'status' => $source->status, 'last_error' => $source->last_error,
            'last_synced_at' => $source->last_synced_at?->toIso8601String(), 'connected' => $source->provider !== 'google' || $source->refresh_token !== null];
    }

    private function authorizeSource(ContactSyncSource $source): void
    {
        abort_unless($source->user_id === auth()->id(), 403);
    }
}
