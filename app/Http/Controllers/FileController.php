<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreTeamFileRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\File;
use App\Models\Folder;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Services\Files\ImageExif;
use App\Services\Files\ReverseGeocoder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploads, downloads and deletes files attached to customers and projects.
 *
 * Uploads stream through the app to the private object-storage disk; the app
 * detects the file type and (for unencrypted, text-extractable files) captures
 * searchable text. Team isolation is enforced by the global scope on File and
 * the route-bound customer/project.
 */
class FileController extends Controller
{
    /**
     * Store an uploaded file for a customer.
     */
    public function storeForCustomer(StoreFileRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', File::class);

        $this->persist($request->file('file'), $request->validated()['tags'] ?? [], $customer, $request->user());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('flash.file_uploaded'));
    }

    /**
     * Store an uploaded file for a project.
     */
    public function storeForProject(StoreFileRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', File::class);

        $this->persist($request->file('file'), $request->validated()['tags'] ?? [], $project, $request->user());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', __('flash.file_uploaded'));
    }

    /**
     * Store an uploaded file for an expense (a global finance record).
     */
    public function storeForExpense(StoreFileRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('create', File::class);

        $this->persist($request->file('file'), $request->validated()['tags'] ?? [], $expense, $request->user());

        return redirect()
            ->route('finance.expenses.show', $expense)
            ->with('status', __('flash.file_uploaded'));
    }

    /**
     * Store a general (company) file with no customer or project, optionally in
     * a folder.
     */
    public function storeGeneral(Request $request): RedirectResponse
    {
        $this->authorize('create', File::class);

        $validated = $request->validate([
            'files' => ['required', 'array', 'max:50'],
            'files.*' => ['file', 'max:51200'],
            'paths' => ['array'],
            'paths.*' => ['nullable', 'string', 'max:1024'],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
            'on_conflict' => ['nullable', 'in:overwrite,rename,skip'],
            'encrypted' => ['nullable', 'boolean'],
            'enc_metadata' => ['array'],
            'enc_metadata.*' => ['string'],
            'enc_file_key' => ['array'],
            'enc_file_key.*' => ['string'],
            // Per-file target folder, resolved client-side (the encrypted folder
            // tree is created in the browser before the files are sent).
            'folder_ids' => ['array'],
            'folder_ids.*' => ['nullable', 'integer', Rule::exists('folders', 'id')],
        ]);

        $strategy = $validated['on_conflict'] ?? 'rename';
        $folderId = $validated['folder_id'] ?? null;

        // When uploading inside a customer/project filter, attach to that record.
        $attachable = match (true) {
            filled($validated['customer_id'] ?? null) => Customer::find($validated['customer_id']),
            filled($validated['project_id'] ?? null) => Project::find($validated['project_id']),
            default => null,
        };

        // Zero-knowledge uploads arrive already encrypted in the browser: the
        // bytes are ciphertext and the real name/mime live in enc_metadata. No
        // name conflict resolution or folder recreation (names are opaque here).
        if (! empty($validated['encrypted'])) {
            $encFolderIds = $validated['folder_ids'] ?? [];
            $stored = 0;
            foreach ($validated['files'] as $i => $upload) {
                $this->persistEncrypted(
                    $upload,
                    $validated['enc_metadata'][$i] ?? '',
                    $validated['enc_file_key'][$i] ?? '',
                    $attachable,
                    $request->user(),
                    $encFolderIds[$i] ?? $folderId,
                );
                $stored++;
            }

            return redirect()
                ->route('files.index', array_filter([
                    'folder' => $folderId,
                    'customer' => $validated['customer_id'] ?? null,
                    'project' => $validated['project_id'] ?? null,
                ]))
                ->with('status', __('flash.files_uploaded', ['count' => $stored]));
        }

        $paths = $validated['paths'] ?? [];
        $folderCache = [];

        $stored = 0;

        foreach ($validated['files'] as $i => $upload) {
            // A dropped folder passes a relative path like "Trip/Day1/img.jpg";
            // recreate that folder chain under the current folder.
            $target = $this->folderForPath($folderId, $paths[$i] ?? null, $folderCache);

            // Resolve a same-name clash per the chosen strategy.
            $name = $this->resolveConflict($upload->getClientOriginalName(), $target, $attachable, $strategy);
            if ($name === null) {
                continue; // skipped
            }

            $this->persist($upload, $validated['tags'] ?? [], $attachable, $request->user(), $target, $name);
            $stored++;
        }

        return redirect()
            ->route('files.index', array_filter([
                'folder' => $folderId,
                'customer' => $validated['customer_id'] ?? null,
                'project' => $validated['project_id'] ?? null,
            ]))
            ->with('status', __('flash.files_uploaded', ['count' => $stored]));
    }

    /**
     * Extract a zip / tar / gzip archive into a new folder (named after the
     * archive) beside it, recreating any internal folder structure.
     */
    public function extract(Request $request, File $file): RedirectResponse
    {
        $this->authorize('view', $file);
        $this->authorize('create', File::class);

        $disk = Storage::disk(config('files.disk'));
        $name = strtolower($file->name);

        $ext = match (true) {
            str_ends_with($name, '.zip') => 'zip',
            str_ends_with($name, '.tar.gz'), str_ends_with($name, '.tgz'),
            str_ends_with($name, '.tar.bz2'), str_ends_with($name, '.tbz'), str_ends_with($name, '.tbz2'),
            str_ends_with($name, '.tar') => 'tar',
            str_ends_with($name, '.gz') => 'gz',
            str_ends_with($name, '.bz2') => 'bz2',
            default => null,
        };

        if ($ext === null) {
            return back()->with('error', __('flash.extract_unsupported'));
        }

        // Work on a local copy, keeping a suitable (possibly double) extension so
        // the extractor detects the compression.
        $suffix = match (true) {
            str_ends_with($name, '.tar.gz') => '.tar.gz',
            str_ends_with($name, '.tar.bz2') => '.tar.bz2',
            default => '.'.pathinfo($name, PATHINFO_EXTENSION),
        };
        $archive = tempnam(sys_get_temp_dir(), 'arc').$suffix;
        file_put_contents($archive, $disk->readStream($file->disk_path));
        $work = $archive.'-out';
        mkdir($work, 0700, true);

        try {
            $this->unpack($ext, $archive, $work, pathinfo($file->name, PATHINFO_FILENAME));

            $base = Folder::firstOrCreate([
                'name' => mb_substr(preg_replace('/\.(tar\.gz|tar\.bz2|zip|tar|tgz|tbz2?|gz|bz2)$/i', '', $file->name), 0, 255),
                'parent_id' => $file->folder_id,
            ])->id;

            $cache = [];
            $count = 0;
            $root = realpath($work);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($work, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $entry) {
                if (! $entry->isFile()) {
                    continue;
                }
                // Skip symlinks and anything resolving outside the work dir
                // (defence in depth against zip-slip / link traversal).
                if ($entry->isLink() || is_link($entry->getPathname())) {
                    continue;
                }
                $real = realpath($entry->getPathname());
                if ($real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $rel = ltrim(str_replace($work, '', $entry->getPathname()), '/');
                $target = $this->folderForPath($base, $rel, $cache);
                $upload = new UploadedFile($entry->getPathname(), basename($rel), null, null, true);
                $this->persist($upload, [], null, $request->user(), $target);
                $count++;
            }
        } catch (\Throwable) {
            return back()->with('error', __('flash.extract_failed'));
        } finally {
            @unlink($archive);
            $this->removeDir($work);
        }

        return back()->with('status', __('flash.files_extracted', ['count' => $count]));
    }

    /**
     * Unpack an archive of the given kind into a working directory.
     */
    private function unpack(string $kind, string $archive, string $work, string $baseName): void
    {
        if ($kind === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($archive) !== true) {
                throw new \RuntimeException('Cannot open zip');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (! $this->safeArchivePath((string) $zip->getNameIndex($i))) {
                    $zip->close();
                    throw new \RuntimeException('Unsafe archive entry');
                }
            }
            $zip->extractTo($work);
            $zip->close();

            return;
        }

        if ($kind === 'tar') {
            $phar = new \PharData($archive);
            $prefix = 'phar://'.$archive.'/';
            foreach (new \RecursiveIteratorIterator($phar) as $entry) {
                $inner = str_replace($prefix, '', str_replace('\\', '/', $entry->getPathname()));
                if (! $this->safeArchivePath($inner)) {
                    throw new \RuntimeException('Unsafe archive entry');
                }
            }
            $phar->extractTo($work, null, true);

            return;
        }

        // A single gzip- or bzip2-compressed file → one output file.
        $raw = (string) file_get_contents($archive);
        $data = $kind === 'bz2'
            ? (function_exists('bzdecode') ? bzdecode($raw) : throw new \RuntimeException('bz2 unavailable'))
            : gzdecode($raw);

        if (! is_string($data)) {
            throw new \RuntimeException('Cannot decode archive');
        }

        file_put_contents($work.'/'.$baseName, $data);
    }

    /**
     * Reject archive entry names that are absolute, contain a null byte, or use
     * ".." traversal — the vectors behind zip-slip.
     */
    private function safeArchivePath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0")) {
            return false;
        }

        $name = str_replace('\\', '/', $name);
        if (str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
            return false;
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * Report which of the given relative paths already exist, so the uploader
     * can ask how to handle same-name files before sending them.
     */
    public function conflicts(Request $request): JsonResponse
    {
        $this->authorize('create', File::class);

        $validated = $request->validate([
            'paths' => ['required', 'array'],
            'paths.*' => ['string', 'max:1024'],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
            'customer_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $attachable = match (true) {
            filled($validated['customer_id'] ?? null) => Customer::find($validated['customer_id']),
            filled($validated['project_id'] ?? null) => Project::find($validated['project_id']),
            default => null,
        };

        $conflicts = [];
        foreach ($validated['paths'] as $path) {
            $folderId = $this->existingFolderForPath($validated['folder_id'] ?? null, $path);
            if ($folderId === false) {
                continue; // folder chain does not exist yet → no clash
            }

            if ($this->fileQuery(basename($path), $folderId, $attachable)->exists()) {
                $conflicts[] = $path;
            }
        }

        return response()->json(['conflicts' => $conflicts]);
    }

    /**
     * Apply the conflict strategy for a target name. Returns the name to store
     * (possibly renamed), or null to skip. Overwrite removes the existing file.
     */
    private function resolveConflict(string $name, ?int $folderId, ?Model $attachable, string $strategy): ?string
    {
        $existing = $this->fileQuery($name, $folderId, $attachable)->first();
        if ($existing === null) {
            return $name;
        }

        if ($strategy === 'skip') {
            return null;
        }

        if ($strategy === 'overwrite') {
            Storage::disk(config('files.disk'))->delete($existing->disk_path);
            $existing->delete();

            return $name;
        }

        // Rename: append a counter until the name is free.
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $suffix = $ext !== '' ? '.'.$ext : '';
        $i = 1;
        $candidate = $name;
        while ($this->fileQuery($candidate, $folderId, $attachable)->exists()) {
            $i++;
            $candidate = $base.'_'.$i.$suffix;
        }

        return $candidate;
    }

    /**
     * A query for a file of the given name in a folder and ownership context.
     *
     * @return Builder<File>
     */
    private function fileQuery(string $name, ?int $folderId, ?Model $attachable): Builder
    {
        $query = File::query()->where('name', $name)->where('folder_id', $folderId);

        return $attachable !== null
            ? $query->where('attachable_type', $attachable->getMorphClass())->where('attachable_id', $attachable->getKey())
            : $query->whereNull('attachable_type');
    }

    /**
     * The id of the existing folder for a relative path without creating any,
     * or false when the chain does not fully exist.
     */
    private function existingFolderForPath(?int $baseId, string $relativePath): int|false|null
    {
        $dir = trim(str_replace('\\', '/', dirname($relativePath)), '/');
        if ($dir === '' || $dir === '.') {
            return $baseId;
        }

        $parent = $baseId;
        foreach (explode('/', $dir) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $folder = Folder::query()->where('name', $segment)->where('parent_id', $parent)->first();
            if ($folder === null) {
                return false;
            }
            $parent = $folder->id;
        }

        return $parent;
    }

    /**
     * Resolve (creating as needed) the folder for a dropped file's relative
     * path, nesting subfolders under the current folder. Created folder ids are
     * cached per request so a folder is only created once.
     *
     * @param  array<string, int>  $cache
     */
    private function folderForPath(?int $baseId, ?string $relativePath, array &$cache): ?int
    {
        if ($relativePath === null) {
            return $baseId;
        }

        $dir = trim(str_replace('\\', '/', dirname($relativePath)), '/');
        if ($dir === '' || $dir === '.') {
            return $baseId;
        }

        $parent = $baseId;
        $accumulated = (string) $baseId;

        foreach (explode('/', $dir) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $accumulated .= '/'.$segment;
            $cache[$accumulated] ??= Folder::firstOrCreate([
                'name' => mb_substr($segment, 0, 255),
                'parent_id' => $parent,
            ])->id;
            $parent = $cache[$accumulated];
        }

        return $parent;
    }

    /**
     * Encrypt an existing plaintext file. The browser fetches the plaintext,
     * encrypts it, and posts the ciphertext blob + wrapped key/metadata here;
     * the server overwrites the bytes and flips the file to encrypted, dropping
     * its plaintext name, mime, checksum and extracted text.
     */
    public function encrypt(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        abort_if($file->is_encrypted, 409);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'enc_metadata' => ['required', 'string'],
            'enc_file_key' => ['required', 'string'],
        ]);

        $blob = $validated['file'];
        Storage::disk(config('files.disk'))->put(
            $file->disk_path,
            (string) file_get_contents($blob->getRealPath()),
        );

        $file->forceFill([
            'name' => '',
            'title' => null,
            'mime_type' => 'application/octet-stream',
            'type' => FileType::ENCRYPTED,
            'size' => $blob->getSize(),
            'checksum' => null,
            'is_encrypted' => true,
            'extracted_text' => null,
            'enc_metadata' => $validated['enc_metadata'],
            'enc_file_key' => $validated['enc_file_key'],
        ])->save();

        return back()->with('status', __('flash.file_updated'));
    }

    /**
     * Rename a file (its display title) inline.
     */
    public function rename(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $file->update(['title' => $validated['title']]);

        return back()->with('status', __('flash.file_renamed'));
    }

    /**
     * The largest file we will attempt to open in the text editor.
     */
    private const EDIT_MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Open a file in the text editor, reading its bytes as UTF-8 text. Binary
     * or oversized files are shown as not editable.
     */
    public function edit(File $file): View
    {
        $this->authorize('update', $file);

        $disk = Storage::disk(config('files.disk'));
        $editable = false;
        $content = '';

        // Encrypted files are opaque ciphertext here; the browser decrypts them.
        // Editing them server-side is impossible, so never read the bytes.
        if (! $file->is_encrypted && $disk->exists($file->disk_path) && $file->size <= self::EDIT_MAX_BYTES) {
            $bytes = (string) $disk->get($file->disk_path);
            // Treat it as text only when it is valid UTF-8 with no null bytes.
            if (! str_contains($bytes, "\0") && mb_check_encoding($bytes, 'UTF-8')) {
                $editable = true;
                $content = $bytes;
            }
        }

        return view('files.edit', [
            'file' => $file,
            'editable' => $editable,
            'content' => $content,
        ]);
    }

    /**
     * Save edited text back to the file, refreshing its size, checksum and
     * extracted text.
     */
    public function updateContent(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        // An encrypted file is re-encrypted in the browser: the server just
        // overwrites the ciphertext blob and the wrapped key/metadata.
        if ($file->is_encrypted) {
            $validated = $request->validate([
                'file' => ['required', 'file', 'max:'.((int) (self::EDIT_MAX_BYTES / 1024) + 64)],
                'enc_metadata' => ['required', 'string'],
                'enc_file_key' => ['required', 'string'],
            ]);

            $blob = $validated['file'];
            Storage::disk(config('files.disk'))->put(
                $file->disk_path,
                (string) file_get_contents($blob->getRealPath()),
            );

            $file->forceFill([
                'size' => $blob->getSize(),
                'enc_metadata' => $validated['enc_metadata'],
                'enc_file_key' => $validated['enc_file_key'],
            ])->save();

            return redirect()->route('files.edit', $file)->with('status', __('flash.file_saved'));
        }

        $content = (string) $request->validate([
            'content' => ['present', 'string', 'max:'.self::EDIT_MAX_BYTES],
        ])['content'];

        // Normalise line endings the browser may have sent.
        $content = str_replace("\r\n", "\n", $content);

        Storage::disk(config('files.disk'))->put($file->disk_path, $content);

        $extractable = $file->type->isTextExtractable($file->mime_type);
        $file->forceFill([
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
            'extracted_text' => $extractable ? mb_substr($content, 0, (int) config('files.extract_text_max_bytes')) : $file->extracted_text,
        ])->save();

        return redirect()->route('files.edit', $file)->with('status', __('flash.file_saved'));
    }

    /**
     * Store a file uploaded from the files overview, assigning it to the chosen
     * customer or project.
     */
    public function store(StoreTeamFileRequest $request): RedirectResponse
    {
        $this->authorize('create', File::class);

        [$type, $id] = explode(':', $request->validated()['attachable'], 2);

        $attachable = $type === 'customer'
            ? Customer::findOrFail($id)
            : Project::findOrFail($id);

        $this->persist($request->file('file'), $request->validated()['tags'] ?? [], $attachable, $request->user());

        return redirect()
            ->route('files.index')
            ->with('status', __('flash.file_uploaded'));
    }

    /**
     * MIME types that are safe to render inline on our own origin.
     *
     * Deliberately excludes SVG, HTML and XML, which can execute script and
     * would otherwise be a stored-XSS vector when served inline same-origin.
     */
    private const INLINE_SAFE_MIMES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    /**
     * Show a file's detail page.
     */
    public function show(File $file, ImageExif $exifReader, ReverseGeocoder $geocoder): View
    {
        $this->authorize('view', $file);

        $file->load(['attachable', 'tags', 'uploader', 'folder']);

        $exif = $exifReader->read($file);
        $location = null;

        if ($exif !== null && $exif['gps'] !== null) {
            [$lat, $lon] = $exif['gps'];
            $location = ['lat' => $lat, 'lon' => $lon, 'address' => $geocoder->lookup($lat, $lon)];
        }

        return view('files.show', [
            'file' => $file,
            'folders' => Folder::query()->orderBy('name')->get(),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name')->all(),
            'exif' => $exif,
            'location' => $location,
        ]);
    }

    /**
     * Update a file's editable metadata (title, description, note).
     */
    public function update(UpdateFileRequest $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $data = $request->validated();
        $file->update($data);

        // Sync tags from the free-text input.
        $tagIds = collect($data['tags'] ?? [])
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->all();
        $file->tags()->sync($tagIds);

        return redirect()
            ->route('files.show', $file)
            ->with('status', __('flash.file_updated'));
    }

    /**
     * Stream a file back to the browser.
     *
     * Only a strict allowlist of MIME types is rendered inline; everything else
     * (including SVG/HTML/XML) is forced to download. nosniff prevents content
     * sniffing and a locked-down CSP neutralises any active content.
     */
    public function download(File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        $disk = Storage::disk(config('files.disk'));

        abort_unless($disk->exists($file->disk_path), 404);

        $inline = in_array($file->mime_type, self::INLINE_SAFE_MIMES, true);

        // Attachments stay maximally locked down. Inline (image/PDF) previews
        // must be framable by our own origin, so they use SAMEORIGIN and a CSP
        // that permits same-origin framing while still blocking active content.
        $headers = $inline
            ? [
                'Content-Type' => $file->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; object-src 'self'; frame-ancestors 'self'",
            ]
            : [
                'Content-Type' => $file->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ];

        return $disk->response($file->disk_path, $file->name, $headers, $inline ? 'inline' : 'attachment');
    }

    /**
     * Delete a file (metadata and stored bytes).
     */
    public function destroy(File $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        // A file that is an invoice's source document takes the invoice record
        // with it — the document and its data belong together. (Only imported
        // invoices ever carry an attached file.)
        $invoice = $file->attachable instanceof Invoice ? $file->attachable : null;

        $path = $file->disk_path;
        $folderId = $file->folder_id;

        DB::transaction(function () use ($file, $invoice): void {
            $file->delete();
            $invoice?->delete();
        });

        Storage::disk(config('files.disk'))->delete($path);

        if ($invoice !== null) {
            return redirect()
                ->route('finance.invoices.index')
                ->with('status', __('flash.invoice_and_file_deleted'));
        }

        // Return to the folder the file lived in, not the now-gone detail page.
        return redirect()
            ->route('files.index', ['folder' => $folderId])
            ->with('status', __('flash.file_deleted'));
    }

    /**
     * Move several files into a folder (or to the root).
     */
    public function bulkMove(Request $request): RedirectResponse
    {
        $this->authorize('create', File::class);

        $validated = $request->validate([
            'file_ids' => ['array', 'max:1000', 'required_without:folder_ids'],
            'file_ids.*' => ['integer'],
            'folder_ids' => ['array', 'max:1000'],
            'folder_ids.*' => ['integer', Rule::exists('folders', 'id')],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
        ]);

        $target = $validated['folder_id'] ?? null;

        $count = File::query()->whereIn('id', $validated['file_ids'] ?? [])->update(['folder_id' => $target]);

        // Reparent selected folders, skipping any move into the folder itself or
        // one of its own descendants (which would orphan the subtree).
        foreach (Folder::query()->whereIn('id', $validated['folder_ids'] ?? [])->get() as $folder) {
            if ($folder->id === $target || $this->folderContains($folder->id, $target)) {
                continue;
            }
            $folder->update(['parent_id' => $target]);
            $count++;
        }

        return back()->with('status', __('flash.items_moved', ['count' => $count]));
    }

    /**
     * Whether $ancestorId is $folderId or one of its ancestors — i.e. moving a
     * folder into $folderId would create a cycle.
     */
    private function folderContains(int $ancestorId, ?int $folderId): bool
    {
        $node = $folderId;
        while ($node !== null) {
            if ($node === $ancestorId) {
                return true;
            }
            $node = Folder::query()->whereKey($node)->value('parent_id');
        }

        return false;
    }

    /**
     * Delete several files at once (with any deletable linked invoices).
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->authorize('create', File::class);

        $validated = $request->validate([
            'file_ids' => ['array', 'max:1000', 'required_without:folder_ids'],
            'file_ids.*' => ['integer'],
            'folder_ids' => ['array', 'max:1000'],
            'folder_ids.*' => ['integer', Rule::exists('folders', 'id')],
        ]);

        $files = File::query()->with('attachable')->whereIn('id', $validated['file_ids'] ?? [])->get();
        $paths = [];
        $folders = Folder::query()->whereIn('id', $validated['folder_ids'] ?? [])->get();

        DB::transaction(function () use ($files, $folders, &$paths): void {
            foreach ($files as $file) {
                $invoice = $file->attachable instanceof Invoice ? $file->attachable : null;
                $paths[] = $file->disk_path;
                $file->delete();
                $invoice?->delete();
            }

            // Deleting a folder lifts its contents up one level (same as the
            // single-folder delete), so nothing inside is lost.
            foreach ($folders as $folder) {
                Folder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
                File::where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
                $folder->delete();
            }
        });

        $disk = Storage::disk(config('files.disk'));
        foreach ($paths as $path) {
            $disk->delete($path);
        }

        return back()->with('status', __('flash.items_deleted', ['count' => $files->count() + $folders->count()]));
    }

    /**
     * Persist an uploaded file, optionally against an owning record and/or a
     * folder. A general (company) file has no attachable.
     *
     * @param  list<string>  $tags
     */
    private function persist(UploadedFile $upload, array $tags, ?Model $attachable, User $uploader, ?int $folderId = null, ?string $name = null): void
    {
        $mime = $upload->getMimeType() ?: ($upload->getClientMimeType() ?: 'application/octet-stream');
        $type = FileType::fromMime($mime);

        // Capture searchable text before the temp file is consumed by putFile.
        $extracted = null;
        if ($type->isTextExtractable($mime)) {
            $bytes = file_get_contents($upload->getRealPath(), false, null, 0, (int) config('files.extract_text_max_bytes'));
            $extracted = $bytes === false ? null : $bytes;
        }

        $checksum = hash_file('sha256', $upload->getRealPath()) ?: null;
        $path = Storage::disk(config('files.disk'))->putFile('files', $upload);

        $file = new File([
            'name' => $name ?? $upload->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => $mime,
            'type' => $type,
            'size' => $upload->getSize(),
            'checksum' => $checksum,
            'is_encrypted' => false,
            'extracted_text' => $extracted,
        ]);
        $file->uploaded_by = $uploader->id;
        $file->folder_id = $folderId;

        if ($attachable !== null) {
            $file->attachable()->associate($attachable);
        }

        $file->save();

        $tagIds = collect($tags)
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->all();
        $file->tags()->sync($tagIds);
    }

    /**
     * Store an already-encrypted upload. The server treats the bytes as opaque
     * ciphertext: no checksum, no text extraction, no type detection. The real
     * name and mime live only inside the client-encrypted enc_metadata blob.
     */
    private function persistEncrypted(UploadedFile $blob, string $encMetadata, string $encFileKey, ?Model $attachable, User $uploader, ?int $folderId): void
    {
        $path = Storage::disk(config('files.disk'))->putFile('files', $blob);

        $file = new File([
            'name' => '',
            'disk_path' => $path,
            'mime_type' => 'application/octet-stream',
            'type' => FileType::ENCRYPTED,
            'size' => $blob->getSize(),
            'checksum' => null,
            'is_encrypted' => true,
            'extracted_text' => null,
            'enc_metadata' => $encMetadata,
            'enc_file_key' => $encFileKey,
        ]);
        $file->uploaded_by = $uploader->id;
        $file->folder_id = $folderId;

        if ($attachable !== null) {
            $file->attachable()->associate($attachable);
        }

        $file->save();
    }
}
