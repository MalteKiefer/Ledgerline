<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreTeamFileRequest;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->with('status', 'File uploaded.');
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
            ->with('status', 'File uploaded.');
    }

    /**
     * Store a file uploaded from the team-wide overview, assigning it to the
     * chosen customer or project (which must belong to the user's team).
     */
    public function store(StoreTeamFileRequest $request): RedirectResponse
    {
        $this->authorize('create', File::class);

        [$type, $id] = explode(':', $request->validated()['attachable'], 2);

        // findOrFail is team-scoped, so a target outside the user's team 404s.
        $attachable = $type === 'customer'
            ? Customer::findOrFail($id)
            : Project::findOrFail($id);

        $this->persist($request->file('file'), $request->validated()['tags'] ?? [], $attachable, $request->user());

        return redirect()
            ->route('files.index')
            ->with('status', 'File uploaded.');
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

        $disposition = in_array($file->mime_type, self::INLINE_SAFE_MIMES, true) ? 'inline' : 'attachment';

        return $disk->response($file->disk_path, $file->name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $disposition);
    }

    /**
     * Delete a file (metadata and stored bytes).
     */
    public function destroy(File $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        Storage::disk(config('files.disk'))->delete($file->disk_path);
        $file->delete();

        return redirect()
            ->back()
            ->with('status', 'File deleted.');
    }

    /**
     * Persist an uploaded file against its owning record.
     *
     * @param  list<string>  $tags
     */
    private function persist(UploadedFile $upload, array $tags, Model $attachable, User $uploader): void
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
        $file->team_id = $attachable->team_id;
        $file->uploaded_by = $uploader->id;
        $file->attachable()->associate($attachable);
        $file->save();

        $tagIds = collect($tags)
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->all();
        $file->tags()->sync($tagIds);
    }
}
