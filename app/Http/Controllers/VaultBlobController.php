<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores the vault's opaque content blobs.
 *
 * A blob is padded ciphertext produced in the browser; the server knows only a
 * random UUID and the padded byte length. Which file a blob belongs to — its
 * name, type, real size, folder — exists solely inside the encrypted manifest.
 */
class VaultBlobController extends Controller
{
    /**
     * Store one uploaded ciphertext blob and return its id.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'blob' => ['required', 'file', 'max:1048576'], // 1 GiB in KB
        ]);

        $id = (string) Str::uuid();
        Storage::disk(config('files.disk'))->putFileAs('vault', $request->file('blob'), $id);

        return response()->json(['id' => $id], 201);
    }

    /**
     * Stream a blob's ciphertext back to the browser for decryption.
     */
    public function show(string $blob): StreamedResponse
    {
        $path = $this->path($blob);
        $disk = Storage::disk(config('files.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'blob', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    /**
     * Delete a blob (after its manifest entry was removed in the browser).
     */
    public function destroy(string $blob): JsonResponse
    {
        Storage::disk(config('files.disk'))->delete($this->path($blob));

        return response()->json(['deleted' => true]);
    }

    /**
     * The storage path for a blob id, accepting only plain UUIDs so the id can
     * never traverse outside the vault prefix.
     */
    private function path(string $id): string
    {
        abort_unless(Str::isUuid($id), 404);

        return 'vault/'.$id;
    }
}
