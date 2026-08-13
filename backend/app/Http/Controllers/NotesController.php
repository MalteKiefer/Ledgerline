<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Models\Note;
use App\Models\NoteAttachment;
use App\Models\NoteFolder;
use App\Models\NoteLink;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Notes module (plaintext-relational). One guard-agnostic controller mounted on
 * both web (session) and /api/v1 (device token) — owner-scope is enforced by the
 * Note/NoteFolder global scope (OwnsUserData). Per-note updates are optimistic
 * (version → 409). Search uses the Postgres GIN index with a sqlite LIKE fallback.
 */
class NotesController extends Controller
{
    private const SEARCH_LIMIT = 100;

    /** Folder tree (flat) + lightweight note list (no body) + tag aggregate. */
    public function data(Request $request): JsonResponse
    {
        $this->requireUser($request);

        $folders = NoteFolder::query()->orderBy('position')->orderBy('name')->get()
            ->map(fn (NoteFolder $f): array => [
                'id' => $f->id,
                'parent_id' => $f->parent_id,
                'name' => $f->name,
                'color' => $f->color,
                'position' => $f->position,
                'version' => $f->version,
            ])->all();

        $notes = Note::query()->orderByDesc('pinned')->orderByDesc('updated_at')->get()
            ->map(fn (Note $n): array => $this->row($n))->all();

        $tags = [];
        foreach ($notes as $n) {
            foreach ($n['tags'] as $t) {
                $tags[$t] = ($tags[$t] ?? 0) + 1;
            }
        }
        ksort($tags);

        return response()->json([
            'folders' => $folders,
            'notes' => $notes,
            'tags' => array_map(fn (string $k, int $v): array => ['name' => $k, 'count' => $v], array_keys($tags), array_values($tags)),
        ]);
    }

    /** Trashed notes + folders (the recycle-bin view). */
    public function trash(Request $request): JsonResponse
    {
        $this->requireUser($request);

        $notes = Note::onlyTrashed()->orderByDesc('deleted_at')->get()->map(fn (Note $n): array => $this->row($n))->all();
        $folders = NoteFolder::onlyTrashed()->orderByDesc('deleted_at')->get()
            ->map(fn (NoteFolder $f): array => ['id' => $f->id, 'name' => $f->name])->all();

        return response()->json(['notes' => $notes, 'folders' => $folders]);
    }

    /** Full note (with body) + backlinks for the editor. */
    public function show(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);

        return response()->json(['note' => [
            ...$this->row($note),
            'body' => $note->body ?? '',
            'backlinks' => $this->backlinksFor($note),
            'attachments' => $this->attachmentsFor($note),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $note = Note::create($this->validated($request, creating: true));
        $this->syncLinks($note);
        $this->resolveDangling($note);

        return response()->json(['note' => [...$this->row($note), 'body' => $note->body ?? '', 'backlinks' => $this->backlinksFor($note)]], 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(Note::class, $note->id, $this->validated($request, creating: false), $expected);
        if ($result instanceof Note) {
            $this->syncLinks($result);
            $this->resolveDangling($result); // title may have changed → re-point dangling links
        }

        return $this->optimisticJson($result, Note::class, $note->id);
    }

    /** Backlinks: notes that link to this one (JSON list, on demand). */
    public function backlinks(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);

        return response()->json(['backlinks' => $this->backlinksFor($note)]);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);
        $note->delete();

        return response()->json(['ok' => true]);
    }

    public function favorite(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);
        $note->forceFill(['favorite' => $request->boolean('favorite')])->save();

        return response()->json(['ok' => true]);
    }

    public function pin(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);
        $note->forceFill(['pinned' => $request->boolean('pinned')])->save();

