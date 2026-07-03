<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoList;
use App\Support\Tags;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plain (non-encrypted) to-do lists and tasks, exposed as a JSON API so the
 * browser can render and mutate everything without page reloads. Only the
 * security/performance-sensitive bits stay on the server: reminder rows are
 * managed here (a task with a due date + channels upserts a reminder), and
 * outbound reminder URLs are restricted to http(s).
 */
class TodoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'lists' => TodoList::orderBy('name')->get(['id', 'name']),
            'tasks' => Todo::orderByDesc('created_at')->get()->map(fn (Todo $t) => $this->toArray($t)),
        ]);
    }

    /* ---- Lists ---- */

    public function storeList(Request $request): JsonResponse
    {
        $list = TodoList::create($request->validate(['name' => ['required', 'string', 'max:120']]));

        return response()->json(['id' => $list->id, 'name' => $list->name]);
    }

    public function updateList(Request $request, TodoList $list): JsonResponse
    {
        $list->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return response()->json(['ok' => true]);
    }

    public function destroyList(TodoList $list): JsonResponse
    {
        $list->delete(); // tasks fall back to "no list" (FK nulls out)

        return response()->json(['ok' => true]);
    }

    /* ---- Tasks ---- */

    public function store(Request $request): JsonResponse
    {
        $todo = Todo::create($this->validated($request));
        $this->syncReminder($todo);

        return response()->json($this->toArray($todo->refresh()));
    }

    public function update(Request $request, Todo $todo): JsonResponse
    {
        $todo->update($this->validated($request));
        $this->syncReminder($todo);

        return response()->json($this->toArray($todo->refresh()));
    }

    public function patch(Request $request, Todo $todo): JsonResponse
    {
        // Lightweight toggles (done/marked/trashed) without the full payload.
        $data = $request->validate([
            'done' => ['sometimes', 'boolean'],
            'marked' => ['sometimes', 'boolean'],
            'trashed' => ['sometimes', 'boolean'],
        ]);
        if ($request->has('done')) {
            $todo->done = $request->boolean('done');
        }
        if ($request->has('marked')) {
            $todo->marked = $request->boolean('marked');
        }
        if ($request->has('trashed')) {
            $todo->trashed_at = $request->boolean('trashed') ? Carbon::now() : null;
        }
        $todo->save();
        $this->syncReminder($todo);

        return response()->json($this->toArray($todo->refresh()));
    }

    public function destroy(Todo $todo): JsonResponse
    {
        $todo->delete();

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        Todo::whereNotNull('trashed_at')->get()->each->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'todo_list_id' => ['nullable', 'exists:todo_lists,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:2048'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'marked' => ['sometimes', 'boolean'],
            'due' => ['nullable', 'date'],
            ...Tags::rules(),
            'reminder_channels' => ['array'],
            'reminder_channels.*' => [Rule::in(Reminder::CHANNELS)],
            'done' => ['sometimes', 'boolean'],
        ]);

        return [
            'todo_list_id' => $v['todo_list_id'] ?? null,
            'title' => $v['title'],
            'description' => $v['description'] ?? null,
            'url' => $v['url'] ?? null,
            'priority' => $v['priority'],
            'marked' => (bool) ($v['marked'] ?? false),
            'tags' => Tags::normalize($v['tags'] ?? null),
            'due_at' => ! empty($v['due']) ? Carbon::parse($v['due'], config('app.timezone')) : null,
            'reminder_channels' => array_values($v['reminder_channels'] ?? []),
            'done' => (bool) ($v['done'] ?? false),
        ];
    }

    private function syncReminder(Todo $todo): void
    {
        $channels = $todo->reminder_channels ?? [];
        $wants = $todo->due_at !== null && $channels !== [] && ! $todo->done && $todo->trashed_at === null;

        if (! $wants) {
            Reminder::where('todo_id', $todo->id)->delete();

            return;
        }

        Reminder::updateOrCreate(
            ['todo_id' => $todo->id],
            [
                'due_at' => $todo->due_at,
                'channels' => $channels,
                'title' => $todo->title,
                'url' => $this->safeUrl($todo->url),
                'fired_at' => null,
            ],
        );
    }

    private function safeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    /** @return array<string,mixed> */
    private function toArray(Todo $t): array
    {
        return [
            'id' => $t->id,
            'listId' => $t->todo_list_id,
            'title' => $t->title,
            'description' => $t->description,
            'url' => $t->url,
            'priority' => $t->priority,
            'marked' => (bool) $t->marked,
            'tags' => $t->tags ?? [],
            'due' => $t->due_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'reminderChannels' => $t->reminder_channels ?? [],
            'done' => (bool) $t->done,
            'trashed' => $t->trashed_at !== null,
        ];
    }
}
