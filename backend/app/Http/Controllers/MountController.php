<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StorageMount;
use App\Services\Backup\BackupDestinationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * External storage mounts (S3 / SFTP) browsed live inside Files. Rows are
 * owner-scoped; credentials are stored encrypted (never serialized). The remote
 * filesystem is built through the shared BackupDestinationFactory (same SSRF
 * host-allow guard). Paths are confined below the mount's configured root and
 * hardened against traversal.
 */
class MountController extends Controller
{
    public function __construct(private readonly BackupDestinationFactory $factory) {}

    // ---- CRUD ----

    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json([
            'mounts' => StorageMount::query()->orderBy('name')->get()
                ->map(fn (StorageMount $m): array => $this->present($m))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $data = $this->validated($request);
        $config = $this->buildConfig($request, $request->string('type')->value(), null);

        // Verify the mount is reachable before persisting (read-only mounts only
        // need a listing; writable ones probe a write+delete).
        try {
            $this->probe($request->string('type')->value(), $config, $request->boolean('read_only'));
        } catch (Throwable $e) {
            return response()->json(['error' => 'unreachable', 'message' => $this->reason($e)], 422);
        }

        $mount = new StorageMount;
        $mount->forceFill([
            'user_id' => $uid,
            'name' => $data['name'],
            'type' => $data['type'],
            'read_only' => $request->boolean('read_only'),
            'config' => $config,
        ])->save();

        return response()->json(['mount' => $this->present($mount)], 201);
    }

    public function update(Request $request, StorageMount $mount): JsonResponse
    {
        $this->requireUser($request);
        $data = $this->validated($request, $mount);
        $config = $this->buildConfig($request, $mount->type, $mount);

        try {
            $this->probe($mount->type, $config, $request->boolean('read_only', $mount->read_only));
        } catch (Throwable $e) {
            return response()->json(['error' => 'unreachable', 'message' => $this->reason($e)], 422);
        }

        $mount->forceFill([
            'name' => $data['name'],
            'read_only' => $request->boolean('read_only', $mount->read_only),
            'config' => $config,
        ])->save();

        return response()->json(['mount' => $this->present($mount)]);
    }

    public function destroy(Request $request, StorageMount $mount): JsonResponse
    {
        $this->requireUser($request);
        $mount->delete();

        return response()->json(['ok' => true]);
    }

    /** Test a mount config (before saving, or an existing one) without persisting. */
    public function test(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $type = $request->string('type')->value();
        if (! in_array($type, ['s3', 'sftp'], true)) {
            return response()->json(['error' => 'invalid_type'], 422);
        }
        try {
            $this->probe($type, $this->buildConfig($request, $type, null), $request->boolean('read_only'));

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $this->reason($e)], 422);
        }
    }

    // ---- Browse / transfer ----

    public function list(Request $request, StorageMount $mount): JsonResponse
    {
        $this->requireUser($request);
        $path = $this->safePath($request->string('path')->value());
        $fs = $this->fs($mount);

        $dirs = [];
        $files = [];
        foreach ($fs->listContents($path, false) as $item) {
            $rel = $item->path();
            $name = basename($rel);
            if ($item->isDir()) {
                $dirs[] = ['name' => $name, 'path' => $rel];
            } else {
                $files[] = [
                    'name' => $name,
                    'path' => $rel,
                    'size' => method_exists($item, 'fileSize') ? $item->fileSize() : null,
                    'last_modified' => method_exists($item, 'lastModified') ? $item->lastModified() : null,
                ];
            }
        }
        usort($dirs, fn ($a, $b): int => strcasecmp((string) $a['name'], (string) $b['name']));
        usort($files, fn ($a, $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return response()->json(['path' => $path, 'dirs' => $dirs, 'files' => $files, 'read_only' => $mount->read_only]);
    }

    public function download(Request $request, StorageMount $mount): StreamedResponse
    {
        $this->requireUser($request);
        $path = $this->safePath($request->string('path')->value());
        abort_if($path === '', 404);
        $fs = $this->fs($mount);
        abort_unless($fs->fileExists($path), 404);

        return response()->streamDownload(function () use ($fs, $path): void {
            $stream = $fs->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, basename($path), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function upload(Request $request, StorageMount $mount): JsonResponse
    {
        $this->requireUser($request);
        abort_if($mount->read_only, 403);
        $request->validate(['file' => ['required', 'file'], 'path' => ['nullable', 'string', 'max:1000']]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $dir = $this->safePath($request->string('path')->value());
        $name = $this->safeSegment($upload->getClientOriginalName());
        $target = ($dir === '' ? '' : $dir.'/').$name;

        $stream = fopen($upload->getRealPath() ?: '', 'rb');
        if ($stream === false) {
            abort(500);
        }
        try {
            $this->fs($mount)->writeStream($target, $stream);
        } catch (Throwable $e) {
            return response()->json(['error' => 'write_failed', 'message' => $this->reason($e)], 422);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return response()->json(['ok' => true, 'path' => $target], 201);
    }

    public function mkdir(Request $request, StorageMount $mount): JsonResponse
    {
        $this->requireUser($request);
        abort_if($mount->read_only, 403);
        $request->validate(['path' => ['nullable', 'string', 'max:1000'], 'name' => ['required', 'string', 'max:255']]);
        $dir = $this->safePath($request->string('path')->value());
        $name = $this->safeSegment($request->string('name')->value());
        $target = ($dir === '' ? '' : $dir.'/').$name;
        $this->fs($mount)->createDirectory($target);

        return response()->json(['ok' => true, 'path' => $target], 201);
    }

    public function deletePath(Request $request, StorageMount $mount): JsonResponse
    {
        $this->requireUser($request);
        abort_if($mount->read_only, 403);
        $request->validate(['path' => ['required', 'string', 'max:1000'], 'dir' => ['sometimes', 'boolean']]);
        $path = $this->safePath($request->string('path')->value());
        abort_if($path === '', 422);
        $fs = $this->fs($mount);
        if ($request->boolean('dir')) {
            $fs->deleteDirectory($path);
        } else {
            $fs->delete($path);
        }

        return response()->json(['ok' => true]);
    }

    // ---- internals ----

    /** @return array{name:string,type:string} */
    private function validated(Request $request, ?StorageMount $mount = null): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => [$mount === null ? 'required' : 'sometimes', Rule::in(['s3', 'sftp'])],
            'read_only' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => $request->string('name')->value(),
            'type' => $mount?->type ?? $request->string('type')->value(),
        ];
    }

    /**
     * Assemble the driver config from the request. On update, blank secret fields
     * (secret / password / private_key) preserve the stored value.
     *
     * @return array<string, mixed>
     */
    private function buildConfig(Request $request, string $type, ?StorageMount $mount): array
    {
        $old = $mount?->config ?? [];
        $keep = static fn (string $k, string $new): string => $new !== '' ? $new : (is_string($old[$k] ?? null) ? $old[$k] : '');

        if ($type === 's3') {
            return [
                'region' => $request->string('region')->value() ?: 'us-east-1',
                'bucket' => $request->string('bucket')->value(),
                'key' => $request->string('key')->value(),
                'secret' => $keep('secret', $request->string('secret')->value()),
                'endpoint' => $request->string('endpoint')->value(),
                'use_path_style' => $request->boolean('use_path_style'),
                'path' => $request->string('path_prefix')->value(),
            ];
        }

        return [
            'host' => $request->string('host')->value(),
            'port' => $request->integer('port') ?: 22,
            'username' => $request->string('username')->value(),
            'password' => $keep('password', $request->string('password')->value()),
            'private_key' => $keep('private_key', $request->string('private_key')->value()),
            'host_fingerprint' => $request->string('host_fingerprint')->value(),
            'path' => $request->string('root')->value(),
        ];
    }

    private function fs(StorageMount $mount): Filesystem
    {
        // Interactive: every call site (list/download/upload/mkdir/delete) is a
        // live web request a user is watching, not a queued backup transfer —
        // see BackupDestinationFactory::makeFromParts()'s $interactive doc.
        return $this->factory->makeFromParts($mount->type, $mount->config ?? [], interactive: true);
    }

    /**
     * Reachability probe: read-only mounts only list the root; writable mounts
     * write + delete a tiny object (reusing the factory's test path).
     *
     * @param  array<string, mixed>  $config
     */
    private function probe(string $type, array $config, bool $readOnly): void
    {
        $fs = $this->factory->makeFromParts($type, $config, interactive: true);
        if ($readOnly) {
            // Touch the listing so bad credentials / host surface now.
            iterator_to_array($fs->listContents('', false));

            return;
        }
        $this->factory->test($type, $config);
    }

    /** Normalize a browse path: strip leading/trailing slashes, reject traversal. */
    private function safePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            $seg = trim($seg);
            if ($seg === '' || $seg === '.') {
                continue;
            }
            abort_if($seg === '..' || str_contains($seg, "\0"), 422);
            $segments[] = $seg;
        }

        return implode('/', $segments);
    }

    /** A single path segment (filename / folder name), traversal-safe. */
    private function safeSegment(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim($name);
        abort_if($name === '' || $name === '.' || $name === '..' || str_contains($name, "\0"), 422);

        return $name;
    }

    /** @return array{id:int,name:string,type:string,read_only:bool} */
    private function present(StorageMount $mount): array
    {
        return [
            'id' => $mount->id,
            'name' => $mount->name,
            'type' => $mount->type,
            'read_only' => (bool) $mount->read_only,
        ];
    }

    private function reason(Throwable $e): string
    {
        // Surface a short, credential-free reason.
        $msg = $e->getMessage();

        return mb_strlen($msg) > 200 ? mb_substr($msg, 0, 200) : $msg;
    }
}
