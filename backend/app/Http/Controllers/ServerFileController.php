<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\SftpBrowser;
use App\Support\FileBrowserUnlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file browser for a monitored host, over SFTP.
 *
 * Every endpoint here needs an unlock grant, which costs the account password.
 * The reason is reach, not caution for its own sake: with the root access this
 * module now requires, this reads and overwrites any file on the target. A
 * terminal at least announces what it is; a file listing looks harmless and is
 * not.
 *
 * Writing, deleting, renaming and changing modes are audited with the path
 * named. Reading is not — a listing per click would drown the log and hide the
 * entries that matter.
 */
class ServerFileController extends Controller
{
    public function __construct(private SftpBrowser $sftp) {}

    /** Exchange the account password for a short-lived grant. */
    public function unlock(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate(['password' => ['required', 'string']]);

        // Unconditional: no "recently confirmed" shortcut, no exemption for a
        // native device token. A stolen bearer alone does not open a filesystem.
        if (! Hash::check($request->string('password')->value(), (string) $user->password)) {
            return response()->json(['error' => 'bad_password'], 422)->header('Cache-Control', 'no-store');
        }

        AuditLog::record('server.files_unlocked', $server, [
            'server' => $server->name,
            'host' => $server->host,
        ], $user->id);

        return response()->json([
            'token' => FileBrowserUnlock::issue($user->id, (int) $server->id),
            'expires_in' => 900,
        ])->header('Cache-Control', 'no-store');
    }

    public function lock(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);
        FileBrowserUnlock::revoke($user->id, (int) $server->id, $this->grant($request));

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);
        if (! $this->unlocked($request, $user, $server)) {
            return $this->locked();
        }

        $request->validate(['path' => ['required', 'string', 'max:4096']]);

        $result = $this->sftp->list($server, $request->string('path')->value());

        return response()->json($result, $result['error'] === 'invalid_path' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** Read a file for preview or editing. */
    public function read(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);
        if (! $this->unlocked($request, $user, $server)) {
            return $this->locked();
        }

        $request->validate(['path' => ['required', 'string', 'max:4096']]);

        $result = $this->sftp->read($server, $request->string('path')->value());

        // A binary is not an error the caller made, so it answers 200 with the
        // flag set: the interface shows "no preview" rather than a failure.
        $status = match ($result['error']) {
            'invalid_path' => 422,
            'not_found' => 404,
            default => 200,
        };

        return response()->json($result, $status)->header('Cache-Control', 'no-store');
    }

    /** Stream a file to the client. */
    public function download(Request $request, Server $server): JsonResponse|StreamedResponse
    {
        $user = $this->requireUser($request);
        if (! $this->unlocked($request, $user, $server)) {
            return response()->json(['error' => 'locked'], 403)->header('Cache-Control', 'no-store');
        }

        $request->validate(['path' => ['required', 'string', 'max:4096']]);
        $path = $request->string('path')->value();

        $got = $this->sftp->download($server, $path);
        if (! $got['ok'] || $got['file'] === null) {
            return response()->json(['error' => $got['error']], $got['error'] === 'not_found' ? 404 : 422)
                ->header('Cache-Control', 'no-store');
        }

        $file = $got['file'];
        $handle = fopen($file->path(), 'rb');
        if ($handle === false) {
            return response()->json(['error' => 'failed'], 500)->header('Cache-Control', 'no-store');
        }

        // Streamed in chunks so a large file never sits in memory, and the temp
        // handle is held by the closure so RAII cleanup waits for the last byte.
        return response()->stream(function () use ($handle, $file): void {
            while (! feof($handle)) {
                echo (string) fread($handle, 262144);
                flush();
            }
            fclose($handle);
            unset($file);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) filesize($file->path()),
            'Content-Disposition' => 'attachment; filename="'.addslashes(basename($path)).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Save an edited file. */
    public function write(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);
        if (! $this->unlocked($request, $user, $server)) {
            return $this->locked();
        }

        $request->validate([
            'path' => ['required', 'string', 'max:4096'],
            'content' => ['present', 'string'],
        ]);

        $path = $request->string('path')->value();
        $result = $this->sftp->write($server, $path, $request->string('content')->value());

        AuditLog::record('server.file_written', $server, [
            'server' => $server->name,
            'path' => $path,
            'bytes' => strlen($request->string('content')->value()),
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, $result['error'] === 'invalid_path' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** Upload a file into a directory. */
    public function upload(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);
        if (! $this->unlocked($request, $user, $server)) {
            return $this->locked();
        }

        $request->validate([
            'path' => ['required', 'string', 'max:4096'],
            'file' => ['required', 'file', 'max:'.(int) (SftpBrowser::MAX_UPLOAD_BYTES / 1024)],
        ]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            return response()->json(['ok' => false, 'error' => 'failed'], 422)->header('Cache-Control', 'no-store');
        }

        $dir = rtrim($request->string('path')->value(), '/');
        $remote = $dir.'/'.$upload->getClientOriginalName();

        $result = $this->sftp->upload($server, $upload->getRealPath() ?: '', $remote);

        AuditLog::record('server.file_uploaded', $server, [
            'server' => $server->name,
            'path' => $remote,
            'bytes' => $upload->getSize(),
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, $result['error'] === 'invalid_path' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** Create a directory, delete, rename, or change mode. */
    public function mutate(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);
        if (! $this->unlocked($request, $user, $server)) {
            return $this->locked();
        }

        $request->validate([
            'action' => ['required', Rule::in(['mkdir', 'rmdir', 'rm', 'rename', 'chmod'])],
            'path' => ['required', 'string', 'max:4096'],
            'target' => ['nullable', 'string', 'max:4096'],
            'mode' => ['nullable', 'string', 'max:4'],
        ]);

        $action = $request->string('action')->value();
        $path = $request->string('path')->value();

        $result = $this->sftp->mutate(
            $server,
            $action,
            $path,
            $request->string('target')->value(),
            $request->string('mode')->value(),
        );

        AuditLog::record('server.file_mutated', $server, [
            'server' => $server->name,
            'action' => $action,
            'path' => $path,
            'target' => $request->string('target')->value(),
            'mode' => $request->string('mode')->value(),
            'ok' => $result['ok'],
        ], $user->id);

        $refused = in_array($result['error'], ['invalid_path', 'invalid_selection'], true);

        return response()->json($result, $refused ? 422 : 200)->header('Cache-Control', 'no-store');
    }

    private function unlocked(Request $request, User $user, Server $server): bool
    {
        return FileBrowserUnlock::valid($user->id, (int) $server->id, $this->grant($request));
    }

    /** The grant travels in a header, so it never lands in a URL or an access log. */
    private function grant(Request $request): string
    {
        return trim((string) $request->header('X-File-Grant', ''));
    }

    private function locked(): JsonResponse
    {
        return response()->json(['error' => 'locked'], 403)->header('Cache-Control', 'no-store');
    }
}
