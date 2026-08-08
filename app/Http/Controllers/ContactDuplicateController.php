<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use App\Models\Contact;
use App\Models\ContactDuplicateDismissal;
use App\Services\Contacts\ContactDuplicateFinder;
use App\Services\Contacts\ContactMerger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Duplicate-contact review: lists likely-duplicate groups, merges a group into a
 * chosen primary (unioning fields), and dismisses a group the user says is fine.
 * All contacts are re-checked against the caller's ownership on every write.
 */
class ContactDuplicateController extends Controller
{
    public function index(): View
    {
        return view('contacts.duplicates');
    }

    public function data(Request $request, ContactDuplicateFinder $finder): JsonResponse
    {
        return response()->json(['groups' => $finder->forUser($request->user()->id)]);
    }

    public function merge(Request $request, ContactMerger $merger): JsonResponse
    {
        $data = $request->validate([
            'primary_id' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['string'],
        ]);

        $contacts = $this->ownedContacts($request, $data['ids']);
        abort_unless($contacts->count() === count(array_unique($data['ids'])), 403);

        $primary = $contacts->firstWhere('id', $data['primary_id']);
        abort_unless($primary !== null, 422);

        $merger->merge($primary, $contacts->reject(fn (Contact $c): bool => $c->id === $primary->id)->values());

        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['string'],
        ]);

        // Confirm every id is the caller's before persisting the dismissal.
        abort_unless($this->ownedContacts($request, $data['ids'])->count() === count(array_unique($data['ids'])), 403);

        ContactDuplicateDismissal::firstOrCreate([
            'user_id' => $request->user()->id,
            'signature' => ContactDuplicateDismissal::signatureFor($data['ids']),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Contact>
     */
    private function ownedContacts(Request $request, array $ids)
    {
        $bookIds = AddressBook::where('user_id', $request->user()->id)->pluck('id');

        return Contact::whereIn('address_book_id', $bookIds)->whereIn('id', $ids)->get();
    }
}
