<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Services\Servers\ServerControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Services and processes on a monitored host.
 *
 * Listing is a read like any other. Acting is not: starting, stopping and
 * killing are the first changes this module makes to a target outside a
 * terminal, so every action is audited with the server and the target named.
 *
 * Inline SSH with the interactive timeouts, like the connection test and the
 * log reader — somebody is waiting on the answer — and throttled harder on the
 * acting endpoints than on the listing ones.
 */
class ServerControlController extends Controller
{
    public function __construct(private ServerControl $control) {}

    public function services(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->control->services($server))->header('Cache-Control', 'no-store');
    }

    public function processes(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->control->processes($server))->header('Cache-Control', 'no-store');
    }

    public function serviceAction(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'unit' => ['required', 'string', 'max:128'],
            'action' => ['required', Rule::in(ServerControl::SERVICE_ACTIONS)],
        ]);

        $unit = $request->string('unit')->value();
        $action = $request->string('action')->value();

        $result = $this->control->serviceAction($server, $unit, $action);

        AuditLog::record('server.service_action', $server, [
            'server' => $server->name,
            'unit' => $unit,
            'action' => $action,
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, $result['error'] === 'invalid_selection' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    public function processSignal(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'pid' => ['required', 'integer', 'min:2', 'max:4194304'],
            'signal' => ['required', Rule::in(ServerControl::PROCESS_SIGNALS)],
        ]);

        $pid = $request->integer('pid');
        $signal = $request->string('signal')->value();

        $result = $this->control->processSignal($server, $pid, $signal);

        AuditLog::record('server.process_signal', $server, [
            'server' => $server->name,
            'pid' => $pid,
            'signal' => $signal,
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, in_array($result['error'], ['invalid_selection', 'refused_pid1'], true) ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
