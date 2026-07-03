<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\Todo;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Finds to-dos by title, description or tag. To-dos are plain database rows
 * (not zero-knowledge), so the server can search them directly.
 */
class TodoSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.todos');
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return Todo::query()
            ->whereNull('trashed_at')
            ->where(function ($query) use ($like, $term): void {
                $query->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereJsonContains('tags', $term);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Todo $todo): SearchResult => new SearchResult(
                group: $this->group(),
                title: $todo->title,
                subtitle: $todo->done ? __('todos.done') : ($todo->due_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?: null),
                url: route('todos.index', ['edit' => $todo->id]),
            ))
            ->all();
    }
}
