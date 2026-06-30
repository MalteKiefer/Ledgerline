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
                $query->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(type) LIKE ?', [$like])
                    ->orWhereHas('tags', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Project $project): SearchResult => new SearchResult(
                group: $this->group(),
                title: $project->name,
                subtitle: $project->type->label().' · '.$project->status->label().' · '.$project->customer->name,
                url: route('projects.show', $project),
            ))
            ->all();
    }
}
