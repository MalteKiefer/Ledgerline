<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplatePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringTemplatePage */
final class RecurringInvoiceTemplatePageResource extends JsonResource
{
    public function __construct(private readonly RecurringTemplatePage $page)
    {
        parent::__construct($page);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lastPage = max(1, (int) ceil($this->page->total / $this->page->perPage));

        return [
            'data' => array_map(
                static fn ($template): array => (new RecurringInvoiceTemplateResource($template))->resolve($request),
                $this->page->items,
            ),
            'links' => [
                'first' => $request->fullUrlWithQuery(['page' => 1]),
                'last' => $request->fullUrlWithQuery(['page' => $lastPage]),
                'prev' => $this->page->page > 1 ? $request->fullUrlWithQuery(['page' => $this->page->page - 1]) : null,
                'next' => $this->page->page < $lastPage ? $request->fullUrlWithQuery(['page' => $this->page->page + 1]) : null,
            ],
            'meta' => [
                'current_page' => $this->page->page,
                'per_page' => $this->page->perPage,
                'total' => $this->page->total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
