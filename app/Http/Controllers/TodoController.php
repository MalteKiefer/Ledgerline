<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PurgesOwnedTrash;
use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoList;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Zero-knowledge to-do lists and tasks over a JSON API. The browser seals
 * {title, description, url, tags} into enc_todo (+ sealed list names); the server
 * stores only ciphertext plus scheduling/sort metadata (priority, marked, due_at,
 * done, list, reminder channels). Reminders schedule off due_at + channels only —
 * no readable content — and fire a generic "a to-do is due" message.
 */
class TodoController extends Controller
{
    use PurgesOwnedTrash;

    public function index(): JsonResponse
    {
        return response()->json([
            // name is the sealed {c,n} string; the client decrypts + sorts it.
            'lists' => TodoList::orderBy('id')->get(['id', 'name', 'is_encrypted']),
            'tasks' => Todo::withTrashed()->orderByDesc('created_at')->get()->map(fn (Todo $t) => $this->toArray($t)),
        ]);
    }

    /* ---- Lists ---- */

    public function storeList(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:4096']]);
        $list = TodoList::create([...$data, 'is_encrypted' => true]);

        return response()->json(['id' => $list->id, 'name' => $list->name, 'is_encrypted' => true]);
    }

    public function updateList(Request $request, TodoList $list): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:4096']]);
        $list->update([...$data, 'is_encrypted' => true]);

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
        if ($todo->isDirty()) {
            $todo->save();
        }
        if ($request->has('trashed')) {
            $request->boolean('trashed') ? $todo->delete() : $todo->restore();
        }
        $this->syncReminder($todo);

        return response()->json($this->toArray($todo));
    }

    public function destroy(Todo $todo): JsonResponse
    {
        $todo->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        return $this->emptyOwnedTrash(Todo::class);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'todo_list_id' => ['nullable', Rule::exists('todo_lists', 'id')->where('user_id', $request->user()->id)],
            // Sealed {title, description, url, tags} — opaque ciphertext.
            'enc_todo' => ['required', 'string', 'max:400000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'marked' => ['sometimes', 'boolean'],
            'due' => ['nullable', 'date'],
            'reminder_channels' => ['array'],
            'reminder_channels.*' => [Rule::in(Reminder::CHANNELS)],
            'done' => ['sometimes', 'boolean'],
        ]);

        return [
            'todo_list_id' => $v['todo_list_id'] ?? null,
            'title' => null,
            'description' => null,
            'url' => null,
            'tags' => null,
            'enc_todo' => $v['enc_todo'],
            'is_encrypted' => true,
            'priority' => $v['priority'],
            'marked' => (bool) ($v['marked'] ?? false),
            'due_at' => ! empty($v['due']) ? Carbon::parse($v['due'], config('app.timezone')) : null,
            'reminder_channels' => array_values($v['reminder_channels'] ?? []),
            'done' => (bool) ($v['done'] ?? false),
        ];
    }

    private function syncReminder(Todo $todo): void
    {
        $channels = $todo->reminder_channels ?? [];
        $wants = $todo->due_at !== null && $channels !== [] && ! $todo->done && ! $todo->trashed();

        if (! $wants) {
            Reminder::where('todo_id', $todo->id)->delete();

            return;
        }

        // No title/url — the content is sealed; the reminder only schedules.
        Reminder::updateOrCreate(
            ['todo_id' => $todo->id],
            ['due_at' => $todo->due_at, 'channels' => $channels, 'fired_at' => null],
        );
    }

    /** @return array<string,mixed> */
    private function toArray(Todo $t): array
    {
        return [
            'id' => $t->id,
            'listId' => $t->todo_list_id,
            'enc_todo' => $t->enc_todo,
            'priority' => $t->priority,
            'marked' => (bool) $t->marked,
            'due' => $t->due_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'reminderChannels' => $t->reminder_channels ?? [],
            'done' => (bool) $t->done,
            'trashed' => $t->trashed(),
        ];
    }
}
