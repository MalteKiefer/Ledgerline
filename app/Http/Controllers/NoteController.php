<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PurgesOwnedTrash;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zero-knowledge markdown notes, exposed as a JSON API. The browser seals each
 * note's {title, content, tags} with the per-user vault key and renders the
 * markdown itself; the server only stores + returns ciphertext (enc_note). No
 * server-side markdown render, search or public snapshot — those need the
 * plaintext the server never has.
 */
class NoteController extends Controller
{
    use PurgesOwnedTrash;

    public function index(): JsonResponse
    {
        return response()->json([
            'notes' => Note::withTrashed()->orderByDesc('pinned')->orderByDesc('updated_at')->get()->map(fn (Note $n) => $this->toArray($n)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $note = Note::create($this->validated($request));

        return response()->json($this->toArray($note), 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $note->update($this->validated($request));

        return response()->json($this->toArray($note->refresh()));
    }

    public function patch(Request $request, Note $note): JsonResponse
    {
        $request->validate(['pinned' => ['sometimes', 'boolean'], 'trashed' => ['sometimes', 'boolean']]);
        if ($request->has('pinned')) {
            $note->pinned = $request->boolean('pinned');
            $note->save();
        }
        if ($request->has('trashed')) {
            $request->boolean('trashed') ? $note->delete() : $note->restore();
        }

        return response()->json($this->toArray($note));
    }

    public function destroy(Note $note): JsonResponse
    {
        $note->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        return $this->emptyOwnedTrash(Note::class);
    }

    /* ---- Helpers ---- */

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        // Zero-knowledge: the browser seals {title, content, tags} into enc_note;
        // the plaintext columns are never received. pinned stays a plaintext
        // ordering flag.
        $v = $request->validate([
            'enc_note' => ['required', 'string', 'max:400000'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        return [
            'title' => null,
            'content' => null,
            'tags' => null,
            'enc_note' => $v['enc_note'],
            'is_encrypted' => true,
            'pinned' => (bool) ($v['pinned'] ?? false),
        ];
    }

    /** @return array<string,mixed> */
    private function toArray(Note $n): array
    {
        return [
            'id' => $n->id,
            // Sealed {title, content, tags}; decrypted client-side.
            'enc_note' => $n->enc_note,
            'pinned' => (bool) $n->pinned,
            'trashed' => $n->trashed(),
            'updated' => $n->updated_at?->toIso8601String(),
        ];
    }
}