        return response()->json(['ok' => true]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->requireUser($request);
        Note::onlyTrashed()->whereKey($id)->restore();

        return response()->json(['ok' => true]);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->requireUser($request);
        // Unlink attachment blobs before the row cascade removes their records.
        foreach (NoteAttachment::query()->where('note_id', $id)->get() as $att) {
            $path = $this->safeBlobPath($att->blob_path);
            if ($path !== null) {
                $this->fs()->delete($path);
            }
        }
        Note::onlyTrashed()->whereKey($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    // ---- Folders ----

    public function storeFolder(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $folder = NoteFolder::create($this->folderRules($request));

        return response()->json(['folder' => $folder], 201);
    }

    public function updateFolder(Request $request, NoteFolder $folder): JsonResponse
    {
        $this->requireUser($request);
        // Guard against making a folder its own descendant (cycle).
        if ($request->filled('parent_id') && $this->wouldCycle($folder->id, $request->integer('parent_id'))) {
            abort(422);
        }
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(NoteFolder::class, $folder->id, $this->folderRules($request), $expected);

        return $this->optimisticJson($result, NoteFolder::class, $folder->id, 'folder');
    }

    public function destroyFolder(Request $request, NoteFolder $folder): JsonResponse
    {
        $this->requireUser($request);
        $ids = $this->descendantFolderIds($folder->id);
        // Soft-delete the subtree's notes + folders; notes in deleted folders keep
        // their note_folder_id (restore puts them back).
        Note::query()->whereIn('note_folder_id', $ids)->delete();
        NoteFolder::query()->whereIn('id', $ids)->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreFolder(Request $request, int $id): JsonResponse
    {
        $this->requireUser($request);
        $ids = $this->descendantFolderIds($id, withTrashed: true);
        NoteFolder::onlyTrashed()->whereIn('id', $ids)->restore();
        Note::onlyTrashed()->whereIn('note_folder_id', $ids)->restore();

        return response()->json(['ok' => true]);
    }

    // ---- Search ----

    public function search(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $q = trim($request->string('q')->value());
        if ($q === '') {
            return response()->json(['notes' => []]);
        }

        $like = '%'.$q.'%';
        $query = Note::query();
        if (DB::getDriverName() === 'pgsql') {
            $query->whereRaw(
                "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(body,'')) @@ plainto_tsquery('simple', ?)",
                [$q]
            )->orWhere('title', 'like', $like);
        } else {
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('title', 'like', $like)->orWhere('body', 'like', $like);
            });
        }

        $notes = $query->orderByDesc('updated_at')->limit(self::SEARCH_LIMIT)->get()
            ->map(fn (Note $n): array => $this->row($n))->all();

        return response()->json(['notes' => $notes]);
    }

    // ---- Attachments ----

    public function attach(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);
        $request->validate([
            // No svg/html (stored-XSS vectors) — defense-in-depth over the serve-time sandbox CSP.
            // Images + video embed inline in the note; pdf attaches as a link.
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,gif,mp4,webm,mov,m4v', 'max:'.$this->maxUploadKb()],
            'name' => ['nullable', 'string', 'max:500'],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $path = 'notes/'.Str::uuid()->toString();
        $this->fs()->putFileAs('notes', $upload, basename($path));

        $name = $request->filled('name') ? $request->string('name')->value() : $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $att = NoteAttachment::create([
            'note_id' => $note->id,
            'blob_path' => $path,
            'name' => $this->safeName($name !== '' ? $name : 'file'),
            'mime' => $mime !== '' ? $mime : null,
            'size' => $upload->getSize() ?: 0,
        ]);

        return response()->json(['attachment' => $this->attachmentRow($att)], 201);
    }

    /**
     * Embed an existing owner-scoped image/video from the Files or Gallery module
     * into a note by copying its bytes into a note attachment. Copying keeps the
     * note self-contained (no cross-module reference) and reuses the sandboxed
     * attachment raw endpoint. Both source models are owner-scoped (OwnsUserData),
     * and Files/Gallery/notes share the one files disk, so the copy stays local.
     */
    public function attachFrom(Request $request, Note $note): JsonResponse
    {
        $this->requireUser($request);
        $request->validate([
            'source' => ['required', 'in:file,gallery'],
            'id' => ['required', 'integer'],
        ]);
        $srcId = $request->integer('id');

        if ($request->string('source')->value() === 'file') {
            $f = FileEntry::query()->findOrFail($srcId);
            $srcPath = (string) $f->storage_path;
            $name = (string) $f->name;
            $mime = (string) $f->mime;
            $size = (int) $f->size;
        } else {
            $p = GalleryPhoto::query()->findOrFail($srcId);
            $srcPath = (string) $p->storage_path;
            $name = (string) $p->name;
            $mime = (string) $p->mime;
            $size = (int) $p->size;
        }

        // Explicit allowlist mirroring the upload attach() rules — NOT a broad
        // image/* || video/* (that would let image/svg+xml through, an XSS vector
        // the upload path deliberately excludes; a Files/Gallery row could carry a
        // spoofed mime). The serve-time sandbox CSP + nosniff already neutralise the
        // blob, this keeps the embed set consistent with what upload permits.
        $embeddable = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'video/m4v',
        ];
        if (! in_array($mime, $embeddable, true)) {
            abort(422, 'unsupported media type for embed');
        }
        // The source path is server-set on an owner-scoped model; guard it defensively
        // (files/ or gallery/ prefix, no traversal) — safeBlobPath is notes/-only.
        $okPrefix = str_starts_with($srcPath, 'files/') || str_starts_with($srcPath, 'gallery/');
        if ($srcPath === '' || str_contains($srcPath, '..') || str_starts_with($srcPath, '/') || ! $okPrefix || ! $this->fs()->exists($srcPath)) {
            abort(404);
        }

        $path = 'notes/'.Str::uuid()->toString();
        $this->fs()->copy($srcPath, $path);
        $att = NoteAttachment::create([
            'note_id' => $note->id,
            'blob_path' => $path,
            'name' => $this->safeName($name !== '' ? $name : 'embed'),
            'mime' => $mime,
            'size' => $size > 0 ? $size : ($this->fs()->size($path) ?: 0),
        ]);

        return response()->json(['attachment' => $this->attachmentRow($att)], 201);
    }

    public function attachmentRaw(Request $request, Note $note, NoteAttachment $attachment): StreamedResponse
    {
        $this->requireUser($request);
        // Owner-scope covers both models; also bind the attachment to the note.
        abort_unless($attachment->note_id === $note->id, 404);
        $path = $this->safeBlobPath($attachment->blob_path);
        if ($path === null || ! $this->fs()->exists($path)) {
            abort(404);
        }

        return $this->fs()->response($path, $this->safeName($attachment->name), [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function destroyAttachment(Request $request, Note $note, NoteAttachment $attachment): JsonResponse
    {
        $this->requireUser($request);
        abort_unless($attachment->note_id === $note->id, 404);
        $path = $this->safeBlobPath($attachment->blob_path);
        if ($path !== null) {
            $this->fs()->delete($path);
        }
        $attachment->delete();

        return response()->json(['ok' => true]);
    }

    /** Download a single note as a Markdown file (YAML frontmatter + body). */
    public function export(Request $request, Note $note): StreamedResponse
    {
        $this->requireUser($request);
        $title = $note->title ?? '';
        $tags = is_array($note->tags) ? array_values(array_filter($note->tags, 'is_string')) : [];
        $front = "---\ntitle: ".$this->yamlScalar($title)."\n";
        if ($tags !== []) {
            $front .= 'tags: ['.implode(', ', array_map(fn (string $t): string => $this->yamlScalar($t), $tags))."]\n";
        }
        $front .= "---\n\n";
        $md = $front.($note->body ?? '');
        $filename = $this->safeName(($title !== '' ? $title : 'note').'.md');

        return response()->streamDownload(function () use ($md): void {
            echo $md;
        }, $filename, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    // ---- Wikilinks / backlinks ----

    /**
     * Extract distinct wikilink target titles from a body: [[Title]] or
     * [[Title|Alias]]. Case-insensitive dedup (first spelling kept), capped.
     *
     * @return list<string>
     */
    private function parseWikilinks(?string $body): array
    {
        if ($body === null || $body === '') {
            return [];
        }
        if (! preg_match_all('/\[\[([^\]|\n]+)(?:\|[^\]\n]*)?\]\]/u', $body, $m)) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach ($m[1] as $raw) {
            $title = trim($raw);
            if ($title === '') {
                continue;
            }
            $key = mb_strtolower($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $title;
            if (count($out) >= 500) {
                break;
            }
        }

        return $out;
    }

    /** Rebuild the outbound wikilink edges for a note from its body. Owner-scoped. */
    private function syncLinks(Note $note): void
    {
        $titles = $this->parseWikilinks($note->body);

        // Resolve each title to a note id (case-insensitive, latest updated wins, never self).
        NoteLink::query()->where('source_note_id', $note->id)->delete();
        foreach ($titles as $title) {
            $target = Note::query()
                ->whereRaw('lower(title) = ?', [mb_strtolower($title)])
                ->where('id', '!=', $note->id)
                ->orderByDesc('updated_at')
                ->first();
            NoteLink::create([
                'source_note_id' => $note->id,
                'target_note_id' => $target?->id,
                'target_title' => $title,
            ]);
        }
    }

    /** Re-point previously-unresolved links whose title now matches this note. */
    private function resolveDangling(Note $note): void
    {
        $title = $note->title;
        if ($title === null || trim($title) === '') {
            return;
        }
        NoteLink::query()
            ->whereNull('target_note_id')
            ->whereRaw('lower(target_title) = ?', [mb_strtolower(trim($title))])
            ->where('source_note_id', '!=', $note->id)
            ->update(['target_note_id' => $note->id]);
    }

    /**
     * Notes that link to $note (resolved edges), with a short snippet. The
     * source() relation applies the Note global scope → trashed/other-user
     * sources are excluded.
     *
     * @return list<array{id:int,title:string,snippet:string}>
     */
    private function backlinksFor(Note $note): array
    {
        $links = NoteLink::query()->where('target_note_id', $note->id)->with('source')->get();
        $out = [];
        foreach ($links as $link) {
            $src = $link->source;
            if (! $src instanceof Note) {
                continue;
            }
            $body = (string) ($src->body ?? '');
            $out[] = [
                'id' => $src->id,
                'title' => $src->title ?? '',
                'snippet' => mb_substr(trim(preg_replace('/\s+/', ' ', $body) ?? ''), 0, 120),
            ];
        }

        return $out;
    }

    // ---- Storage / attachments helpers ----

    private function fs(): Filesystem
    {
        $d = config('files.disk');

        return Storage::disk(is_string($d) ? $d : 'files');
    }

    private function maxUploadKb(): int
    {
        $mb = config('files.max_upload_mb', 2048);

        return (is_numeric($mb) ? (int) $mb : 2048) * 1024;
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('#[\x00-\x1F\x7F"\\\\/]+#', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'file' : $clean;
    }

    /** Only serve/delete blobs under the notes/ prefix; reject traversal/absolute. */
    private function safeBlobPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        return str_starts_with($path, 'notes/') ? $path : null;
    }

    private function yamlScalar(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }

    /** @return list<array{id:int,name:string,mime:?string,size:int}> */
    private function attachmentsFor(Note $note): array
    {
        return array_values(NoteAttachment::query()->where('note_id', $note->id)->orderBy('id')->get()
            ->map(fn (NoteAttachment $a): array => $this->attachmentRow($a))->all());
    }

    /** @return array{id:int,name:string,mime:?string,size:int} */
    private function attachmentRow(NoteAttachment $a): array
    {
        return ['id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size];
    }

    // ---- Helpers ----

    /** @return array{id:int,note_folder_id:?int,title:string,tags:list<string>,pinned:bool,favorite:bool,updated_at:?string} */
    private function row(Note $n): array
    {
        $tags = is_array($n->tags) ? array_values(array_filter($n->tags, 'is_string')) : [];

        return [
            'id' => $n->id,
            'note_folder_id' => $n->note_folder_id,
            'title' => $n->title ?? '',
            'tags' => $tags,
            'pinned' => (bool) $n->pinned,
            'favorite' => (bool) $n->favorite,
            'updated_at' => $n->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:1000000'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'pinned' => ['sometimes', 'boolean'],
            'favorite' => ['sometimes', 'boolean'],
            'note_folder_id' => ['nullable', 'integer', "exists:note_folders,id,user_id,{$uid}"],
        ]);

        $patch = [
            'title' => $request->has('title') ? $request->string('title')->value() : null,
            'body' => $request->has('body') ? $request->string('body')->value() : null,
            'note_folder_id' => $request->filled('note_folder_id') ? $request->integer('note_folder_id') : null,
        ];
        if ($request->has('tags')) {
            $patch['tags'] = array_values(array_filter($request->array('tags'), 'is_string'));
        }
        if ($request->has('pinned')) {
            $patch['pinned'] = $request->boolean('pinned');
        }
        if ($request->has('favorite')) {
            $patch['favorite'] = $request->boolean('favorite');
        }

        return $patch;
    }

    /** @return array<string, mixed> */
    private function folderRules(Request $request): array
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:32'],
            'position' => ['sometimes', 'integer'],
            'parent_id' => ['nullable', 'integer', "exists:note_folders,id,user_id,{$uid}"],
        ]);

        $patch = [
            'name' => $request->string('name')->value(),
            'color' => $request->filled('color') ? $request->string('color')->value() : null,
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
        ];
        if ($request->has('position')) {
            $patch['position'] = $request->integer('position');
        }

        return $patch;
    }

    /**
     * Owner-scoped ids of a folder + all its descendants (BFS, cycle-safe).
     *
     * @return list<int>
     */
    private function descendantFolderIds(int $rootId, bool $withTrashed = false): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];
        $seen = [$rootId => true];
        while ($frontier !== []) {
            $query = NoteFolder::query();
            if ($withTrashed) {
                $query->withTrashed();
            }
            $children = $query->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = [];
            foreach ($children as $raw) {
                if (! is_numeric($raw)) {
                    continue;
                }
                $cid = (int) $raw;
                if (! isset($seen[$cid])) {
                    $seen[$cid] = true;
                    $ids[] = $cid;
                    $frontier[] = $cid;
                }
            }
        }

        return $ids;
    }

    /** True if setting $folderId's parent to $newParentId would create a cycle. */
    private function wouldCycle(int $folderId, int $newParentId): bool
    {
        return in_array($newParentId, $this->descendantFolderIds($folderId, withTrashed: true), true);
    }

    /**
     * Optimistic per-row update in a locked transaction (mirrors FinanceController).
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $patch
     */
    private function optimistic(string $modelClass, int $id, array $patch, ?int $expected): Model|false|null
    {
        return DB::transaction(function () use ($modelClass, $id, $patch, $expected): Model|false|null {
            $fresh = $modelClass::query()->lockForUpdate()->find($id);
            if (! $fresh instanceof Model) {
                return null;
            }
            $raw = $fresh->getAttribute('version');
            $ver = is_int($raw) ? $raw : 0;
            if ($expected !== null && $ver !== $expected) {
                return false;
            }
            $fresh->fill($patch);
            $fresh->setAttribute('version', $ver + 1);
            $fresh->save();

            return $fresh;
        });
    }

    /** @param  class-string<Model>  $modelClass */
    private function optimisticJson(Model|false|null $result, string $modelClass, int $id, string $key = 'note'): JsonResponse
    {
        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = $modelClass::query()->find($id);
            $v = $current instanceof Model ? $current->getAttribute('version') : null;

            return response()->json(['error' => 'version_conflict', 'version' => is_int($v) ? $v : 0], 409);
        }

        if ($key === 'note' && $result instanceof Note) {
            return response()->json(['note' => [...$this->row($result), 'body' => $result->body ?? '']]);
        }

        return response()->json([$key => $result]);
    }
}
