<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoList;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plain (non-encrypted) to-do lists and tasks, rendered server-side. Filtering,
 * sorting and search all happen in PHP; the page uses plain form posts, so the
 * client ships almost no JavaScript. Reminders are managed here: a task with a
 * due date and channels upserts a reminder row, otherwise it is removed.
 */
class TodoController extends Controller
{
    private const PRIORITY_ORDER = ['high' => 0, 'normal' => 1, 'low' => 2];

    public function index(Request $request): View
    {
        $view = (string) $request->query('view', 'all');
        $tag = (string) $request->query('tag', '');
        $q = trim((string) $request->query('q', ''));

        $query = Todo::query();
        $view === 'trash' ? $query->whereNotNull('trashed_at') : $query->whereNull('trashed_at');
        if ($view === 'marked') {
            $query->where('marked', true);
        } elseif (str_starts_with($view, 'list:')) {
            $query->where('todo_list_id', (int) substr($view, 5));
        }
        if ($tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"));
        }

        $tasks = $query->get()
            ->sort(fn (Todo $a, Todo $b) => [(int) $a->done, -(int) $a->marked, self::PRIORITY_ORDER[$a->priority] ?? 1, $a->due_at?->timestamp ?? PHP_INT_MAX]
                <=> [(int) $b->done, -(int) $b->marked, self::PRIORITY_ORDER[$b->priority] ?? 1, $b->due_at?->timestamp ?? PHP_INT_MAX])
            ->values();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = Todo::find($request->integer('edit'));
        } elseif ($request->boolean('new')) {
            $editing = new Todo(['priority' => 'normal']);
        }

        return view('todos.index', [
            'lists' => TodoList::orderBy('name')->get(),
            'tasks' => $tasks,
            'allTags' => $this->allTags(),
            'trashCount' => Todo::whereNotNull('trashed_at')->count(),
            'view' => $view,
            'activeTag' => $tag,
            'q' => $q,
            'editing' => $editing,
        ]);
    }

    /* ---- Lists ---- */

    public function storeList(Request $request): RedirectResponse
    {
        TodoList::create($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back();
    }

    public function updateList(Request $request, TodoList $list): RedirectResponse
    {
        $list->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back();
    }

    public function destroyList(TodoList $list): RedirectResponse
    {
        $list->delete(); // tasks fall back to "no list" (FK nulls out)

        return redirect()->route('todos.index');
    }

    /* ---- Tasks ---- */

    public function store(Request $request): RedirectResponse
    {
        $todo = Todo::create($this->validated($request));
        $this->syncReminder($todo);

        return redirect()->route('todos.index');
    }

    public function update(Request $request, Todo $todo): RedirectResponse
    {
        $todo->update($this->validated($request));
        $this->syncReminder($todo);

        return redirect()->route('todos.index');
    }

    public function toggleDone(Todo $todo): RedirectResponse
    {
        $todo->update(['done' => ! $todo->done]);
        $this->syncReminder($todo);

        return back();
    }

    public function toggleMark(Todo $todo): RedirectResponse
    {
        $todo->update(['marked' => ! $todo->marked]);

        return back();
    }

    public function trash(Todo $todo): RedirectResponse
    {
        $todo->update(['trashed_at' => Carbon::now()]);
        $this->syncReminder($todo);

        return back();
    }

    public function restore(Todo $todo): RedirectResponse
    {
        $todo->update(['trashed_at' => null]);
        $this->syncReminder($todo);

        return back();
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $todo->delete(); // cascade drops its reminder

        return back();
    }

    public function emptyTrash(): RedirectResponse
    {
        Todo::whereNotNull('trashed_at')->get()->each->delete();

        return redirect()->route('todos.index');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'todo_list_id' => ['nullable', 'integer', 'exists:todo_lists,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:2048'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'marked' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
            'due' => ['nullable', 'date'],
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
            'marked' => $request->boolean('marked'),
            'tags' => $this->parseTags($v['tags'] ?? null),
            'due_at' => ! empty($v['due']) ? Carbon::parse($v['due'], config('app.timezone')) : null,
            'reminder_channels' => array_values($v['reminder_channels'] ?? []),
            'done' => $request->boolean('done'),
        ];
    }

    /** @return list<string> */
    private function parseTags(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }

    /** @return list<string> */
    private function allTags(): array
    {
        return Todo::whereNotNull('tags')->pluck('tags')
            ->flatMap(fn ($t) => is_array($t) ? $t : [])
            ->unique()->sort()->values()->all();
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

    /** Only http(s) links reach outbound notifications. */
    private function safeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
