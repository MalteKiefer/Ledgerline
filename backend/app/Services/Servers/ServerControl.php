<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * List and act on services and processes of a monitored host.
 *
 * These are the first ACTIONS the module takes on a target beyond reading. The
 * boundary was already crossed by the terminal — anything here can be typed
 * into a shell — but the terminal at least required the account password, so
 * this deliberately keeps its own fences: the verb comes from a fixed set, the
 * unit is matched against a strict pattern, the pid is an integer, and the
 * script is assembled here rather than accepted from a request.
 *
 * These commands need privilege that the module otherwise does not want. A
 * monitoring account that follows the documented setup cannot restart a service
 * or kill somebody else's process, and the permission error is passed through
 * rather than swallowed — better an honest refusal from the host than a button
 * that silently does nothing.
 */
class ServerControl
{
    /** What may be asked of a service. */
    public const SERVICE_ACTIONS = ['start', 'stop', 'restart', 'reload'];

    /** What may be asked of a process. Two signals: ask politely, then insist. */
    public const PROCESS_SIGNALS = ['TERM', 'KILL'];

    /**
     * What may be asked of the machine itself.
     *
     * `reboot_force` is deliberately separate from `reboot`: the ordinary one
     * asks systemd to stop units in order, the forced one skips that. They read
     * alike and behave nothing alike, so they are never the same button.
     */
    public const POWER_ACTIONS = ['reboot', 'reboot_force', 'poweroff', 'cancel'];

    /** tty names as `who` prints them. Anything else never reaches the host. */
    private const TTY_PATTERN = '/^(pts\/\d{1,4}|tty\d{1,3})$/';

    private const UNIT_PATTERN = '/^[A-Za-z0-9@:._\-]{1,128}$/';

    public function __construct(private ServerProbe $probe) {}

