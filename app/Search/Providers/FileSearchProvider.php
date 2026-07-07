<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\StoredFile;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;
use Illuminate\Support\Number;

/**
 * Finds files by name or tag. Files are plain database rows now (not
 * zero-knowledge), so the server can search their metadata directly.
 */
class FileSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.files');
    }

    public function search(string $term, int $limit): array
    {
        $query = StoredFile::query();
        // Match the file name/tags OR the extracted full-text content (OCR).
        $this->matchAny($query, ['name', 'content'], $this->wildcard($term), $term);

        return $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (StoredFile $f): SearchResult => new SearchResult(
                group: $this->group(),
                title: $f->name,
                subtitle: Number::fileSize($f->size ?: 0),
                url: route('files.index'),
            ))
            ->all();
    }
}
