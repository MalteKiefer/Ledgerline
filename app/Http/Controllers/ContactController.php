<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Person;
use App\Models\UserSetting;
use App\Services\Contacts\ContactImporter;
use App\Services\Contacts\ContactWriter;
use App\Services\Contacts\VCardService;
use App\Services\Files\ReverseGeocoder;
use App\Support\ImageManagerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Intervention\Image\Encoders\JpegEncoder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contacts UI backend (reload-free JSON). All queries are scoped to the
 * authenticated user's address books; writes go through ContactWriter so the
 * vCard, groups and DAV sync token stay consistent.
 */
class ContactController extends Controller
{
    public function index(): View
    {
        return view('contacts.index');
    }

    /** Dedicated editor page for a new contact. */
    public function create(): View
    {
        return view('contacts.edit', ['contactId' => null]);
    }

    /** Dedicated editor page for an existing contact. */
    public function edit(Contact $contact): View
    {
        $this->authorizeContact($contact);

        return view('contacts.edit', ['contactId' => $contact->id]);
    }

    /** Read-only detail page; editing is a separate step (Google-style). */
    public function view(Contact $contact): View
    {
        $this->authorizeContact($contact);

        return view('contacts.show', ['contactId' => $contact->id]);
    }

    public function data(Request $request, VCardService $vcards): JsonResponse
    {
        $userId = $request->user()->id;
        // Owned OR shared-with-me address books (SharesWithUsers scope).
        $bookIds = AddressBook::query()->pluck('id');
        $settings = UserSetting::for($userId);

        $q = trim((string) $request->query('q'));
        $bookId = $request->query('book');
        $groupId = $request->query('group');
        $favorites = $request->boolean('favorites');

        // Sort by the chosen name, falling back to the formatted name when that
        // component is blank (e.g. imported cards with only FN); ties broken by
        // the other name so ordering is stable.
        [$primary, $secondary] = $settings->contact_sort === 'last_name'
            ? ['last_name', 'first_name']
            : ['first_name', 'last_name'];

        $contacts = Contact::query()
            ->whereIn('address_book_id', $bookIds)
            ->when($bookId, fn ($x) => $x->where('address_book_id', $bookId))
            ->when($groupId, fn ($x) => $x->whereHas('groups', fn ($g) => $g->where('contact_groups.id', $groupId)))
            ->when($favorites, fn ($x) => $x->where('favorite', true))
            // Search across every field: the denormalised columns for precision
            // plus the raw vCard so notes, title, nickname, addresses, URLs and
            // phone numbers are all matched too. lower(...) both sides makes it
            // case-insensitive on PostgreSQL (LIKE is case-sensitive there).
            ->when($q !== '', function ($x) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $x->where(fn ($w) => $w
                    ->whereRaw('lower(fn) like ?', [$like])
                    ->orWhereRaw('lower(first_name) like ?', [$like])
                    ->orWhereRaw('lower(last_name) like ?', [$like])
                    ->orWhereRaw('lower(org) like ?', [$like])
                    ->orWhereRaw('lower(vcard) like ?', [$like]));
            })
            ->orderByRaw("lower(coalesce(nullif({$primary}, ''), fn)) asc")
            ->orderByRaw("lower(coalesce(nullif({$secondary}, ''), '')) asc")
            ->get()
            ->map(fn (Contact $c): array => [
                'id' => $c->id,
                'book' => $c->address_book_id,
                'fn' => $c->fn,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'org' => $c->org,
                'emails' => $c->emails ?? [],
                'phones' => $c->phones ?? [],
                'has_photo' => $c->has_photo,
                'favorite' => $c->favorite,
                'avatar' => $this->avatarUrl($c),
            ]);

