<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Services\Servers\ServerControl;
use App\Services\Servers\VpnInspector;
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
    public function __construct(
        private ServerControl $control,
        private VpnInspector $vpn,
    ) {}

    /** Which overlay network this host is on, and how it is doing. */
    public function vpn(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->vpn->inspect($server))->header('Cache-Control', 'no-store');
    }

    /**
     * Bring an overlay network up, down or round again.
     *
     * Audited before the command is sent, not after: taking down the network
     * this app reaches the host through means the answer may never come back,
     * and an action that leaves no trace when it works is worse than none.
     */
    public function vpnAction(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'provider' => ['required', 'string', 'max:32'],
            'action' => ['required', Rule::in(ServerControl::VPN_ACTIONS)],
            'unit' => ['nullable', 'string', 'max:128'],
        ]);

        $provider = $request->string('provider')->value();
        $action = $request->string('action')->value();
        $unit = $request->string('unit')->value();

        AuditLog::record('server.vpn_action', $server, [
            'server' => $server->name,
            'provider' => $provider,
            'action' => $action,
            'unit' => $unit === '' ? null : $unit,
        ], $user->id);

        $result = $this->control->vpnAction($server, $provider, $action, $unit);

        return response()->json($result, $result['error'] === 'invalid_selection' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

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

    /**
     * Reboot or shut the machine down.
     *
     * The most consequential endpoint in the module, so it is audited before
     * the command is sent as well as after: a machine that never comes back
     * should still leave a record of who asked and for what.
     */
    public function power(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'action' => ['required', Rule::in(ServerControl::POWER_ACTIONS)],
        ]);

        $action = $request->string('action')->value();

        AuditLog::record('server.power', $server, [
            'server' => $server->name,
            'host' => $server->host,
            'action' => $action,
        ], $user->id);

        $result = $this->control->power($server, $action);

        return response()->json($result, $result['error'] === 'invalid_selection' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** End somebody's login session on the host. */
    public function killSession(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'tty' => ['required', 'string', 'max:32'],
        ]);

        $tty = $request->string('tty')->value();
        $result = $this->control->killSession($server, $tty);

        AuditLog::record('server.session_killed', $server, [
            'server' => $server->name,
            'tty' => $tty,
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, $result['error'] === 'invalid_selection' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
