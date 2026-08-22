<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ApplyServerUpdates;
use App\Models\AuditLog;
use App\Models\Server;
use App\Services\Servers\DiskUsageInspector;
use App\Services\Servers\PackageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Maintenance: what is using the space, and what needs updating.
 *
 * Both answer a question the snapshot leaves half-finished. "94% full" without
 * "which directory" sends somebody to ssh and du by hand; "14 updates" without
 * "which" says nothing about whether it can wait until the weekend.
 */
class ServerMaintenanceController extends Controller
{
    public function __construct(
        private readonly DiskUsageInspector $disk,
        private readonly PackageManager $packages,
    ) {}

    /** The largest directories under a path. */
    public function diskUsage(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        $request->validate([
            'path' => ['required', 'string', 'max:4096'],
            'depth' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $result = $this->disk->inspect(
            $server,
            $request->string('path')->value(),
            $request->integer('depth') ?: 1,
        );

        return response()->json($result, $result['error'] === 'invalid_path' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** Pending updates, security ones first. */
    public function updates(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->packages->pending($server))->header('Cache-Control', 'no-store');
    }

    /**
     * Apply them.
     *
     * Queued, never inline: an upgrade takes minutes, and a request that holds
     * a worker for minutes is how this application has already broken itself
     * twice. The answer is 202 and the result arrives as a notification.
     */
    public function applyUpdates(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        // Audited before dispatch, not after: a machine that does not come back
        // from an upgrade should still have left a record that one was started.
        AuditLog::record('server.updates_applied', $server, [
            'server' => $server->name,
            'host' => $server->host,
        ], $user->id);

        ApplyServerUpdates::dispatch($server->id);

        return response()->json(['queued' => true], 202)->header('Cache-Control', 'no-store');
    }
}
