<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Admin container control (can:manage-global-settings). Proxies to the bounded
 * docker-control agent sidecar (docker/agent/agent.py) which alone holds the
 * docker socket. The app never touches the socket; it can only invoke the
 * agent's fixed action allowlist on this project's compose services.
 * Deliberate, owner-authorised power — see the security register.
 */
class DockerController extends Controller
{
    private const ACTIONS = ['restart', 'stop', 'start', 'recreate', 'pull', 'logs'];

    public function containers(Request $request): JsonResponse
    {
        if (! $this->configured()) {
            return response()->json(['configured' => false, 'operator' => $this->operatorCommands()]);
        }
        try {
            $res = Http::withToken($this->token())->connectTimeout(2)->timeout(20)->get($this->base().'/list');
            if (! $res->successful()) {
                return response()->json(['configured' => true, 'reachable' => false, 'operator' => $this->operatorCommands()], 200);
            }

            return response()->json([
                'configured' => true,
                'reachable' => true,
                'services' => $res->json('services') ?? [],
                'operator' => $this->operatorCommands(),
            ]);
        } catch (\Throwable) {
            return response()->json(['configured' => true, 'reachable' => false, 'operator' => $this->operatorCommands()], 200);
        }
    }

    public function action(Request $request): JsonResponse
    {
        $request->validate([
            'service' => ['required', 'string', 'max:64'],
            'action' => ['required', 'string', 'in:'.implode(',', self::ACTIONS)],
        ]);
        if (! $this->configured()) {
            return response()->json(['error' => 'agent_unconfigured'], 503);
        }
        try {
            $res = Http::withToken($this->token())->connectTimeout(2)
                ->timeout($request->string('action')->value() === 'pull' || $request->string('action')->value() === 'recreate' ? 900 : 120)
                ->post($this->base().'/action', [
                    'service' => $request->string('service')->value(),
                    'action' => $request->string('action')->value(),
                ]);

            return response()->json($res->json() ?? ['ok' => false], $res->status());
        } catch (\Throwable) {
            return response()->json(['error' => 'agent_unreachable'], 503);
        }
    }

    private function configured(): bool
    {
        return $this->base() !== '' && $this->token() !== '';
    }

    private function base(): string
    {
        $u = config('docker.agent_url');

        return is_string($u) ? rtrim($u, '/') : '';
    }

    private function token(): string
    {
        $t = config('docker.agent_token');

        return is_string($t) ? $t : '';
    }

    /** @return array<string,string> */
    private function operatorCommands(): array
    {
        return [
            'enable' => 'set DOCKER_AGENT_TOKEN in .env, then: docker compose --profile agent up -d agent',
            'restart' => 'docker compose --profile <profile> restart <service>',
            'update' => 'docker compose --profile <profile> pull <service> && docker compose --profile <profile> up -d <service>',
        ];
    }
}
