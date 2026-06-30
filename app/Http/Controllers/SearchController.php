<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Search\SearchManager;
use App\Search\SearchResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search across all registered entity types.
 *
 * The controller delegates to the SearchManager and knows nothing about
 * individual entities. It serves both the full results page and the live JSON
 * suggestions consumed by the Spotlight-style palette.
 */
class SearchController extends Controller
{
    /**
     * Maximum rows per group for the live suggestion palette.
     */
    private const SUGGEST_LIMIT = 5;

    /**
     * Render the full search results page.
     */
    public function index(Request $request, SearchManager $manager): View
    {
        $term = $this->term($request);
        $groups = $manager->search($term);

        return view('search.index', [
            'term' => $term,
            'groups' => $groups,
            'total' => array_sum(array_map('count', $groups)),
        ]);
    }

    /**
     * Return live suggestions as JSON for the search palette.
     */
    public function suggest(Request $request, SearchManager $manager): JsonResponse
    {
        $term = $this->term($request);
        $groups = $manager->search($term, self::SUGGEST_LIMIT);

        $payload = [];

        foreach ($groups as $group => $results) {
            $payload[] = [
                'group' => $group,
                'results' => array_map(static fn (SearchResult $result): array => [
                    'title' => $result->title,
                    'subtitle' => $result->subtitle ?? '',
                    'url' => $result->url,
                ], $results),
            ];
        }

        return response()->json(['groups' => $payload]);
    }

    /**
     * Validate and normalise the search term.
     */
    private function term(Request $request): string
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        return trim((string) ($validated['q'] ?? ''));
    }
}
