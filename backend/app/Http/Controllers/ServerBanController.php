<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Services\Servers\BanManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The ban lists of fail2ban and CrowdSec, and changes to them.
 *
 * Unbanning is the one action here somebody performs in a hurry — usually
 * their own address, usually while locked out of something else — so it is
 * audited with the address named. Banning and allow-listing are audited the
 * same way for the same reason: a rule nobody remembers adding is worse than
 * no rule.
 */
class ServerBanController extends Controller
{
    public function __construct(private BanManager $bans) {}

    public function index(Request $request, Server $server): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->bans->bans($server))->header('Cache-Control', 'no-store');
    }

    public function act(Request $request, Server $server): JsonResponse
    {
        $user = $this->requireUser($request);

        $request->validate([
            'daemon' => ['required', Rule::in(BanManager::DAEMONS)],
            'action' => ['required', Rule::in(BanManager::ACTIONS)],
            'ip' => ['required', 'string', 'ip'],
            'jail' => ['nullable', 'string', 'max:64'],
        ]);

        $daemon = $request->string('daemon')->value();
        $action = $request->string('action')->value();
        $ip = $request->string('ip')->value();
        $jail = $request->string('jail')->value();

        $result = $this->bans->act($server, $daemon, $action, $ip, $jail);

        AuditLog::record('server.ban_action', $server, [
            'server' => $server->name,
            'daemon' => $daemon,
            'action' => $action,
            'ip' => $ip,
            'jail' => $jail,
            'ok' => $result['ok'],
        ], $user->id);

        $refused = in_array($result['error'], ['invalid_selection', 'invalid_ip', 'jail_required'], true);

        return response()->json($result, $refused ? 422 : 200)->header('Cache-Control', 'no-store');
    }
}
