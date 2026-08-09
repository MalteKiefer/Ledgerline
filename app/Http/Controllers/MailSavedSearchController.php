<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailSavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-scoped CRUD for saved mail searches (persisted filter sets, surfaced as
 * virtual folders). A foreign / unknown id is a 404.
 */
class MailSavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searches = MailSavedSearch::query()
            ->ownedBy($this->requireUser($request)->id)
            ->orderBy('name')
            ->get();

        return response()->json(['saved_searches' => $searches->map(fn (MailSavedSearch $s): array => $this->present($s))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'filters' => ['required', 'array'],
        ]);

        $search = new MailSavedSearch([
            'name' => $request->string('name')->value(),
            'filters_json' => (array) $request->input('filters', []),
        ]);
        $search->save();

        return response()->json(['saved_search' => $this->present($search)], 201);
    }

    public function destroy(Request $request, MailSavedSearch $search): JsonResponse
    {
        abort_if((int) $search->user_id !== (int) $this->requireUser($request)->id, 404);
        $search->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function present(MailSavedSearch $search): array
    {
        return [
            'id' => $search->id,
            'name' => $search->name,
            'filters' => $search->filters_json,
        ];
    }
}
