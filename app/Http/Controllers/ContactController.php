<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\UserSetting;
use App\Services\Contacts\ContactImporter;
use App\Services\Contacts\ContactWriter;
use App\Services\Contacts\VCardService;
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

    public function data(Request $request, VCardService $vcards): JsonResponse
    {
        $userId = $request->user()->id;
        $bookIds = AddressBook::where('user_id', $userId)->pluck('id');
        $settings = UserSetting::for($userId);

        $q = trim((string) $request->query('q'));
        $bookId = $request->query('book');
        $groupId = $request->query('group');

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
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('fn', 'like', "%{$q}%")->orWhere('org', 'like', "%{$q}%")
                ->orWhereJsonContains('emails', $q)))
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
                'avatar' => $c->has_photo ? route('contacts.avatar', ['contact' => $c]) : null,
            ]);

        return response()->json([
            'books' => AddressBook::where('user_id', $userId)->orderBy('name')->get(['id', 'name', 'uri']),
            'groups' => ContactGroup::where('user_id', $userId)->orderBy('name')->get(['id', 'name']),
            'contacts' => $contacts,
            'settings' => [
                'sort' => $settings->contact_sort,
                'display_format' => $settings->contact_display_format,
            ],
        ]);
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

        return response()->json(array_merge(
            $vcards->parse($contact->vcard),
            ['id' => $contact->id, 'book' => $contact->address_book_id, 'group_ids' => $contact->groups()->pluck('contact_groups.id')],
        ));
    }

    public function store(Request $request, ContactWriter $writer): JsonResponse
    {
        $data = $this->validated($request);
        $book = AddressBook::where('user_id', $request->user()->id)->findOrFail($data['book_id']);
        $contact = $writer->create($book, $data, $data['group_ids'] ?? []);

        return response()->json(['id' => $contact->id], 201);
    }

    public function update(Request $request, Contact $contact, ContactWriter $writer): JsonResponse
    {
        $this->authorizeContact($contact);
        $data = $this->validated($request);
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

        return response(base64_decode($b64), 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=3600']);
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
    private function validated(Request $request): array
    {
        return $request->validate([
            'book_id' => ['sometimes', 'string'],
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
            'group_ids' => ['array'],
            'group_ids.*' => ['string'],
        ]);
    }

    private function authorizeContact(Contact $contact): void
    {
        abort_unless($contact->addressBook->user_id === auth()->id(), 403);
    }
}
