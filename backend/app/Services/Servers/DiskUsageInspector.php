<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;

/**
 * What is actually using the space.
 *
 * "The disk is 94% full" is half an answer; the other half is which directory
 * to look in, and without it somebody ends up running du by hand over ssh
 * anyway. On demand rather than in the probe: du walks the whole tree, which
 * takes minutes on a large filesystem and has no business running every five
 * minutes on a schedule.
 *
 * Bounded three ways — depth, a hard timeout, and one filesystem only — because
 * an unbounded du on a host with a network mount is a good way to make the
 * machine unhappy while you watch.
 */
final class DiskUsageInspector
{
    /**
     * Long enough for a real filesystem, short enough not to hold a worker.
     *
     * A tree with millions of files will not finish in this, and that is the
     * right trade: the answer arrives partial or not at all rather than the
     * request sitting on a worker while somebody watches a spinner.
     */
    private const TIMEOUT = 40;

    public function __construct(private ServerProbe $probe) {}

    /**
     * The largest directories under one path.
     *
     * @return array{ok:bool,path:string,entries:list<array{path:string,size_kb:int}>,truncated:bool,error:string|null}
     */
    public function inspect(Server $server, string $path, int $depth = 1): array
    {
        $path = SftpBrowser::normalisePath($path);
        if ($path === null) {
            return ['ok' => false, 'path' => '', 'entries' => [], 'truncated' => false, 'error' => 'invalid_path'];
        }

        $depth = max(1, min(3, $depth));

        // -x keeps it on one filesystem: without it a du of / on a container
        // host walks every overlay mount and reports the same bytes many times.
        // The path is single-quoted for the target's shell, never interpolated
        // into anything that parses it further.
        // `timeout` on the target as well as here: without it the far side
        // keeps walking the tree long after we have stopped listening.
        //
        // The output is held in a variable rather than piped straight into
        // sort, because a pipe throws away du's exit status — and 124 is the
        // one status that matters here. A tree too large to finish returns a
        // partial answer that looks exactly like a complete one, so it has to
        // say so.
        $script = 'out=$(timeout '.self::TIMEOUT.' du -x -k --max-depth='.$depth.' -- '.self::quote($path).' 2>/dev/null); '
            .'rc=$?; printf "%s" "$out" | sort -rn | head -40; echo; echo "##LL:rc=$rc"';

        $key = (string) $server->host_key;
        if ($key === '') {
            return ['ok' => false, 'path' => $path, 'entries' => [], 'truncated' => false, 'error' => 'no_host_key'];
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, timeout: self::TIMEOUT + 5);
        if (! $result['ok'] && $result['out'] === '') {
            return ['ok' => false, 'path' => $path, 'entries' => [], 'truncated' => false, 'error' => 'unreachable'];
        }
        $out = substr($result['out'], 0, 256 * 1024);

        // 124 is `timeout` killing du, which means the numbers below cover
        // only part of the tree.
        $truncated = str_contains($out, '##LL:rc=124');

        $entries = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($out)) ?: [] as $line) {
            if (str_starts_with($line, '##LL:rc=')) {
                continue;
            }
            $parts = preg_split('/\t/', trim($line), 2) ?: [];
            if (count($parts) < 2 || ! ctype_digit(trim($parts[0]))) {
                continue;
            }
            $entries[] = ['path' => trim($parts[1]), 'size_kb' => (int) trim($parts[0])];
        }

        // du prints the total for the path itself; it is not a finding, it is
        // the thing being broken down.
        $entries = array_values(array_filter($entries, static fn (array $e): bool => $e['path'] !== $path));

        return ['ok' => true, 'path' => $path, 'entries' => $entries, 'truncated' => $truncated, 'error' => null];
    }

    /** Single quotes for POSIX sh on the target. Never escapeshellarg. */
    private static function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
