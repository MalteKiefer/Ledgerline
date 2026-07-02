<?php

declare(strict_types=1);

namespace App\Search\Providers;

use App\Models\File;
use App\Search\AbstractSearchProvider;
use App\Search\SearchResult;

/**
 * Global-search source for files.
 *
 * Matches file name, detected type and tags always; also the extracted text of
 * unencrypted files (encrypted files store no extracted_text, so their content
 * is never searched). Team isolation is applied by the File global scope.
 */
class FileSearchProvider extends AbstractSearchProvider
{
    public function group(): string
    {
        return __('search.files');
    }

    public function search(string $term, int $limit): array
    {
        $like = $this->wildcard($term);

        return File::query()
            ->where(function ($query) use ($like): void {
                foreach (['name', 'title', 'description', 'note', 'type', 'extracted_text'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
                $query->orWhereHas('tags', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (File $file): SearchResult => new SearchResult(
                group: $this->group(),
                title: $file->displayTitle,
                subtitle: $file->type->label(),
                url: route('files.show', $file),
            ))
            ->all();
    }
}
