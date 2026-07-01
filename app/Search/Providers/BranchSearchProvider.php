<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Branch;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;
use App\Support\Countries;

/**
 * Global-search source for branch offices (Niederlassungen).
 */
class BranchSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.branches');
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Branch::query()
            ->with('customer')
            ->where(function ($query) use ($like): void {
                foreach (['name', 'city'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Branch $branch): SearchResult => new SearchResult(
                group: $this->group(),
                title: $branch->name,
                subtitle: trim(($branch->city ? $branch->city.' · ' : '').$branch->customer->name)
                    .($branch->country && ($n = Countries::name($branch->country)) ? ' ('.$n.')' : ''),
                url: route('branches.show', $branch),
            ))
            ->all();
    }
}
