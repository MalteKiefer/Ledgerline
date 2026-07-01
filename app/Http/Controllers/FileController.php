<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Http\Requests\StoreFileRequest;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
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

        $this->persist($request, $customer);

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

        $this->persist($request, $project);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'File uploaded.');
    }

    /**
     * Stream a file back to the browser (inline for images/PDF).
     */
    public function download(File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        $disk = Storage::disk(config('files.disk'));

        abort_unless($disk->exists($file->disk_path), 404);

        $disposition = in_array($file->type, [FileType::IMAGE, FileType::PDF], true) ? 'inline' : 'attachment';

        return $disk->response($file->disk_path, $file->name, ['Content-Type' => $file->mime_type], $disposition);
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
     */
    private function persist(StoreFileRequest $request, Model $attachable): void
    {
        $upload = $request->file('file');
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
        $file->uploaded_by = $request->user()->id;
        $file->attachable()->associate($attachable);
        $file->save();

        $tagIds = collect($request->validated()['tags'] ?? [])
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->all();
        $file->tags()->sync($tagIds);
    }
}
