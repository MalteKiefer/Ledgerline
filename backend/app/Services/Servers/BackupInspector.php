<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * How a host is backed up, and whether it actually happened.
 *
 * Written after looking at three real machines, none of which is backed up the
 * way a tool-detector would expect: one runs an Acronis agent, one a cron line
 * calling `/opt/backup.sh` at half past three, one the same script every four
 * hours into a log — and the panel host adds four hand-written database dumps
 * plus Plesk's own scheduler on top. So the centre of this is **schedules and
 * their evidence**, not a list of installed binaries. A tool nobody ever runs
 * protects nothing, and a five-line shell script run nightly protects a lot.
 *
 * Where a job redirects into a log, that file's age is the honest answer to
 * "did it run last night" — better than any inventory.
 *
 * Nothing here prints a secret. A cron line can carry `-pPASSWORD` or a
 * repository URL with credentials in it, so every command and configuration
 * line is redacted before it leaves the host's output.
 */
final class BackupInspector
{
    private const TIMEOUT = 30;

    /**
     * Everything in one round trip.
     *
     * The keyword filter is deliberately wide: it is better to show a cron line
     * that turns out to be something else than to miss the one script that is
     * the only backup this machine has.
     */
    private const SCRIPT = <<<'SH'
    printf "\n##LL:tools\n"
    for b in borg borgmatic restic kopia duplicity duplicati-cli rsnapshot rclone rsync bacula-fd bareos-fd veeamconfig proxmox-backup-client acrocmd zfs btrbk sanoid syncoid timeshift pg_dump mysqldump; do
      command -v "$b" >/dev/null 2>&1 && printf "%s\n" "$b"
    done
    printf "\n##LL:versions\n"
    for b in borg borgmatic restic kopia rclone duplicity proxmox-backup-client; do
      command -v "$b" >/dev/null 2>&1 && printf "%s\t%s\n" "$b" "$($b --version 2>/dev/null | head -1)"
    done
    printf "\n##LL:agents\n"; systemctl list-units --all --no-legend --plain 2>/dev/null | grep -Ei 'acronis|aakore|cyber-protect|veeam|bacula|bareos|backup|borg|restic|duplic|rsnapshot|kopia|btrbk|sanoid|urbackup' | awk '{print $1"\t"$3"\t"$4}' | head -20 || echo "__absent__"
    printf "\n##LL:timers\n"; systemctl list-timers --all --no-legend 2>/dev/null | grep -Ei 'backup|borg|restic|duplic|snapshot|rsync|veeam|bacula|kopia|btrbk|sanoid|dump' | head -12 || echo "__absent__"
    printf "\n##LL:cron\n"
    { crontab -l 2>/dev/null | sed 's/^/root|/'; for f in /etc/cron.d/*; do [ -f "$f" ] && sed "s|^|$f\||" "$f"; done; sed 's/^/\/etc\/crontab|/' /etc/crontab 2>/dev/null; } \
      | grep -Ei 'backup|borg|restic|rsync|duplic|snapshot|dump|acronis|kopia|rclone|tar ' | grep -v '|[[:space:]]*#' | head -25
    printf "\n##LL:crondirs\n"; ls /etc/cron.daily /etc/cron.weekly /etc/cron.monthly 2>/dev/null | grep -Ei 'backup|borg|restic|rsync|dump|snapshot' | head -10
    printf "\n##LL:acronis\n"; if command -v acrocmd >/dev/null 2>&1; then timeout 15 acrocmd list activities 2>/dev/null | head -14 || echo "__error__"; else echo "__absent__"; fi
    printf "\n##LL:repos\n"
    grep -hE '^[[:space:]]*(-|repository|snapshot_root|path)' /etc/borgmatic/config.yaml /etc/borgmatic.d/*.yaml /root/.config/borgmatic/config.yaml 2>/dev/null | head -10
    grep -E '^snapshot_root' /etc/rsnapshot.conf 2>/dev/null | head -3
    command -v rclone >/dev/null 2>&1 && rclone listremotes 2>/dev/null | head -10
    printf "\n##LL:end\n"
    SH;

    public function __construct(private ServerProbe $probe) {}

    /**
     * @return array{ok:bool,tools:list<array<string,string|null>>,agents:list<array<string,mixed>>,schedules:list<array<string,mixed>>,activities:list<array<string,string|null>>,repositories:list<string>,error:string|null}
     */
    public function inspect(Server $server): array
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return $this->empty('no_host_key');
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, self::SCRIPT, interactive: true, timeout: self::TIMEOUT);
        if (! $result['ok'] && $result['out'] === '') {
            return $this->empty('unreachable');
        }

