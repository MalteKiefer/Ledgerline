<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteShare;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Plain (non-encrypted) markdown notes, rendered server-side. Listing, search
 * and markdown rendering happen in PHP; the page uses plain form posts. Notes
 * can be shared as a frozen, server-rendered public snapshot.
 */
class NoteController extends Controller
{
    /** Public-share lifetimes in seconds: 1h, 1d, 1w, 30d. */
    private const LIFETIMES = [3600, 86400, 604800, 2592000];

    public function index(Request $request): View
    {
        $view = (string) $request->query('view', 'active');
        $tag = (string) $request->query('tag', '');
        $q = trim((string) $request->query('q', ''));

        $query = Note::query();
        $view === 'trash' ? $query->whereNotNull('trashed_at') : $query->whereNull('trashed_at');
        if ($tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('content', 'like', "%{$q}%"));
        }

        $notes = $query->orderByDesc('pinned')->orderByDesc('updated_at')->get();

        $current = $request->filled('open') ? Note::find($request->integer('open')) : null;
        if ($current === null && $request->boolean('new')) {
            $current = new Note(['title' => '', 'pinned' => false]);
        }

        return view('notes.index', [
            'notes' => $notes,
            'allTags' => $this->allTags(),
            'trashCount' => Note::whereNotNull('trashed_at')->count(),
            'view' => $view,
            'activeTag' => $tag,
            'q' => $q,
            'current' => $current,
            'currentHtml' => $current ? $this->render($current->content) : null,
            'lifetimes' => self::LIFETIMES,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $note = Note::create($this->validated($request));

        // JSON caller (the file browser's "migrate markdown to note").
        if ($request->expectsJson()) {
            return response()->json(['id' => $note->id], 201);
        }

        return redirect()->route('notes.index', ['open' => $note->id]);
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $note->update($this->validated($request));

        return redirect()->route('notes.index', ['open' => $note->id]);
    }

    public function togglePin(Note $note): RedirectResponse
    {
        $note->update(['pinned' => ! $note->pinned]);

        return back();
    }

    public function trash(Note $note): RedirectResponse
    {
        $note->update(['trashed_at' => Carbon::now()]);

        return redirect()->route('notes.index');
    }

    public function restore(Note $note): RedirectResponse
    {
        $note->update(['trashed_at' => null]);

        return back();
    }

    public function destroy(Note $note): RedirectResponse
    {
        $note->delete();

        return redirect()->route('notes.index');
    }

    public function emptyTrash(): RedirectResponse
    {
        Note::whereNotNull('trashed_at')->delete();

        return redirect()->route('notes.index', ['view' => 'trash']);
    }

    /* ---- Sharing (frozen server-side snapshot) ---- */

    public function share(Request $request, Note $note): RedirectResponse
    {
        $data = $request->validate([
            'expires_in' => ['required', 'integer', Rule::in(self::LIFETIMES)],
            'max_views' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'password' => ['nullable', 'string', 'max:255'],
            'allow_download' => ['sometimes', 'boolean'],
        ]);

        $share = NoteShare::create([
            'title' => $note->title,
            'content' => (string) $note->content,
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'has_password' => ! empty($data['password']),
            'allow_download' => $request->boolean('allow_download'),
            'max_views' => $data['max_views'] ?? null,
            'expires_at' => Carbon::now()->addSeconds($data['expires_in']),
        ]);

        return redirect()->route('notes.index', ['open' => $note->id])
            ->with('share_url', route('shares.show', $share));
    }

    public function unshare(NoteShare $share): RedirectResponse
    {
        $share->delete();

        return back();
    }

    /* ---- Helpers ---- */

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:200000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        return [
            'title' => trim((string) ($v['title'] ?? '')) ?: __('notes.untitled'),
            'content' => $v['content'] ?? null,
            'tags' => $this->parseTags($v['tags'] ?? null),
            'pinned' => $request->boolean('pinned'),
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
        return Note::whereNotNull('tags')->pluck('tags')
            ->flatMap(fn ($t) => is_array($t) ? $t : [])
            ->unique()->sort()->values()->all();
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
