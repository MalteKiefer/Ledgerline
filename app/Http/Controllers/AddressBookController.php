<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AddressBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Address book CRUD (CardDAV collections), scoped to the authenticated user. */
class AddressBookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $name = (string) $request->string('name');
        $book = AddressBook::create([
            'user_id' => $this->requireUser($request)->id,
            'name' => $name,
            'uri' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'synctoken' => 1,
        ]);

        return response()->json(['id' => $book->id], 201);
    }

    public function update(Request $request, AddressBook $addressBook): JsonResponse
    {
        $this->authorizeBook($addressBook);
        $request->validate(['name' => ['required', 'string', 'max:255']]);
        $addressBook->update(['name' => (string) $request->string('name')]);

        return response()->json(['ok' => true]);
    }

    public function destroy(AddressBook $addressBook): JsonResponse
    {
        $this->authorizeBook($addressBook);
        // Keep at least one book.
        abort_if(AddressBook::where('user_id', $addressBook->user_id)->count() <= 1, 422);
        $addressBook->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeBook(AddressBook $book): void
    {
        abort_unless($book->user_id === auth()->id(), 403);
    }
}
