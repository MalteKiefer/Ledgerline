<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuotePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuotePage */
final class QuotePageResource extends JsonResource
{
    public function __construct(private readonly QuotePage $page)
    {
        parent::__construct($page);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lastPage = max(1, (int) ceil($this->page->total / $this->page->perPage));

        return [
            'data' => array_map(
                static fn ($quote): array => (new QuoteResource($quote))->resolve($request),
                $this->page->items,
            ),
            'links' => [
                'first' => $this->pageUrl($request, 1),
                'last' => $this->pageUrl($request, $lastPage),
                'prev' => $this->page->page > 1 ? $this->pageUrl($request, $this->page->page - 1) : null,
                'next' => $this->page->page < $lastPage ? $this->pageUrl($request, $this->page->page + 1) : null,
            ],
            'meta' => [
                'current_page' => $this->page->page,
                'per_page' => $this->page->perPage,
                'total' => $this->page->total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function pageUrl(Request $request, int $page): string
    {
        return $request->fullUrlWithQuery(['page' => $page]);
    }
}
