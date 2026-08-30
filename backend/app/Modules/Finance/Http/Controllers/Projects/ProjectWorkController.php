<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Projects;

use App\Modules\Finance\Application\Commands\Projects\CreateWorkItem;
use App\Modules\Finance\Application\Commands\Projects\DeleteProjectTime;
use App\Modules\Finance\Application\Commands\Projects\DeleteWorkItem;
use App\Modules\Finance\Application\Commands\Projects\LogProjectTime;
use App\Modules\Finance\Application\Commands\Projects\ReorderWorkItems;
use App\Modules\Finance\Application\Commands\Projects\UpdateProjectTime;
use App\Modules\Finance\Application\Commands\Projects\UpdateWorkItem;
use App\Modules\Finance\Application\DTOs\Projects\TimeEntryView;
use App\Modules\Finance\Application\DTOs\Projects\WorkItemView;
use App\Modules\Finance\Application\Queries\Projects\ListProjectWork;
use App\Modules\Finance\Http\Requests\Projects\ProjectWorkRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectWorkResource;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class ProjectWorkController extends ProjectController
{
    public function workItems(ProjectWorkRequest $request, string $project, ListProjectWork $query): JsonResponse
    {
        $page = $this->workPage(
            $query->handle($this->projectId($request, $project), 'work_items', $request->page(), $request->perPage()),
            WorkItemView::class,
        );

        return response()->json((new ProjectWorkResource($page))->resolve($request));
    }

    public function timeEntries(ProjectWorkRequest $request, string $project, ListProjectWork $query): JsonResponse
    {
        $page = $this->workPage(
            $query->handle($this->projectId($request, $project), 'time_entries', $request->page(), $request->perPage()),
            TimeEntryView::class,
        );

        return response()->json((new ProjectWorkResource($page))->resolve($request));
    }

    public function storeWorkItem(ProjectWorkRequest $request, string $project, CreateWorkItem $command): JsonResponse
    {
        return $this->run($request, fn () => $command->handle($request->createWorkData($this->projectId($request, $project))), 201);
    }

    public function updateWorkItem(ProjectWorkRequest $request, string $project, string $workItem, UpdateWorkItem $command): JsonResponse
    {
        return $this->run($request, fn () => $command->handle($request->updateWorkData($this->projectId($request, $project), $workItem)));
    }

    public function storeTime(ProjectWorkRequest $request, string $project, LogProjectTime $command): JsonResponse
    {
        return $this->run($request, fn () => $command->handle($request->createTimeData($this->projectId($request, $project))), 201);
    }

    public function updateTime(ProjectWorkRequest $request, string $project, string $entry, UpdateProjectTime $command): JsonResponse
    {
        return $this->run($request, fn () => $command->handle($request->updateTimeData($this->projectId($request, $project), $entry)));
    }

    public function deleteWorkItem(ProjectWorkRequest $request, string $project, string $workItem, DeleteWorkItem $command): JsonResponse
    {
        return $this->remove($request, fn () => $command->handle($this->projectId($request, $project), $workItem, $request->expectedVersion(), $this->ownerId($request), new DateTimeImmutable));
    }

    public function deleteTime(ProjectWorkRequest $request, string $project, string $entry, DeleteProjectTime $command): JsonResponse
    {
        return $this->remove($request, fn () => $command->handle($this->projectId($request, $project), $entry, $request->expectedVersion(), $this->ownerId($request), new DateTimeImmutable));
    }

    public function reorderWorkItems(ProjectWorkRequest $request, string $project, ReorderWorkItems $command): JsonResponse
    {
        try {
            $items = $command->handle($this->projectId($request, $project), $request->ids(), $this->ownerId($request), new DateTimeImmutable);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        $data = [];
        foreach ($items as $item) {
            if (! $item instanceof WorkItemView) {
                throw new \LogicException('Reorder command returned an invalid work item.');
            }
            $data[] = (new ProjectWorkResource($item))->resolve($request);
        }

        return response()->json(['data' => $data]);
    }

    /** @param \Closure(): (WorkItemView|TimeEntryView) $operation */
    private function run(ProjectWorkRequest $request, \Closure $operation, int $status = 200): JsonResponse
    {
        try {
            $value = $operation();
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json((new ProjectWorkResource($value))->resolve($request), $status);
    }

    /** @param \Closure(): void $operation */
    private function remove(ProjectWorkRequest $request, \Closure $operation): JsonResponse
    {
        try {
            $operation();
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json(null, 204);
    }

    /**
     * @param  array<mixed, mixed>  $page
     * @param  class-string<WorkItemView|TimeEntryView>  $itemType
     * @return array{items: list<WorkItemView|TimeEntryView>, page: int, per_page: int, total: int}
     */
    private function workPage(array $page, string $itemType): array
    {
        $items = $page['items'] ?? null;
        if (! is_array($items)) {
            throw new \LogicException('Project work query returned an invalid item page.');
        }

        $typedItems = [];
        foreach ($items as $item) {
            if (! $item instanceof $itemType) {
                throw new \LogicException('Project work query returned an invalid item type.');
            }
            $typedItems[] = $item;
        }

        $currentPage = $page['page'] ?? null;
        $perPage = $page['per_page'] ?? null;
        $total = $page['total'] ?? null;
        if (! is_int($currentPage) || ! is_int($perPage) || ! is_int($total)) {
            throw new \LogicException('Project work query returned invalid pagination metadata.');
        }

        return ['items' => $typedItems, 'page' => $currentPage, 'per_page' => $perPage, 'total' => $total];
    }
}
