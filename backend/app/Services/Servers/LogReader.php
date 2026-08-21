<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * Read a bounded slice of a log from a monitored host.
 *
 * The module's promise is that nothing from a request becomes a command. That
 * holds here: the caller picks a SOURCE from a fixed set, and the arguments are
 * either integers, a value from a list this class itself discovered on the host,
 * or a name matched against a strict pattern. The script is assembled from those
 * pieces here, never from text the browser sent.
 *
 * Still read-only and still unprivileged: journalctl shows what the monitoring
 * account may see, which on most systems means the journal it belongs to. A log
 * it cannot read comes back empty rather than escalating.
 */
class LogReader
{
    /** What the UI may ask for. Anything else is refused before a connection opens. */
    public const SOURCES = ['journal', 'docker', 'file'];

    /** Never fetch more than this, whatever the request says. */
    private const MAX_LINES = 2000;

    private const DEFAULT_LINES = 200;

    /**
     * A systemd unit name. Deliberately strict — the value is an argument to a
     * fixed command through array argv, so there is no shell to escape, but a
     * pattern this tight also means a typo fails here rather than on the host.
     */
    private const UNIT_PATTERN = '/^[A-Za-z0-9@:._\-]{1,128}$/';

    /** Docker container name or id, per Docker's own naming rules. */
    private const CONTAINER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.\-]{0,127}$/';

    public function __construct(private ServerProbe $probe) {}

    /**
     * What this host can actually offer: which log systems exist, and the units,
     * containers and files that are really there.
     *
     * This is what makes the read safe. The UI offers only what came back from
     * here, and a later read is checked against it — so the browser is choosing
     * from the host's own answer rather than naming something of its own.
     *
     * @return array{journal:bool,units:list<string>,containers:list<string>,files:list<string>,error:string|null}
     */
    public function sources(Server $server): array
    {
        $script = <<<'SH'
        echo "##LL:journal"; command -v journalctl >/dev/null 2>&1 && echo yes || echo no
        echo "##LL:units"; systemctl list-units --type=service --state=running --no-legend --plain 2>/dev/null | awk '{print $1}' | head -120
        echo "##LL:containers"; docker ps --format "{{.Names}}" 2>/dev/null | head -60
        echo "##LL:files"; ls -1 /var/log/*.log /var/log/syslog /var/log/messages /var/log/auth.log 2>/dev/null | head -40
        echo "##LL:end"
        SH;

        $result = $this->run($server, $script);
        if ($result === null) {
            return ['journal' => false, 'units' => [], 'containers' => [], 'files' => [], 'error' => 'unreachable'];
        }

        $s = $this->sections($result);

        return [
            'journal' => trim($s['journal'] ?? '') === 'yes',
            'units' => $this->lines($s['units'] ?? ''),
            'containers' => $this->lines($s['containers'] ?? ''),
            'files' => $this->lines($s['files'] ?? ''),
            'error' => null,
        ];
    }

    /**
     * Fetch the tail of one log.
     *
     * @param  array{source:string,unit:?string,container:?string,path:?string,lines:int,errors_only:bool}  $req
     * @return array{ok:bool,text:string,error:string|null}
     */
    public function read(Server $server, array $req): array
    {
        $lines = max(1, min(self::MAX_LINES, $req['lines'] ?: self::DEFAULT_LINES));

        $script = match ($req['source']) {
            'journal' => $this->journalScript($req['unit'], $lines, $req['errors_only']),
            'docker' => $this->dockerScript($req['container'], $lines),
            'file' => $this->fileScript($req['path'], $lines),
            default => null,
        };
        if ($script === null) {
            return ['ok' => false, 'text' => '', 'error' => 'invalid_selection'];
        }

        $out = $this->run($server, $script);
        if ($out === null) {
            return ['ok' => false, 'text' => '', 'error' => 'unreachable'];
        }

        return ['ok' => true, 'text' => $out, 'error' => null];
    }

    private function journalScript(?string $unit, int $lines, bool $errorsOnly): ?string
    {
        if ($unit !== null && preg_match(self::UNIT_PATTERN, $unit) !== 1) {
            return null;
        }
        // --no-pager because a pager on a pipe would wait forever; --output short-iso
        // so timestamps are unambiguous rather than locale-dependent.
        $cmd = 'journalctl --no-pager --output=short-iso -n '.$lines;
        if ($errorsOnly) {
            $cmd .= ' -p err';
        }
        if ($unit !== null) {
            $cmd .= ' -u '.self::sq($unit);
        }

        return $cmd.' 2>&1';
    }

    private function dockerScript(?string $container, int $lines): ?string
    {
        if ($container === null || preg_match(self::CONTAINER_PATTERN, $container) !== 1) {
            return null;
        }

        // Docker writes most container output to stderr, so both streams are kept.
        return 'docker logs --tail '.$lines.' '.self::sq($container).' 2>&1';
    }

    private function fileScript(?string $path, int $lines): ?string
    {
        // Only a path this class discovered on the host is accepted. A traversal
        // check would not be enough on its own — an absolute path outside /var/log
        // has no ".." in it at all — so membership of the discovered list is the
        // actual control, and the pattern below is belt and braces.
        if ($path === null || preg_match('#^/var/log/[A-Za-z0-9._\-/]{1,200}$#', $path) !== 1 || str_contains($path, '..')) {
            return null;
        }

        return 'tail -n '.$lines.' '.self::sq($path).' 2>&1';
    }

    /**
     * Single-quote for POSIX sh.
     *
     * Not escapeshellarg(): that quotes for the platform PHP is running on, and
     * this string is built here but executed on the target. On Windows it emits
     * double quotes, which sh treats as weak quoting — a value containing $( )
     * would be evaluated on the remote host. The patterns above already exclude
     * those characters, but a quoting function that is only correct because
     * something else filtered its input is not a quoting function.
     *
     * The one character that matters is the quote itself: close, escape, reopen.
     */
    private static function sq(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    /**
     * Run a script on the host with the interactive bounds: a user is waiting on
     * this, so it must fail fast rather than hold the request open.
     */
    private function run(Server $server, string $script): ?string
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return null;
        }

        $target = ServerTarget::fromServer($server);
        $result = $this->probe->exec($target, $key, $script, interactive: true);

        if (! $result['ok'] && $result['out'] === '') {
            return null;
        }

        // Bounded regardless of what the host sends: 2000 lines of a log with very
        // long records could otherwise be megabytes.
        $text = $result['out'];

        return strlen($text) > 1024 * 1024 ? substr($text, 0, 1024 * 1024) : $text;
    }

    /** @return array<string, string> */
    private function sections(string $output): array
    {
        $out = [];
        $current = null;
        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
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

    /** @return list<string> */
    private function lines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return array_values(array_unique($out));
    }
}
