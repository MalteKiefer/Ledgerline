<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Note;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;
use Illuminate\Support\Str;

/**
 * Finds notes by title, content or tag. Notes are plain database rows (not
 * zero-knowledge), so the server can search them directly.
 */
class NoteSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.notes');
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Note::query()
            ->whereNull('trashed_at')
            ->where(function ($query) use ($like, $term): void {
                $query->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(content) LIKE ?', [$like])
                    ->orWhereJsonContains('tags', $term);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Note $note): SearchResult => new SearchResult(
                group: $this->group(),
                title: $note->title ?: __('notes.untitled'),
                subtitle: (string) Str::of((string) $note->content)->stripTags()->limit(80) ?: null,
                url: route('notes.index', ['open' => $note->id]),
            ))
            ->all();
    }
}
