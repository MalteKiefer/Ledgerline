<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Plaintext-relational Notes (pivot Phase 1). Per-row CRUD in DB transactions,
 * owner-scoped by the model's global scope, soft-delete trash + restore, and a
 * one-shot bulk import for the in-browser ZK→plaintext migration bridge.
 *
 * Every write is a single-row INSERT/UPDATE — no whole-collection re-serialize,
 * so the opaque-blob last-writer-wins loss class cannot occur here.
 */
class NotesController extends Controller
{
    /**
     * Validation rules shared by store/update.
     *
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:1000000'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Editable attributes pulled from the request as a typed array.
     *
     * @return array{title: string|null, body: string|null, tags: list<string>|null, pinned: bool}
     */
    private function payload(Request $request): array
    {
        /** @var list<string> $tags */
        $tags = array_values(array_filter(
            $request->array('tags'),
            static fn ($t): bool => is_string($t),
        ));

        return [
            'title' => $request->filled('title') ? $request->string('title')->value() : null,
            'body' => $request->filled('body') ? $request->string('body')->value() : null,
            'tags' => $request->has('tags') ? $tags : null,
            'pinned' => $request->boolean('pinned'),
        ];
    }

    /** The notes page: server-render the shell + inline the active notes (fast first paint). */
    public function page(): View
    {
        $notes = Note::query()->orderByDesc('pinned')->orderByDesc('updated_at')->get();

        return view('notes.index', ['notes' => $notes]);
    }

    /** Active notes, newest-updated first (pinned on top). Optional ?q= search. */
    public function index(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());
        $notes = Note::query()
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(function ($w) use ($like): void {
                    $w->where('title', 'like', $like)->orWhere('body', 'like', $like);
                });
            })
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['notes' => $notes]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules());
        $payload = $this->payload($request);
        $note = DB::transaction(fn (): Note => Note::create($payload));

        return response()->json(['note' => $note], 201);
    }

    /**
     * Update one note. Optimistic concurrency: if the client sends the version it
     * based its edit on and the row moved on since, reject with 409 (no silent
     * last-writer-wins on the rare concurrent-tab case).
     */
    public function update(Request $request, Note $note): JsonResponse
    {
        $request->validate($this->rules() + ['version' => ['sometimes', 'integer', 'min:0']]);
        $payload = $this->payload($request);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($note, $payload, $expected): Note|bool|null {
            $fresh = Note::query()->lockForUpdate()->find($note->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false; // conflict sentinel
            }
            $fresh->fill($payload);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = Note::query()->find($note->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['note' => $result]);
    }

    /** Soft-delete (move to trash). */
    public function destroy(Note $note): JsonResponse
    {
        $note->delete();

        return response()->json(['ok' => true]);
    }

    /** Trash listing. */
    public function trashed(): JsonResponse
    {
        return response()->json(['notes' => Note::onlyTrashed()->orderByDesc('deleted_at')->get()]);
    }

    /** Restore from trash. */
    public function restore(int $id): JsonResponse
    {
        $note = Note::onlyTrashed()->findOrFail($id);
        $note->restore();

        return response()->json(['note' => $note]);
    }

    /** Permanently delete a trashed note. */
    public function forceDelete(int $id): JsonResponse
    {
        Note::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    /** Empty the trash (permanent). */
    public function emptyTrash(): JsonResponse
    {
        $n = 0;
        Note::onlyTrashed()->chunkById(200, function ($chunk) use (&$n): void {
            foreach ($chunk as $note) {
                $note->forceDelete();
                $n++;
            }
        });

        return response()->json(['deleted' => $n]);
    }
}