    /**
     * Every service systemd knows about, with its state.
     *
     * @return array{ok:bool,units:list<array{name:string,load:string,active:string,sub:string,description:string}>,error:string|null}
     */
    public function services(Server $server): array
    {
        // --all so a stopped unit is listed too: a service you cannot see is a
        // service you cannot start.
        $out = $this->run($server, 'systemctl list-units --type=service --all --no-legend --plain --no-pager 2>/dev/null | head -300');
        if ($out === null) {
            return ['ok' => false, 'units' => [], 'error' => 'unreachable'];
        }

        $units = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($out)) ?: [] as $line) {
            $f = preg_split('/\s+/', trim($line), 5) ?: [];
            if (count($f) < 4 || ! str_ends_with($f[0], '.service')) {
                continue;
            }
            $units[] = [
                'name' => $f[0],
                'load' => $f[1],
                'active' => $f[2],
                'sub' => $f[3],
                'description' => trim($f[4] ?? ''),
            ];
        }

        return ['ok' => true, 'units' => $units, 'error' => null];
    }

    /**
     * The processes worth looking at, by memory then CPU.
     *
     * @return array{ok:bool,processes:list<array{pid:int,user:string,cpu:float,mem:float,rss_kb:int,command:string}>,error:string|null}
     */
    public function processes(Server $server): array
    {
        // Sorted by resident memory: a snapshot cannot say anything honest about
        // instantaneous CPU, but it can say exactly what is holding memory.
        $out = $this->run($server, 'ps -eo pid=,user=,pcpu=,pmem=,rss=,comm= 2>/dev/null | sort -k5 -rn | head -100');
        if ($out === null) {
            return ['ok' => false, 'processes' => [], 'error' => 'unreachable'];
        }

        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($out)) ?: [] as $line) {
            $f = preg_split('/\s+/', trim($line), 6) ?: [];
            if (count($f) < 6 || ! is_numeric($f[0])) {
                continue;
            }
            $rows[] = [
                'pid' => (int) $f[0],
                'user' => $f[1],
                'cpu' => (float) $f[2],
                'mem' => (float) $f[3],
                'rss_kb' => (int) $f[4],
                'command' => $f[5],
            ];
        }

        return ['ok' => true, 'processes' => $rows, 'error' => null];
    }

    /**
     * Act on a service.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function serviceAction(Server $server, string $unit, string $action): array
    {
        if (! in_array($action, self::SERVICE_ACTIONS, true) || preg_match(self::UNIT_PATTERN, $unit) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }

        // 2>&1 so the host's own refusal reaches the reader. "Interactive
        // authentication required" is the answer to "why did nothing happen",
        // and hiding it would make an unprivileged account look broken.
        $out = $this->run($server, 'systemctl '.$action.' '.self::sq($unit).' 2>&1; echo "##LL:rc=$?"');
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        return $this->outcome($out);
    }

    /**
     * Signal a process.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function processSignal(Server $server, int $pid, string $signal): array
    {
        if (! in_array($signal, self::PROCESS_SIGNALS, true) || $pid < 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }
        // Never pid 1. Killing init takes the machine down, and no interface
        // should make that one mis-click away.
        if ($pid === 1) {
            return ['ok' => false, 'output' => '', 'error' => 'refused_pid1'];
        }

        $out = $this->run($server, 'kill -'.$signal.' '.$pid.' 2>&1; echo "##LL:rc=$?"');
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        return $this->outcome($out);
    }

    /**
     * Reboot or shut the machine down.
     *
     * The most consequential thing this module can do, so it is its own verb
     * set rather than a special case of a service action: nothing here should
     * be reachable by passing an unusual unit name.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function power(Server $server, string $action): array
    {
        if (! in_array($action, self::POWER_ACTIONS, true)) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }

        // A reboot cuts the connection, which is success, not failure. Detaching
        // and answering immediately is the only honest reading — waiting would
        // report a timeout for a machine that did exactly what was asked.
        $script = match ($action) {
            'reboot' => 'systemctl reboot 2>&1 || shutdown -r +0 2>&1; echo "##LL:rc=$?"',
            // Two --force: the first skips the ordered shutdown, the second is
            // the immediate kernel reboot. This is the "it is wedged" button.
            'reboot_force' => 'systemctl reboot --force --force 2>&1; echo "##LL:rc=$?"',
            'poweroff' => 'systemctl poweroff 2>&1 || shutdown -h +0 2>&1; echo "##LL:rc=$?"',
            'cancel' => 'shutdown -c 2>&1; echo "##LL:rc=$?"',
        };

        $out = $this->run($server, $script);
        if ($out === null) {
            // The link dropping mid-reboot is the expected outcome for the
            // three destructive verbs, and reporting it as an error would
            // teach the operator to ignore real errors.
            return $action === 'cancel'
                ? ['ok' => false, 'output' => '', 'error' => 'unreachable']
                : ['ok' => true, 'output' => '', 'error' => null];
        }

        return $this->outcome($out);
    }

    /**
     * End somebody's login session.
     *
     * Signals every process attached to the terminal, which is what "log a user
     * out" actually means — killing only the shell leaves whatever it started
     * behind.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    public function killSession(Server $server, string $tty): array
    {
        if (preg_match(self::TTY_PATTERN, $tty) !== 1) {
            return ['ok' => false, 'output' => '', 'error' => 'invalid_selection'];
        }

        $out = $this->run($server, 'pkill -KILL -t '.self::sq($tty).' 2>&1; echo "##LL:rc=$?"');
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'error' => 'unreachable'];
        }

        $result = $this->outcome($out);
        // pkill exits 1 when it matched nothing, which here means the session
        // had already ended — the state the caller wanted either way.
        if (! $result['ok'] && $result['output'] === '') {
            return ['ok' => true, 'output' => '', 'error' => null];
        }

        return $result;
    }

    /**
     * Split the trailing exit-code marker from whatever the command said.
     *
     * @return array{ok:bool,output:string,error:string|null}
     */
    private function outcome(string $out): array
    {
        $rc = 0;
        if (preg_match('/##LL:rc=(\d+)\s*$/', $out, $m) === 1) {
            $rc = (int) $m[1];
            $out = (string) preg_replace('/##LL:rc=\d+\s*$/', '', $out);
        }

        return ['ok' => $rc === 0, 'output' => trim($out), 'error' => $rc === 0 ? null : 'command_failed'];
    }

    /** Single-quote for POSIX sh — the script runs on the target, not here. */
    private static function sq(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    private function run(Server $server, string $script): ?string
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return null;
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, interactive: true);
        if (! $result['ok'] && $result['out'] === '') {
            return null;
        }

        $text = $result['out'];

        return strlen($text) > 512 * 1024 ? substr($text, 0, 512 * 1024) : $text;
    }
}
