<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Bookmark;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Finds bookmarks by title, URL or tag. Bookmarks are plain database rows (not
 * zero-knowledge), so the server can search them directly.
 */
class BookmarkSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.bookmarks');
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Bookmark::query()
            ->whereNull('trashed_at')
            ->where(function ($query) use ($like, $term): void {
                $query->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(url) LIKE ?', [$like])
                    ->orWhereJsonContains('tags', $term);
            })
            ->orderByDesc('favorite')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Bookmark $b): SearchResult => new SearchResult(
                group: $this->group(),
                title: $b->title,
                subtitle: $b->url,
                url: $b->url,
            ))
            ->all();
    }
}
