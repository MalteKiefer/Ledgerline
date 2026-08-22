<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Services\Servers\DockerInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Docker engine of a monitored host.
 *
 * Reading is a read like any other. Stopping, killing, removing and pruning
 * change the machine, so each is audited with the target named — a container
 * nobody remembers removing is worse than one that is still there.
 */
class ServerDockerController extends Controller
{
    public function __construct(private DockerInspector $docker) {}

    public function show(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        $state = $this->docker->inspect($server);

        return response()->json(['available' => $state['present'] && $state['error'] === null] + $state)
            ->header('Cache-Control', 'no-store');
    }

    public function act(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'action' => ['required', Rule::in(DockerInspector::ACTIONS)],
        ]);

        $name = $request->string('name')->value();
        $action = $request->string('action')->value();

        $result = $this->docker->act($server, $name, $action);

        AuditLog::record('server.docker_action', $server, [
            'server' => $server->name,
            'container' => $name,
            'action' => $action,
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, $result['error'] === 'invalid_selection' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    public function prune(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'target' => ['required', Rule::in(DockerInspector::PRUNE_TARGETS)],
        ]);

        $target = $request->string('target')->value();
        $result = $this->docker->prune($server, $target);

        AuditLog::record('server.docker_prune', $server, [
            'server' => $server->name,
            'target' => $target,
            'ok' => $result['ok'],
        ], $user->id);

        return response()->json($result, $result['error'] === 'invalid_selection' ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
