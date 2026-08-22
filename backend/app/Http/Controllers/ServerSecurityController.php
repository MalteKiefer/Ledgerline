<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\Servers\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What is guarding a monitored host.
 *
 * Read-only. Inline SSH with the interactive timeouts, like the log reader —
 * somebody is waiting on it — and throttled, because it asks the host a dozen
 * questions in one round trip.
 */
class ServerSecurityController extends Controller
{
    public function __construct(private SecurityAudit $audit) {}

    public function show(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->audit->audit($server))->header('Cache-Control', 'no-store');
    }
}
