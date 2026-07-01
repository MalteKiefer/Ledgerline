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
use Illuminate\Database\Eloquent\Model;
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
        ]);

        $folderId = $validated['folder_id'] ?? null;

        // When uploading inside a customer/project filter, attach to that record.
        $attachable = match (true) {
            filled($validated['customer_id'] ?? null) => Customer::find($validated['customer_id']),
            filled($validated['project_id'] ?? null) => Project::find($validated['project_id']),
            default => null,
        };

        $paths = $validated['paths'] ?? [];
        $folderCache = [];

        foreach ($validated['files'] as $i => $upload) {
            // A dropped folder passes a relative path like "Trip/Day1/img.jpg";
            // recreate that folder chain under the current folder.
            $target = $this->folderForPath($folderId, $paths[$i] ?? null, $folderCache);
            $this->persist($upload, $validated['tags'] ?? [], $attachable, $request->user(), $target);
        }

        return redirect()
            ->route('files.index', array_filter([
                'folder' => $folderId,
                'customer' => $validated['customer_id'] ?? null,
                'project' => $validated['project_id'] ?? null,
            ]))
            ->with('status', __('flash.files_uploaded', ['count' => count($validated['files'])]));
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
            'file_ids' => ['required', 'array'],
            'file_ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
        ]);

        $count = File::query()->whereIn('id', $validated['file_ids'])->update(['folder_id' => $validated['folder_id'] ?? null]);

        return back()->with('status', __('flash.files_moved', ['count' => $count]));
    }

    /**
     * Delete several files at once (with any deletable linked invoices).
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->authorize('create', File::class);

        $validated = $request->validate([
            'file_ids' => ['required', 'array'],
            'file_ids.*' => ['integer'],
        ]);

        $files = File::query()->with('attachable')->whereIn('id', $validated['file_ids'])->get();
        $paths = [];

        DB::transaction(function () use ($files, &$paths): void {
            foreach ($files as $file) {
                $invoice = $file->attachable instanceof Invoice ? $file->attachable : null;
                $paths[] = $file->disk_path;
                $file->delete();
                $invoice?->delete();
            }
        });

        $disk = Storage::disk(config('files.disk'));
        foreach ($paths as $path) {
            $disk->delete($path);
        }

        return back()->with('status', __('flash.files_deleted', ['count' => $files->count()]));
    }

    /**
     * Persist an uploaded file, optionally against an owning record and/or a
     * folder. A general (company) file has no attachable.
     *
     * @param  list<string>  $tags
     */
    private function persist(UploadedFile $upload, array $tags, ?Model $attachable, User $uploader, ?int $folderId = null): void
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
            'name' => $upload->getClientOriginalName(),
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
}
