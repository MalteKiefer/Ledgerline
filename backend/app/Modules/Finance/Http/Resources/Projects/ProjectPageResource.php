<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectPageResource extends JsonResource
{
    public function __construct(private readonly ProjectPage $page)
    {
        parent::__construct($page);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $last = max(1, (int) ceil($this->page->total / $this->page->perPage));

        return [
            'data' => array_map(static fn ($project): array => (new ProjectResource($project))->resolve($request), $this->page->items),
            'meta' => [
                'current_page' => $this->page->page,
                'per_page' => $this->page->perPage,
                'total' => $this->page->total,
                'last_page' => $last,
            ],
            'links' => [
                'first' => $this->url($request, 1),
                'last' => $this->url($request, $last),
                'prev' => $this->page->page > 1 ? $this->url($request, $this->page->page - 1) : null,
                'next' => $this->page->page < $last ? $this->url($request, $this->page->page + 1) : null,
            ],
        ];
    }

    private function url(Request $request, int $page): string
    {
        return $request->url().'?'.http_build_query([...$request->query(), 'page' => $page]);
    }
}
