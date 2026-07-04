<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Contact group CRUD, scoped to the authenticated user. */
class ContactGroupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:255']])['name'];
        $group = ContactGroup::firstOrCreate(['user_id' => $request->user()->id, 'name' => $name]);

        return response()->json(['id' => $group->id], 201);
    }

    public function destroy(ContactGroup $group): JsonResponse
    {
        abort_unless($group->user_id === auth()->id(), 403);
        $group->delete();

        return response()->json(['ok' => true]);
    }
}
