<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;
use App\Support\DiskTempFile;

/**
 * Pack and unpack archives on a monitored host.
 *
 * Packing happens on the far side rather than here: pulling a directory file by
 * file to build a tar locally would be one transfer per file and would carry
 * nothing of the permissions and ownership a tar keeps. Unpacking is the same
 * argument in reverse.
 *
 * Which formats are offered is decided by what the host actually has. A machine
 * without unzip cannot open a zip, and a button that fails on click is worse
 * than a button that is not there — the same rule the ACL panel follows.
 */
class RemoteArchiver
{
    private const PACK_TIMEOUT = 1800;

    private const EXTRACT_TIMEOUT = 1800;

    /** What may be produced, and the tar flag that produces it. */
    public const FORMATS = ['tar.gz', 'tar.xz', 'tar.zst', 'tar'];

    public function __construct(private ServerProbe $probe, private SftpBrowser $sftp) {}

    /**
     * Which archive tools the host has.
     *
     * @return array{ok:bool,pack:list<string>,extract:list<string>,error:string|null}
     */
    public function tools(Server $server): array
    {
        $script = <<<'SH'
        for c in tar gzip xz zstd bzip2 unzip 7z 7za unrar bsdtar; do
          if command -v "$c" >/dev/null 2>&1; then echo "$c"; fi
        done
        SH;

        $out = $this->run($server, $script);
        if ($out === null) {
            return ['ok' => false, 'pack' => [], 'extract' => [], 'error' => 'unreachable'];
        }

        $have = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($out)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $have[$line] = true;
            }
        }

        $pack = [];
        if (isset($have['tar'])) {
            $pack[] = 'tar';
            if (isset($have['gzip'])) {
                $pack[] = 'tar.gz';
            }
            if (isset($have['xz'])) {
                $pack[] = 'tar.xz';
            }
            if (isset($have['zstd'])) {
                $pack[] = 'tar.zst';
            }
        }

        // Extraction is per format, not per tool: bsdtar and 7z each cover
        // several, and claiming a format the host cannot open would produce a
        // button that fails on click.
        $extract = [];
        if (isset($have['tar'])) {
            $extract[] = 'tar';
            if (isset($have['gzip'])) {
                $extract[] = 'tar.gz';
                $extract[] = 'gz';
            }
            if (isset($have['xz'])) {
                $extract[] = 'tar.xz';
                $extract[] = 'xz';
            }
            if (isset($have['zstd'])) {
                $extract[] = 'tar.zst';
                $extract[] = 'zst';
            }
            if (isset($have['bzip2'])) {
                $extract[] = 'tar.bz2';
                $extract[] = 'bz2';
            }
        }
        if (isset($have['unzip']) || isset($have['bsdtar']) || isset($have['7z']) || isset($have['7za'])) {
            $extract[] = 'zip';
        }
        if (isset($have['7z']) || isset($have['7za'])) {
            $extract[] = '7z';
        }
        if (isset($have['unrar']) || isset($have['7z'])) {
            $extract[] = 'rar';
        }

        return ['ok' => true, 'pack' => array_values(array_unique($pack)), 'extract' => array_values(array_unique($extract)), 'error' => null];
    }

    /**
     * Pack several paths into one archive and fetch it.
     *
     * Everything is packed relative to a common parent so the archive unpacks
     * into recognisable names rather than a chain of empty directories.
     *
     * @param  list<string>  $paths
     * @return array{ok:bool,file:DiskTempFile|null,name:string,error:string|null}
     */
    public function pack(Server $server, array $paths, string $format = 'tar.gz'): array
    {
        if (! in_array($format, self::FORMATS, true)) {
            return ['ok' => false, 'file' => null, 'name' => '', 'error' => 'invalid_selection'];
        }

        $clean = [];
        foreach ($paths as $path) {
            $normalised = SftpBrowser::normalisePath($path);
            // The filesystem root is refused: tarring it over a web request is
            // a way to take the machine down, not a feature.
            if ($normalised === null || $normalised === '/') {
                return ['ok' => false, 'file' => null, 'name' => '', 'error' => 'invalid_path'];
            }
            $clean[] = $normalised;
        }
        if ($clean === [] || count($clean) > 500) {
            return ['ok' => false, 'file' => null, 'name' => '', 'error' => 'invalid_selection'];
        }

        $parent = $this->commonParent($clean);
        $names = [];
        foreach ($clean as $path) {
            $names[] = self::shq(ltrim(substr($path, strlen(rtrim($parent, '/'))), '/'));
        }

        $remote = '/tmp/ll-pack-'.bin2hex(random_bytes(6)).'.'.$format;
        $flag = match ($format) {
            'tar.gz' => '-czf',
            'tar.xz' => '-cJf',
            'tar.zst' => '--zstd -cf',
            default => '-cf',
        };

        $script = 'tar '.$flag.' '.self::shq($remote).' -C '.self::shq($parent).' -- '.implode(' ', $names).' 2>&1; echo "##LL:rc=$?"';
        $made = $this->run($server, $script, self::PACK_TIMEOUT);
        if ($made === null) {
            return ['ok' => false, 'file' => null, 'name' => '', 'error' => 'unreachable'];
        }
        if (preg_match('/##LL:rc=(\d+)/', $made, $m) === 1 && $m[1] !== '0') {
            $this->cleanup($server, $remote);

            return ['ok' => false, 'file' => null, 'name' => '', 'error' => 'pack_failed'];
        }

        $got = $this->sftp->download($server, $remote);
        // Removed whatever happened: leaving an archive of somebody's files in
        // /tmp is litter at best and a disclosure at worst.
        $this->cleanup($server, $remote);

        if (! $got['ok'] || $got['file'] === null) {
            return ['ok' => false, 'file' => null, 'name' => '', 'error' => $got['error']];
        }

        $name = count($clean) === 1
            ? basename($clean[0]).'.'.$format
            : basename(rtrim($parent, '/') ?: 'archive').'-files.'.$format;

        return ['ok' => true, 'file' => $got['file'], 'name' => $name, 'error' => null];
    }

    /**
     * Unpack an archive on the host, into a directory beside it.
     *
     * Into its own directory rather than the current one: an archive that turns
     * out not to have a top-level folder would otherwise spray its contents
     * across whatever was already there, and there is no undo for that.
     *
     * @return array{ok:bool,output:string,dest:string,error:string|null}
     */
    public function extract(Server $server, string $path, string $destination = ''): array
    {
        $path = SftpBrowser::normalisePath($path);
        if ($path === null) {
            return ['ok' => false, 'output' => '', 'dest' => '', 'error' => 'invalid_path'];
        }

        $format = self::formatOf($path);
        if ($format === null) {
            return ['ok' => false, 'output' => '', 'dest' => '', 'error' => 'unknown_format'];
        }

        $dest = $destination === '' ? self::defaultDestination($path) : SftpBrowser::normalisePath($destination);
        if ($dest === null || $dest === '/') {
            return ['ok' => false, 'output' => '', 'dest' => '', 'error' => 'invalid_path'];
        }

        $q = self::shq($path);
        $d = self::shq($dest);
        $command = match ($format) {
            'tar' => 'tar -xf '.$q.' -C '.$d,
            'tar.gz' => 'tar -xzf '.$q.' -C '.$d,
            'tar.xz' => 'tar -xJf '.$q.' -C '.$d,
            'tar.bz2' => 'tar -xjf '.$q.' -C '.$d,
            'tar.zst' => 'tar --zstd -xf '.$q.' -C '.$d,
            'zip' => 'if command -v unzip >/dev/null 2>&1; then unzip -q '.$q.' -d '.$d
                .'; elif command -v bsdtar >/dev/null 2>&1; then bsdtar -xf '.$q.' -C '.$d
                .'; else 7z x -o'.$d.' '.$q.'; fi',
            '7z' => '7z x -o'.$d.' '.$q,
            'rar' => 'if command -v unrar >/dev/null 2>&1; then unrar x -y '.$q.' '.$d.'/; else 7z x -o'.$d.' '.$q.'; fi',
            // A bare compressed file has no member names; decompress next to it.
            'gz' => 'gzip -dc '.$q.' > '.self::shq($dest.'/'.self::strippedName($path)),
            'xz' => 'xz -dc '.$q.' > '.self::shq($dest.'/'.self::strippedName($path)),
            'bz2' => 'bzip2 -dc '.$q.' > '.self::shq($dest.'/'.self::strippedName($path)),
            'zst' => 'zstd -dc '.$q.' > '.self::shq($dest.'/'.self::strippedName($path)),
        };

        $script = 'mkdir -p '.$d.' 2>&1 && '.$command.' 2>&1; echo "##LL:rc=$?"';
        $out = $this->run($server, $script, self::EXTRACT_TIMEOUT);
        if ($out === null) {
            return ['ok' => false, 'output' => '', 'dest' => $dest, 'error' => 'unreachable'];
        }

        $rc = 0;
        if (preg_match('/##LL:rc=(\d+)\s*$/', $out, $m) === 1) {
            $rc = (int) $m[1];
            $out = (string) preg_replace('/##LL:rc=\d+\s*$/', '', $out);
        }

        return [
            'ok' => $rc === 0,
            'output' => trim(substr($out, 0, 8192)),
            'dest' => $dest,
            'error' => $rc === 0 ? null : 'extract_failed',
        ];
    }

    /**
     * The format a name implies, or null if we would only be guessing.
     *
     * The literal union is the point: it makes the extract() match exhaustive,
     * so adding a format here without teaching extract() about it is a type
     * error rather than a silent fall-through.
     *
     * @return 'tar'|'tar.gz'|'tar.xz'|'tar.bz2'|'tar.zst'|'zip'|'7z'|'rar'|'gz'|'xz'|'bz2'|'zst'|null
     */
    public static function formatOf(string $path): ?string
    {
        $lower = strtolower(basename($path));

        return match (true) {
            str_ends_with($lower, '.tar.gz'), str_ends_with($lower, '.tgz') => 'tar.gz',
            str_ends_with($lower, '.tar.xz'), str_ends_with($lower, '.txz') => 'tar.xz',
            str_ends_with($lower, '.tar.bz2'), str_ends_with($lower, '.tbz2') => 'tar.bz2',
            str_ends_with($lower, '.tar.zst') => 'tar.zst',
            str_ends_with($lower, '.tar') => 'tar',
            str_ends_with($lower, '.zip') => 'zip',
            str_ends_with($lower, '.7z') => '7z',
            str_ends_with($lower, '.rar') => 'rar',
            str_ends_with($lower, '.gz') => 'gz',
            str_ends_with($lower, '.xz') => 'xz',
            str_ends_with($lower, '.bz2') => 'bz2',
            str_ends_with($lower, '.zst') => 'zst',
            default => null,
        };
    }

    /** Where an archive unpacks by default: a directory named after it. */
    private static function defaultDestination(string $path): string
    {
        $base = basename($path);
        foreach (['.tar.gz', '.tar.xz', '.tar.bz2', '.tar.zst', '.tgz', '.txz', '.tbz2', '.tar', '.zip', '.7z', '.rar', '.gz', '.xz', '.bz2', '.zst'] as $suffix) {
            if (str_ends_with(strtolower($base), $suffix)) {
                $base = substr($base, 0, -strlen($suffix));

                break;
            }
        }

        return rtrim(dirname($path), '/').'/'.($base === '' ? 'extracted' : $base);
    }

    /** The name a bare compressed file carries once its suffix is gone. */
    private static function strippedName(string $path): string
    {
        $base = basename($path);
        foreach (['.gz', '.xz', '.bz2', '.zst'] as $suffix) {
            if (str_ends_with(strtolower($base), $suffix)) {
                return substr($base, 0, -strlen($suffix));
            }
        }

        return $base;
    }

    /**
     * The deepest directory that contains all of the given paths.
     *
     * @param  list<string>  $paths
     */
    private function commonParent(array $paths): string
    {
        $parts = null;
        foreach ($paths as $path) {
            $segments = explode('/', trim(dirname($path), '/'));
            if ($parts === null) {
                $parts = $segments;

                continue;
            }
            $shared = [];
            foreach ($parts as $i => $segment) {
                if (($segments[$i] ?? null) !== $segment) {
                    break;
                }
                $shared[] = $segment;
            }
            $parts = $shared;
        }

        $joined = implode('/', array_filter($parts ?? []));

        return '/'.$joined;
    }

    private function cleanup(Server $server, string $remote): void
    {
        $this->run($server, 'rm -f '.self::shq($remote));
    }

    /** Single-quote for POSIX sh — the script runs on the target, not here. */
    private static function shq(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    private function run(Server $server, string $script, int $timeout = 60): ?string
    {
        $key = (string) $server->host_key;
        if ($key === '') {
            return null;
        }

        $result = $this->probe->exec(ServerTarget::fromServer($server), $key, $script, interactive: $timeout <= 60);
        if (! $result['ok'] && $result['out'] === '') {
            return null;
        }

        return substr($result['out'], 0, 256 * 1024);
    }
}
