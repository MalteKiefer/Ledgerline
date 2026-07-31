<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Plaintext-relational Todos (pivot Phase 1). Lists + tasks as owner-scoped rows,
 * per-row writes in transactions, soft-delete trash. Same shape/UX as the old ZK
 * module; the sealed store is retired.
 */
class TodosController extends Controller
{
    /** The page: inline lists + active tasks (fast first paint). */
    public function page(): View
    {
        return view('todos.index', [
            'lists' => TodoList::query()->orderBy('name')->get(),
            'todos' => Todo::query()->orderBy('done')->orderByDesc('marked')->orderByDesc('updated_at')->get(),
        ]);
    }

    // ---- Lists ----
    public function lists(): JsonResponse
    {
        return response()->json(['lists' => TodoList::query()->orderBy('name')->get()]);
    }

    public function storeList(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:200']]);
        $list = DB::transaction(fn (): TodoList => TodoList::create(['name' => $request->string('name')->value()]));

        return response()->json(['list' => $list], 201);
    }

    public function renameList(Request $request, TodoList $list): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:200']]);
        $list->update(['name' => $request->string('name')->value()]);

        return response()->json(['list' => $list]);
    }

    /** Delete a list; its tasks' FK nulls (they survive, unfiled). */
    public function destroyList(TodoList $list): JsonResponse
    {
        DB::transaction(function () use ($list): void {
            Todo::withTrashed()->where('todo_list_id', $list->id)->update(['todo_list_id' => null]);
            $list->delete();
        });

        return response()->json(['ok' => true]);
    }

    // ---- Tasks ----
    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return [
            'todo_list_id' => ['nullable', 'integer', Rule::exists('todo_lists', 'id')->where('user_id', (int) $this->requireUser($request)->id)],
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', Rule::in(['high', 'normal', 'low'])],
            'marked' => ['sometimes', 'boolean'],
            'done' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'due' => ['nullable', 'date'],
            'recurrence' => ['nullable', Rule::in(['none', ...Todo::RECURRENCES])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        /** @var list<string> $tags */
        $tags = array_values(array_filter($request->array('tags'), static fn ($t): bool => is_string($t)));
        // Only http(s) — a javascript:/data: URL would execute on click.
        $url = $request->filled('url') ? trim($request->string('url')->value()) : '';
        if ($url !== '' && preg_match('#^https?://#i', $url) !== 1) {
            $url = '';
        }

        return [
            'todo_list_id' => $request->filled('todo_list_id') ? $request->integer('todo_list_id') : null,
            'title' => $request->string('title')->value(),
            'description' => $request->filled('description') ? $request->string('description')->value() : null,
            'url' => $url !== '' ? $url : null,
            'priority' => $request->filled('priority') ? $request->string('priority')->value() : 'normal',
            'marked' => $request->boolean('marked'),
            'done' => $request->boolean('done'),
            'tags' => $request->has('tags') ? ($tags !== [] ? $tags : null) : null,
            'due' => $request->filled('due') ? $request->string('due')->value() : null,
            'recurrence' => $request->filled('recurrence') && $request->string('recurrence')->value() !== 'none'
                ? $request->string('recurrence')->value()
                : null,
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['todos' => Todo::query()->orderBy('done')->orderByDesc('marked')->orderByDesc('updated_at')->get()]);
    }

    public function trashed(): JsonResponse
    {
        return response()->json(['todos' => Todo::onlyTrashed()->orderByDesc('deleted_at')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules($request));
        $todo = DB::transaction(fn (): Todo => Todo::create($this->payload($request)));

        return response()->json(['todo' => $todo], 201);
    }

    public function update(Request $request, Todo $todo): JsonResponse
    {
        $request->validate($this->rules($request) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $payload = $this->payload($request);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($todo, $payload, $expected): Todo|bool|null {
            $fresh = Todo::query()->lockForUpdate()->find($todo->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            $wasDone = $fresh->done;
            $fresh->fill($payload);
            $fresh->version = $fresh->version + 1;
            $fresh->save();
            // Recurrence: spawn the next occurrence only on a false->true done flip.
            if (! $wasDone && $fresh->done) {
                $this->spawnNextOccurrence($fresh);
            }

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = Todo::query()->find($todo->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['todo' => $result]);
    }

    /** Quick flag toggles (done / marked) — a cheap single-column write. */
    public function toggle(Request $request, Todo $todo): JsonResponse
    {
        $request->validate(['field' => [Rule::in(['done', 'marked'])], 'value' => ['required', 'boolean']]);
        $field = $request->string('field')->value();
        $value = $request->boolean('value');
        $wasDone = $todo->done;
        $todo->update([$field => $value]);
        // Recurrence: completing a recurring task spawns its next occurrence
        // (only on the false->true done flip, so a re-toggle never double-spawns).
        if ($field === 'done' && $value && ! $wasDone) {
            $this->spawnNextOccurrence($todo);
        }

        return response()->json(['todo' => $todo]);
    }

    /**
     * Copy a just-completed recurring task into its next occurrence: same fields,
     * not done/marked, `due` advanced by the cadence (from the old due, or now if
     * unset). The completed original stays as history but its recurrence is
     * cleared — the successor carries the cadence forward — so re-completing it
     * (done → undone → done) never double-spawns. No-op for one-off tasks. The
     * new row is created through the normal owner-scoped path (user_id stamped).
     */
    private function spawnNextOccurrence(Todo $todo): void
    {
        $cadence = (string) ($todo->recurrence ?? '');
        if (! in_array($cadence, Todo::RECURRENCES, true)) {
            return;
        }

        $base = $todo->due ?? Carbon::now();
        $next = match ($cadence) {
            'daily' => $base->copy()->addDay(),
            'weekly' => $base->copy()->addWeek(),
            'monthly' => $base->copy()->addMonthNoOverflow(),
            default => $base->copy()->addYear(), // 'yearly' (the only remaining cadence)
        };

        Todo::create([
            'todo_list_id' => $todo->todo_list_id,
            'title' => $todo->title,
            'description' => $todo->description,
            'url' => $todo->url,
            'priority' => $todo->priority,
            'tags' => $todo->tags,
            'recurrence' => $cadence,
            'due' => $next,
            'marked' => false,
            'done' => false,
        ]);

        $todo->recurrence = null;
        $todo->saveQuietly();
    }

    public function destroy(Todo $todo): JsonResponse
    {
        $todo->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $todo = Todo::onlyTrashed()->findOrFail($id);
        $todo->restore();

        return response()->json(['todo' => $todo]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        Todo::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        $n = 0;
        Todo::onlyTrashed()->chunkById(200, function ($chunk) use (&$n): void {
            foreach ($chunk as $todo) {
                $todo->forceDelete();
                $n++;
            }
        });

        return response()->json(['deleted' => $n]);
    }
}