        $s = $this->sections(substr($result['out'], 0, 512 * 1024));
        $logs = $this->logStats($server, $key, $this->cronLogPaths($s['cron'] ?? ''));

        return [
            'ok' => true,
            'tools' => $this->tools($s),
            'agents' => $this->agents($s['agents'] ?? ''),
            'schedules' => array_merge(
                $this->timers($s['timers'] ?? ''),
                $this->cronJobs($s['cron'] ?? '', $logs),
                $this->cronDirJobs($s['crondirs'] ?? ''),
            ),
            'activities' => $this->activities($s['acronis'] ?? ''),
            'repositories' => $this->repositories($s['repos'] ?? ''),
            'error' => null,
        ];
    }

    /**
     * A cron line that redirects into a log tells us where to look for proof.
     *
     * @return list<string>
     */
    private function cronLogPaths(string $raw): array
    {
        $paths = [];
        foreach ($this->lines($raw) as $line) {
            $path = $this->logPath($line);
            if ($path !== null) {
                $paths[$path] = true;
            }
        }

        return array_slice(array_keys($paths), 0, 10);
    }

    /**
     * The log a command redirects into, if it keeps one.
     *
     * `>/dev/null` is where output goes to be thrown away. Its timestamp moves
     * for reasons that have nothing to do with this job, so treating it as
     * evidence would put a confident "last ran 8 days ago" under a backup that
     * runs every quarter hour.
     */
    private function logPath(string $command): ?string
    {
        if (preg_match('#>>?\s*(/[^\s|>]+)#', $command, $m) !== 1) {
            return null;
        }

        return str_starts_with($m[1], '/dev/') ? null : $m[1];
    }

    /**
     * When each of those logs was last written, and how big it is.
     *
     * A second round trip only when there is something to ask about: this is
     * the difference between "a job is scheduled" and "a job ran last night".
     *
     * @param  list<string>  $paths
     * @return array<string,array{mtime:int,size:int}>
     */
    private function logStats(Server $server, string $key, array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $quoted = implode(' ', array_map(fn (string $p): string => $this->quote($p), $paths));
        $result = $this->probe->exec(
            ServerTarget::fromServer($server),
            $key,
            'stat -c "%n|%Y|%s" '.$quoted.' 2>/dev/null',
            interactive: true,
            timeout: 15,
        );

        $out = [];
        foreach ($this->lines($result['out']) as $line) {
            $f = explode('|', $line);
            if (count($f) === 3 && is_numeric($f[1]) && is_numeric($f[2])) {
                $out[$f[0]] = ['mtime' => (int) $f[1], 'size' => (int) $f[2]];
            }
        }

        return $out;
    }

    /**
     * Installed tools, with a version where the tool answers cheaply.
     *
     * Inventory only, and labelled as such in the interface: a tool that is
     * installed and never run protects nothing.
     *
     * @param  array<string,string>  $s
     * @return list<array<string,string|null>>
     */
    private function tools(array $s): array
    {
        $versions = [];
        foreach ($this->lines($s['versions'] ?? '') as $line) {
            $f = explode("\t", $line);
            if (count($f) === 2) {
                $versions[$f[0]] = trim($f[1]);
            }
        }

        $out = [];
        foreach ($this->lines($s['tools'] ?? '') as $name) {
            $out[] = ['name' => $name, 'version' => $versions[$name] ?? null];
        }

        return $out;
    }

    /**
     * Backup agents and daemons, with their unit state.
     *
     * @return list<array<string,mixed>>
     */
    private function agents(string $raw): array
    {
        if ($this->missing($raw)) {
            return [];
        }

        $out = [];
        foreach ($this->lines($raw) as $line) {
            $f = explode("\t", $line);
            if (count($f) < 3) {
                continue;
            }
            $out[] = ['unit' => $f[0], 'active' => $f[1] === 'active', 'state' => $f[2]];
        }

        return $out;
    }

    /**
     * systemd timers, with when they last fired and how that went.
     *
     * @return list<array<string,mixed>>
     */
    private function timers(string $raw): array
    {
        if ($this->missing($raw)) {
            return [];
        }

        $out = [];
        foreach ($this->lines($raw) as $line) {
            // NEXT and LAST both carry spaces, so the two unit names at the end
            // are the anchor: everything before them is the two timestamps.
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            $count = count($parts);
            if ($count < 4) {
                continue;
            }
            $unit = $parts[$count - 2];
            $activates = $parts[$count - 1];
            $times = implode(' ', array_slice($parts, 0, $count - 2));

            // NEXT ends at its "left"/"n/a", LAST at its "ago"/"n/a".
            $next = null;
            $last = null;
            if (preg_match('/^(.*?)\s+(?:\d\S*\s+)?(?:left|n\/a)\s+(.*?)\s+(?:\d\S*\s+)?(?:ago|n\/a)\s*$/', $times, $m) === 1) {
                $next = trim($m[1]) !== '' ? trim($m[1]) : null;
                $last = trim($m[2]) !== '' ? trim($m[2]) : null;
            }

            $stamp = $last !== null ? strtotime($last) : false;

            $out[] = [
                'kind' => 'timer',
                'name' => $unit,
                'runs' => $activates,
                // What it is set to do, in the same shape as a cron expression.
                'schedule' => $next !== null ? $next : $times,
                'log' => null,
                // A timer records when it last fired, so it needs no log to be
                // held to the same standard as a cron job.
                'last_run' => $stamp === false ? null : $stamp,
                'log_size' => null,
            ];
        }

        return $out;
    }

    /**
     * Cron jobs that look like backups, with the log they write to.
     *
     * @param  array<string,array{mtime:int,size:int}>  $logs
     * @return list<array<string,mixed>>
     */
    private function cronJobs(string $raw, array $logs): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $cut = strpos($line, '|');
            if ($cut === false) {
                continue;
            }
            $source = substr($line, 0, $cut);
            $entry = trim(substr($line, $cut + 1));
            if ($entry === '' || str_starts_with($entry, '#')) {
                continue;
            }

            // Five schedule fields, then possibly a user, then the command.
            $parts = preg_split('/\s+/', $entry, 6) ?: [];
            if (count($parts) < 6) {
                continue;
            }
            $schedule = implode(' ', array_slice($parts, 0, 5));
            $command = $parts[5];
            // /etc/cron.d and /etc/crontab carry a user column; a personal
            // crontab does not, and mistaking one for the other would cut the
            // first word off the command.
            if ($source !== 'root' && preg_match('/^[a-z_][a-z0-9_-]*\s+\S/i', $command) === 1) {
                $split = preg_split('/\s+/', $command, 2) ?: [];
                $command = $split[1] ?? $command;
            }

            $log = $this->logPath($command);
            $stat = $log !== null ? ($logs[$log] ?? null) : null;

            $out[] = [
                'kind' => 'cron',
                'name' => $source === 'root' ? 'crontab' : basename($source),
                'runs' => $this->redact($command),
                'schedule' => $schedule,
                'log' => $log,
                // The log's age is the evidence; the schedule is only intent.
                'last_run' => $stat['mtime'] ?? null,
                'log_size' => $stat['size'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Scripts dropped into /etc/cron.daily and friends.
     *
     * @return list<array<string,mixed>>
     */
    private function cronDirJobs(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $name) {
            $out[] = [
                'kind' => 'cron',
                'name' => $name,
                'runs' => $name,
                'schedule' => 'daily',
                'log' => null,
                'last_run' => null,
                'log_size' => null,
            ];
        }

        return $out;
    }

    /**
     * Acronis activity, when the agent is there and answers.
     *
     * @return list<array<string,string|null>>
     */
    private function activities(string $raw): array
    {
        if ($this->missing($raw) || str_contains($raw, '__error__')) {
            return [];
        }

        $out = [];
        foreach ($this->lines($raw) as $line) {
            if (str_starts_with($line, 'Name') || str_starts_with(ltrim($line), '---')) {
                continue;
            }
            $f = preg_split('/\s{2,}/', trim($line)) ?: [];
            if (count($f) < 6) {
                continue;
            }
            $out[] = [
                'name' => $f[0],
                'state' => $f[2],
                'started' => $f[4],
                'elapsed' => $f[5],
                // An activity still running has no outcome yet, which is not a
                // failure and must not be shown as one.
                'result' => count($f) >= 9 ? end($f) : null,
            ];
        }

        return array_slice($out, 0, 10);
    }

    /**
     * Where backups are written, as far as the configuration says.
     *
     * @return list<string>
     */
    private function repositories(string $raw): array
    {
        $out = [];
        foreach ($this->lines($raw) as $line) {
            $line = trim($line, "- \t");
            $line = preg_replace('/^(repository|snapshot_root|path)\s*:?\s*/i', '', $line) ?? $line;
            $line = trim($line, "\"' \t");
            if ($line !== '') {
                $out[$this->redact($line)] = true;
            }
        }

        return array_slice(array_keys($out), 0, 15);
    }

    /**
     * Take the secrets out of a command or a repository URL.
     *
     * A cron line is written by somebody who did not expect it to be shown, and
     * `mysqldump -pHunter2` is a common way to write one. So is a repository URL
     * with credentials in front of the host.
     */
    private function redact(string $value): string
    {
        $patterns = [
            '/(-p)(\S+)/' => '$1***',
            '/(--password[= ])(\S+)/i' => '$1***',
            '/((?:PG|MYSQL|BORG|RESTIC|B2|AWS)[A-Z_]*(?:PASSWORD|PASSPHRASE|KEY|SECRET|TOKEN)=)(\S+)/i' => '$1***',
            '/(\/\/[^:\/\s]+:)([^@\s]+)(@)/' => '$1***$3',
            '/(token[= ])(\S+)/i' => '$1***',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return mb_substr($value, 0, 300);
    }

    /** POSIX single-quoting, for the shell on the far side. */
    private function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    /**
     * @return array{ok:bool,tools:list<array<string,string|null>>,agents:list<array<string,mixed>>,schedules:list<array<string,mixed>>,activities:list<array<string,string|null>>,repositories:list<string>,error:string|null}
     */
    private function empty(string $error): array
    {
        return [
            'ok' => false, 'tools' => [], 'agents' => [], 'schedules' => [],
            'activities' => [], 'repositories' => [], 'error' => $error,
        ];
    }

    private function missing(string $raw): bool
    {
        return trim($raw) === '' || str_contains($raw, '__absent__');
    }

    /** @return list<string> */
    private function lines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            if (trim($line) !== '') {
                $out[] = rtrim($line);
            }
        }

        return $out;
    }

    /** @return array<string,string> */
    private function sections(string $raw): array
    {
        $out = [];
        $current = null;
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (str_starts_with($line, '##LL:')) {
                $current = substr($line, 5);
                $out[$current] = '';

                continue;
            }
            if ($current !== null) {
                $out[$current] .= $line."\n";
            }
        }

        return $out;
    }
}
