<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Project;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Global-search source for projects.
 */
class ProjectSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return 'Projects';
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Project::query()
            ->with('customer')
            ->where(function ($query) use ($like): void {
                foreach (['name', 'reference'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Project $project): SearchResult => new SearchResult(
                group: $this->group(),
                title: $project->name,
                subtitle: $project->status->label().' · '.$project->customer->name,
                url: route('projects.show', $project),
            ))
            ->all();
    }
}