        return response()->json([
            'books' => AddressBook::query()->orderBy('name')->get(['id', 'name', 'uri', 'user_id'])
                ->map(fn ($b): array => ['id' => $b->id, 'name' => $b->name, 'uri' => $b->uri, 'owned' => (int) $b->user_id === $userId]),
            'groups' => ContactGroup::where('user_id', $userId)->orderBy('name')->get(['id', 'name']),
            'contacts' => $contacts,
            'settings' => [
                'sort' => $settings->contact_sort,
                'display_format' => $settings->contact_display_format,
            ],
        ]);
    }

    /** Lightweight name autocomplete over the user's contacts (for the gallery people naming). */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $bookIds = AddressBook::where('user_id', $request->user()->id)->pluck('id');

        $contacts = Contact::query()
            ->whereIn('address_book_id', $bookIds)
            ->when($q !== '', function ($x) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $x->where(fn ($w) => $w
                    ->whereRaw('lower(fn) like ?', [$like])
                    ->orWhereRaw('lower(first_name) like ?', [$like])
                    ->orWhereRaw('lower(last_name) like ?', [$like]));
            })
            ->orderByRaw("lower(coalesce(nullif(first_name, ''), fn)) asc")
            ->limit(10)
            ->get()
            ->map(fn (Contact $c): array => [
                'id' => $c->id,
                'uid' => $c->uid,
                'name' => $c->fn ?: trim(((string) $c->first_name).' '.((string) $c->last_name)),
                'avatar' => $this->avatarUrl($c),
            ])
            ->filter(fn (array $c): bool => $c['name'] !== '')
            ->values();

        return response()->json(['contacts' => $contacts]);
    }

    /** Persist the user's contacts list preferences (sort + display format). */
    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sort' => ['required', 'in:first_name,last_name'],
            'display_format' => ['required', 'in:first_last,last_first'],
        ]);

        UserSetting::for($request->user()->id)->update([
            'contact_sort' => $data['sort'],
            'contact_display_format' => $data['display_format'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function show(Contact $contact, VCardService $vcards): JsonResponse
    {
        $this->authorizeContact($contact);
        $data = $vcards->parse($contact->vcard);
        $data['related'] = $this->resolveRelated($data['related'] ?? []);

        // A gallery person linked to this card (people.contact_id) lets the
        // detail page offer "show this person's photos".
        $person = Person::query()->where('contact_id', $contact->id)
            ->whereNull('hidden_at')->orderByDesc('faces_count')->first();

        return response()->json(array_merge(
            $data,
            [
                'id' => $contact->id,
                'book' => $contact->address_book_id,
                'group_ids' => $contact->groups()->pluck('contact_groups.id'),
                'person' => $person ? ['id' => $person->id, 'url' => route('gallery.people.show', $person)] : null,
            ],
        ));
    }

    /** Flip the favorite star (rebuilds the vCard so DAV clients sync it too). */
    public function favorite(Request $request, Contact $contact, ContactWriter $writer, VCardService $vcards): JsonResponse
    {
        $this->authorizeContact($contact);
        $favorite = $request->validate(['favorite' => ['required', 'boolean']])['favorite'];

        $data = $vcards->parse($contact->vcard);
        $data['favorite'] = $favorite;
        $writer->update($contact, $data, $contact->groups()->pluck('contact_groups.id')->all());

        return response()->json(['ok' => true, 'favorite' => (bool) $favorite]);
    }

    /**
     * Geocode one of the contact's postal addresses for the map preview. Uses
     * the cached, rate-limited Nominatim client; returns 404 when the address
     * cannot be resolved.
     */
    public function geocode(Request $request, Contact $contact, VCardService $vcards, ReverseGeocoder $geocoder): JsonResponse
    {
        $this->authorizeContact($contact);
        $index = (int) $request->query('address', 0);

        $address = ($vcards->parse($contact->vcard)['addresses'] ?? [])[$index] ?? null;
        abort_unless(is_array($address), 404);

        $query = implode(', ', array_filter([
            trim(implode(' ', array_filter([$address['street'] ?? null, $address['ext'] ?? null]))),
            trim(implode(' ', array_filter([$address['zip'] ?? null, $address['city'] ?? null]))),
            $address['region'] ?? null,
            $address['country'] ?? null,
        ], fn (?string $v): bool => $v !== null && trim($v) !== ''));
        abort_if($query === '', 404);

        $match = $geocoder->search($query)[0] ?? null;
        abort_unless($match !== null, 404);

        return response()->json($match);
    }

    /**
     * Attach the linked contact's current name (and id) to RELATED entries that
     * point at another card via urn:uuid, so the UI can render and open them.
     *
     * @param  list<array{type: ?string, value: ?string, uid: ?string}>  $related
     * @return list<array{type: ?string, value: ?string, uid: ?string, contact_id: ?string, name: ?string}>
     */
    private function resolveRelated(array $related): array
    {
        $uids = array_values(array_filter(array_column($related, 'uid')));
        $bookIds = AddressBook::where('user_id', auth()->id())->pluck('id');
        $byUid = $uids === []
            ? collect()
            : Contact::whereIn('address_book_id', $bookIds)->whereIn('uid', $uids)->get()->keyBy('uid');

        return array_map(function (array $r) use ($byUid): array {
            $match = $r['uid'] !== null ? $byUid->get($r['uid']) : null;

            return $r + [
                'contact_id' => $match?->id,
                'name' => $match?->fn ?: ($r['value'] ?? null),
            ];
        }, $related);
    }

    public function store(Request $request, ContactWriter $writer): JsonResponse
    {
        $data = $this->validated($request, creating: true);
        $book = AddressBook::where('user_id', $request->user()->id)->findOrFail($data['book_id']);
        $contact = $writer->create($book, $data, $data['group_ids'] ?? []);

        return response()->json(['id' => $contact->id], 201);
    }

    public function update(Request $request, Contact $contact, VCardService $vcards, ContactWriter $writer): JsonResponse
    {
        $this->authorizeContact($contact);
        $data = $this->validated($request);
        // The editor never posts the photo — carry the existing one over, or the
        // rebuilt vCard silently drops it.
        $data['photo'] = $vcards->parse($contact->vcard)['photo'] ?? null;
        $writer->update($contact, $data, $data['group_ids'] ?? []);

        return response()->json(['ok' => true]);
    }

    public function destroy(Contact $contact, ContactWriter $writer): JsonResponse
    {
        $this->authorizeContact($contact);
        $writer->delete($contact);

        return response()->json(['ok' => true]);
    }

    /** Set the contact's PHOTO from an uploaded image (capped, base64 in the vCard). */
    public function avatar(Request $request, Contact $contact, ContactWriter $writer, VCardService $vcards): JsonResponse
    {
        $this->authorizeContact($contact);
        $request->validate(['photo' => ['required', 'image', 'max:15360']]);

        $image = app(ImageManagerFactory::class)->make()->decodePath($request->file('photo')->getRealPath())->scaleDown(512, 512);
        $dataUri = 'data:image/jpeg;base64,'.base64_encode((string) $image->encode(new JpegEncoder(quality: 82)));

        $data = $vcards->parse($contact->vcard);
        $data['photo'] = $dataUri;
        $writer->update($contact, $data, $contact->groups()->pluck('contact_groups.id')->all());

        return response()->json(['ok' => true, 'avatar' => route('contacts.avatar', ['contact' => $contact])]);
    }

    /** Serve the contact's PHOTO (decoded from the vCard data URI). */
    public function avatarImage(Contact $contact, VCardService $vcards): Response
    {
        $this->authorizeContact($contact);
        $photo = $vcards->parse($contact->vcard)['photo'] ?? null;
        abort_unless(is_string($photo) && str_starts_with($photo, 'data:'), 404);

        [$meta, $b64] = explode(',', $photo, 2);
        $mime = str_contains($meta, 'image/png') ? 'image/png' : 'image/jpeg';

        return response(base64_decode($b64), 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Export a book (or all the user's contacts) as one .vcf download. */
    public function export(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $books = AddressBook::where('user_id', $userId)
            ->when($request->query('book'), fn ($q) => $q->where('id', $request->query('book')))
            ->pluck('id');

        return response()->streamDownload(function () use ($books): void {
            Contact::whereIn('address_book_id', $books)->orderBy('fn')->chunk(200, function ($chunk): void {
                foreach ($chunk as $contact) {
                    echo rtrim($contact->vcard, "\r\n")."\r\n";
                }
            });
        }, 'contacts.vcf', ['Content-Type' => 'text/vcard; charset=utf-8']);
    }

    /** Import a .vcf (one or many cards) into a book; dedupe by UID. */
    public function import(Request $request, ContactImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'book_id' => ['required', 'string'],
        ]);
        $book = AddressBook::where('user_id', $request->user()->id)->findOrFail($data['book_id']);

        $result = $importer->import($book, (string) file_get_contents($request->file('file')->getRealPath()));

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'book_id' => [$creating ? 'required' : 'sometimes', 'string'],
            'fn' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'org' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'bday' => ['nullable', 'string', 'max:32'],
            'anniversaries' => ['array'],
            'anniversaries.*.date' => ['nullable', 'string', 'max:32'],
            'anniversaries.*.label' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:5000'],
            'emails' => ['array'],
            'phones' => ['array'],
            'urls' => ['array'],
            'addresses' => ['array'],
            'addresses.*.type' => ['nullable', 'string', 'max:32'],
            'addresses.*.ext' => ['nullable', 'string', 'max:255'],
            'addresses.*.street' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.region' => ['nullable', 'string', 'max:255'],
            'addresses.*.zip' => ['nullable', 'string', 'max:32'],
            'addresses.*.country' => ['nullable', 'string', 'max:255'],
            'related' => ['array'],
            'related.*.type' => ['nullable', 'string', 'max:32'],
            'related.*.value' => ['nullable', 'string', 'max:255'],
            'related.*.uid' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['array'],
            'custom_fields.*.label' => ['nullable', 'string', 'max:64'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:1000'],
            'favorite' => ['nullable', 'boolean'],
            'group_ids' => ['array'],
            'group_ids.*' => ['string'],
        ]);
    }

    private function authorizeContact(Contact $contact): void
    {
        // The book relation is owner-scoped, so another user's contact resolves
        // to a null book — that must read as forbidden, not a 500.
        abort_unless($contact->addressBook?->user_id === auth()->id(), 403);
    }

    /**
     * Avatar URL with a version stamp so a changed photo busts the browser cache
     * (the avatar route otherwise returns the same URL with a 1h cache header,
     * which showed a stale/broken image in the list after a photo change).
     */
    private function avatarUrl(Contact $c): ?string
    {
        if (! $c->has_photo) {
            return null;
        }

        return route('contacts.avatar', ['contact' => $c]).'?v='.($c->updated_at?->timestamp ?? 0);
    }
}
