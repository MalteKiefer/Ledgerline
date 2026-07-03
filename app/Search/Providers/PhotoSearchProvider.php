<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Photo;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Finds gallery photos by name, original filename, place or camera.
 */
class PhotoSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.photos');
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Photo::query()
            ->where(function ($query) use ($like): void {
                foreach (['name', 'original_name', 'place', 'camera'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            })
            ->orderByDesc('taken_at')
            ->limit($limit)
            ->get()
            ->map(fn (Photo $photo): SearchResult => new SearchResult(
                group: $this->group(),
                title: $photo->name,
                subtitle: trim(($photo->taken_at?->isoFormat('LL') ?? '').($photo->place ? ' · '.$photo->place : '')) ?: null,
                url: route('gallery.index', ['q' => $photo->name]),
            ))
            ->all();
    }
}
