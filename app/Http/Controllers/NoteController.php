<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteShare;
use App\Support\Tags;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Plain (non-encrypted) markdown notes, exposed as a JSON API so the browser
 * renders and mutates them without page reloads. Markdown rendering is done
 * SERVER-SIDE (a security-sensitive step: HTML is escaped and links sanitised),
 * as is share creation (password hashing) — everything else is client-side.
 */
class NoteController extends Controller
{
    private const LIFETIMES = [3600, 86400, 604800, 2592000];

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
        // Owner-only: a bulk builder forceDelete bypasses the SharesWithUsers
        // write guard, so pin it to the caller's own notes (never shared ones).
        Note::withoutGlobalScopes()->where('user_id', auth()->id())->onlyTrashed()->forceDelete();

        return response()->json(['ok' => true]);
    }

    /** Server-side markdown render (escaped + sanitised). */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['content' => ['nullable', 'string', 'max:200000']]);

        return response()->json(['html' => self::render($data['content'] ?? '')]);
    }

    /* ---- Sharing (server-side: frozen snapshot + hashed password) ---- */

    public function share(Request $request, Note $note): JsonResponse
    {
        $data = $request->validate([
            'expires_in' => ['required', 'integer', Rule::in(self::LIFETIMES)],
            'max_views' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'allow_download' => ['sometimes', 'boolean'],
        ]);

        $share = new NoteShare([
            'title' => $note->title,
            'content' => (string) $note->content,
            'allow_download' => $request->boolean('allow_download'),
            'max_views' => $data['max_views'] ?? null,
            'expires_at' => Carbon::now()->addSeconds($data['expires_in']),
        ]);
        // The password fields are guarded (never mass-assignable) so a client
        // can never set has_password=false to bypass the gate; set them here.
        $share->forceFill([
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'has_password' => ! empty($data['password']),
        ])->save();

        return response()->json(['url' => route('shares.show', $share)]);
    }

    /* ---- Helpers ---- */

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:200000'],
            'pinned' => ['sometimes', 'boolean'],
            ...Tags::rules(),
        ]);

        return [
            'title' => trim((string) ($v['title'] ?? '')) ?: __('notes.untitled'),
            'content' => $v['content'] ?? null,
            'tags' => Tags::normalize($v['tags'] ?? null),
            'pinned' => (bool) ($v['pinned'] ?? false),
        ];
    }

    /** @return array<string,mixed> */
    private function toArray(Note $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'content' => $n->content,
            'tags' => $n->tags ?? [],
            'pinned' => (bool) $n->pinned,
            'trashed' => $n->trashed(),
            'updated' => $n->updated_at?->toIso8601String(),
        ];
    }

    /** Render markdown to sanitised HTML (safe mode strips raw HTML). */
    public static function render(?string $markdown): string
    {
        if ($markdown === null || $markdown === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
