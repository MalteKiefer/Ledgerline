<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Search\SearchManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Global search across all registered entity types.
 *
 * The controller knows nothing about individual entities; it delegates to the
 * configured search providers via the SearchManager.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, SearchManager $manager): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        $groups = $manager->search($term);

        return view('search.index', [
            'term' => $term,
            'groups' => $groups,
            'total' => array_sum(array_map('count', $groups)),
        ]);
    }
}
