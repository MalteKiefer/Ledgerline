<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;
use App\Support\BinaryProcess;
use App\Support\DiskTempFile;

/**
 * Browse and change files on a monitored host over SFTP.
 *
 * SFTP rather than the shell path the rest of the module uses, and the reason
 * is not style: `ls` output cannot be parsed unambiguously (a newline in a file
 * name is legal), `cat` through a text pipe corrupts binaries, a large file
 * read into a string kills the worker, and writing a file back through a shell
 * means quoting arbitrary content — the exact class of bug that has already
 * bitten this project twice. SFTP answers all four: it is a real protocol with
 * typed listings and byte-exact transfers, and it runs inside the same SSH
 * connection, so it needs no second service, no agent and no extra port.
 *
 * The transport is the OpenSSH client, for the same reason the shell path uses
 * it (v1.696.0): a properly hardened sshd may offer only post-quantum key
 * exchange, which no pure-PHP library speaks, so the negotiation fails before a
 * host key is ever seen.
 *
 * Batch mode is one command per call. sftp's batch file aborts on the first
 * error and exits non-zero, which is exactly the behaviour wanted here: a
 * failed `rename` must never be followed by the `rm` that came after it.
 */
class SftpBrowser
{
    /** How long an interactive file operation may take. */
    private const TIMEOUT = 120;

    /** Transfers get longer, but still bounded — nobody waits forever on a UI. */
    private const TRANSFER_TIMEOUT = 900;

    /** Reading a file into the editor. Beyond this it is not a text file worth editing. */
    public const MAX_EDIT_BYTES = 2 * 1024 * 1024;

    /** What may be written back or uploaded in one request. */
    public const MAX_UPLOAD_BYTES = 512 * 1024 * 1024;

    public function __construct(private ServerProbe $probe) {}

    /**
     * List a directory.
     *
     * @return array{ok:bool,path:string,entries:list<array<string,mixed>>,error:string|null}
     */
    public function list(Server $server, string $path): array
    {
        $path = self::normalisePath($path);
        if ($path === null) {
            return ['ok' => false, 'path' => '', 'entries' => [], 'error' => 'invalid_path'];
        }

        $result = $this->batch($server, ['ls -la '.self::q($path)], self::TIMEOUT);
        if ($result === null) {
            return ['ok' => false, 'path' => $path, 'entries' => [], 'error' => 'unreachable'];
        }
        if (! $result['ok']) {
            return ['ok' => false, 'path' => $path, 'entries' => [], 'error' => self::readError($result['out'].$result['err'])];
        }

        return ['ok' => true, 'path' => $path, 'entries' => $this->parseListing($result['out'], $path), 'error' => null];
    }

    /**
     * Fetch a file to a local temporary file.
     *
     * Returns the temp file rather than its contents: the caller streams it to
     * the client, so a large download never sits in memory. The file is RAII —
     * it disappears when the returned handle goes out of scope.
     *
     * @return array{ok:bool,file:DiskTempFile|null,error:string|null}
     */
    public function download(Server $server, string $path): array
    {
        $path = self::normalisePath($path);
        if ($path === null) {
            return ['ok' => false, 'file' => null, 'error' => 'invalid_path'];
        }

        $local = DiskTempFile::create('ll-sftp-get');
        $result = $this->batch(
            $server,
            ['get '.self::q($path).' '.self::q($local->path())],
            self::TRANSFER_TIMEOUT,
        );

        if ($result === null) {
            return ['ok' => false, 'file' => null, 'error' => 'unreachable'];
        }
        if (! $result['ok']) {
            return ['ok' => false, 'file' => null, 'error' => self::readError($result['out'].$result['err'])];
        }

        return ['ok' => true, 'file' => $local, 'error' => null];
    }

    /**
     * Fetch a directory as a compressed tar, built on the host.
     *
     * There is no sensible way to hand a browser a tree, and one archive is one
     * transfer rather than one per file. Built through the shell path rather
     * than SFTP because SFTP has no notion of "archive this" — and the tar is
     * written to a temporary name on the target, fetched, then removed, so a
     * failure does not leave a copy of somebody's directory lying around.
     *
     * @return array{ok:bool,file:DiskTempFile|null,error:string|null}
     */
    public function downloadDirectory(Server $server, string $path): array
    {
        $path = self::normalisePath($path);
        if ($path === null || $path === '/') {
            // Refusing / is not squeamishness: tarring a root filesystem over a
            // web request is a way to take the machine down, not a feature.
            return ['ok' => false, 'file' => null, 'error' => 'invalid_path'];
        }

        $remote = '/tmp/ll-dl-'.bin2hex(random_bytes(6)).'.tar.gz';
        $name = basename($path);
        $parent = dirname($path);

        $script = 'tar -czf '.self::shq($remote).' -C '.self::shq($parent).' -- '.self::shq($name).' 2>&1; echo "##LL:rc=$?"';
        $made = $this->probe->exec(ServerTarget::fromServer($server), (string) $server->host_key, $script, interactive: true);
        if (! $made['ok'] && $made['out'] === '') {
            return ['ok' => false, 'file' => null, 'error' => 'unreachable'];
        }
        if (preg_match('/##LL:rc=(\d+)/', $made['out'], $m) === 1 && $m[1] !== '0') {
            return ['ok' => false, 'file' => null, 'error' => self::readError($made['out'])];
        }

        $got = $this->download($server, $remote);

        // Clean up whatever happened: leaving a tar of somebody's directory in
        // /tmp is litter at best and a disclosure at worst.
        $this->probe->exec(
            ServerTarget::fromServer($server),
            (string) $server->host_key,
            'rm -f '.self::shq($remote),
            interactive: true,
        );

        return $got;
    }

    /**
     * Read a file as text, for preview or the editor.
     *
     * Refuses anything that is not text rather than handing the browser a
     * mangled string: a binary opened in an editor and saved back is a
     * destroyed file, and no amount of warning copy prevents that reliably.
     *
     * @return array{ok:bool,content:string,binary:bool,size:int,error:string|null}
     */
    public function read(Server $server, string $path, int $maxBytes = self::MAX_EDIT_BYTES): array
    {
        $got = $this->download($server, $path);
        if (! $got['ok'] || $got['file'] === null) {
            return ['ok' => false, 'content' => '', 'binary' => false, 'size' => 0, 'error' => $got['error']];
        }

        $file = $got['file'];
        $size = (int) filesize($file->path());
        if ($size > $maxBytes) {
            return ['ok' => false, 'content' => '', 'binary' => false, 'size' => $size, 'error' => 'too_large'];
        }

        $content = (string) file_get_contents($file->path());
        if (self::looksBinary($content)) {
            return ['ok' => false, 'content' => '', 'binary' => true, 'size' => $size, 'error' => 'binary'];
        }

        return ['ok' => true, 'content' => $content, 'binary' => false, 'size' => $size, 'error' => null];
    }

    /**
     * Write a file.
     *
     * Uploaded beside the target and renamed into place, because an upload cut
     * short halfway is otherwise a half-written configuration file. The rename
     * is atomic on the same filesystem, so the file is either the old one or
     * the new one and never a fragment.
     *
     * @return array{ok:bool,error:string|null}
     */
    public function write(Server $server, string $path, string $content): array
    {
        $path = self::normalisePath($path);
        if ($path === null) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }
        if (strlen($content) > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'too_large'];
        }

        $local = DiskTempFile::create('ll-sftp-put');
        file_put_contents($local->path(), $content);

        return $this->upload($server, $local->path(), $path);
    }

    /**
     * Put a local file at a remote path, via a neighbouring temporary name.
     *
     * @return array{ok:bool,error:string|null}
     */
    public function upload(Server $server, string $localPath, string $remotePath): array
    {
        $remotePath = self::normalisePath($remotePath);
        if ($remotePath === null) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }

        // Same directory, so the rename stays on one filesystem and is atomic.
        $staging = $remotePath.'.ll-upload-'.bin2hex(random_bytes(4));

        $result = $this->batch($server, [
            'put '.self::q($localPath).' '.self::q($staging),
            'rename '.self::q($staging).' '.self::q($remotePath),
        ], self::TRANSFER_TIMEOUT);

        if ($result === null) {
            return ['ok' => false, 'error' => 'unreachable'];
        }
        if (! $result['ok']) {
            // The batch aborts on the first failure, so a failed put never
            // reaches the rename. Clean up the fragment if the put succeeded
            // and only the rename went wrong.
            $this->batch($server, ['rm '.self::q($staging)], self::TIMEOUT);

            return ['ok' => false, 'error' => self::readError($result['out'].$result['err'])];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Create a directory, remove a file or directory, rename, or change mode.
     *
     * @return array{ok:bool,error:string|null}
     */
    public function mutate(Server $server, string $action, string $path, string $target = '', string $mode = ''): array
    {
        $path = self::normalisePath($path);
        if ($path === null) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }

        $command = match ($action) {
            'mkdir' => 'mkdir '.self::q($path),
            'rmdir' => 'rmdir '.self::q($path),
            'rm' => 'rm '.self::q($path),
            'rename' => null,
            'chmod' => null,
            default => false,
        };

        if ($command === false) {
            return ['ok' => false, 'error' => 'invalid_selection'];
        }

        if ($action === 'rename') {
            $to = self::normalisePath($target);
            if ($to === null) {
                return ['ok' => false, 'error' => 'invalid_path'];
            }
            $command = 'rename '.self::q($path).' '.self::q($to);
        }

        if ($action === 'chmod') {
            // Three or four octal digits and nothing else; the value reaches a
            // command line, so it is validated rather than quoted around.
            if (preg_match('/^[0-7]{3,4}$/', $mode) !== 1) {
                return ['ok' => false, 'error' => 'invalid_selection'];
            }
            $command = 'chmod '.$mode.' '.self::q($path);
        }

        $result = $this->batch($server, [(string) $command], self::TIMEOUT);
        if ($result === null) {
            return ['ok' => false, 'error' => 'unreachable'];
        }

        return $result['ok']
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => self::readError($result['out'].$result['err'])];
    }

    /**
     * Turn sftp's `ls -la` output into rows.
     *
     * sftp prints the full path rather than the bare name, which is what makes
     * this parseable at all: the name is everything after the directory prefix,
     * so a space or a quote in it survives intact.
     *
     * @return list<array<string,mixed>>
     */
    private function parseListing(string $out, string $dir): array
    {
        $prefix = rtrim($dir, '/').'/';
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $out) ?: [] as $line) {
            // Echoed commands and sftp's own prompt are not entries.
            if ($line === '' || str_starts_with($line, 'sftp>')) {
                continue;
            }
            // "-rw-r--r--    ? root  root  982432 Aug 22 06:50 /path/name"
            if (preg_match('/^([dlbcps\-][rwxSsTt\-]{9})\s+\S+\s+(\S+)\s+(\S+)\s+(\d+)\s+(\S+\s+\d+\s+\S+)\s+(.+)$/', $line, $m) !== 1) {
                continue;
            }

            $full = $m[6];
            $name = str_starts_with($full, $prefix) ? substr($full, strlen($prefix)) : basename($full);
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $perms = $m[1];
            $rows[] = [
                'name' => $name,
                'path' => $prefix.$name,
                'type' => match ($perms[0]) {
                    'd' => 'dir',
                    'l' => 'link',
                    '-' => 'file',
                    default => 'special',
                },
                'perms' => $perms,
                'owner' => $m[2],
                'group' => $m[3],
                'size' => (int) $m[4],
                // Left as the host printed it. sftp gives "Aug 22 06:50" for a
                // recent file and "Jan  5  2023" for an old one — no year in
                // the first case, so an exact timestamp would be invented.
                'modified' => trim($m[5]),
            ];
        }

        return $rows;
    }

    /**
     * Is this content binary?
     *
     * A NUL byte settles it; beyond that, a high share of bytes outside the
     * printable and whitespace range does. Deliberately conservative: showing a
     * text file as binary is an inconvenience, opening a binary in an editor
     * and saving it back destroys the file.
     */
    private static function looksBinary(string $content): bool
    {
        if ($content === '') {
            return false;
        }
        if (str_contains($content, "\0")) {
            return true;
        }

        $sample = substr($content, 0, 8192);
        $odd = strlen((string) preg_replace('/[\x09\x0A\x0D\x20-\x7E\x80-\xFF]/', '', $sample));

        return $odd > strlen($sample) * 0.1;
    }

    /**
     * A path we are willing to send.
     *
     * Absolute, no NUL, and no control characters: sftp's batch input is
     * line-based, so a newline in a path could never be expressed and must be
     * refused rather than silently truncated.
     */
    public static function normalisePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 4096) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return null;
        }

        // Collapse duplicate slashes and resolve . and .. here, so the caller
        // cannot smuggle a traversal past a check that ran on the raw string.
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }

    /**
     * Single-quote for POSIX sh.
     *
     * A different dialect from q(): that one quotes for sftp's batch parser,
     * this one for the shell that runs tar on the target. Mixing them would
     * be the escapeshellarg mistake again, one layer along.
     */
    private static function shq(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    /**
     * Quote for sftp's batch parser.
     *
     * Verified against the real client: inside double quotes, `\"` and `\\`
     * escape correctly, which makes every legal path expressible except one
     * containing a newline — and that is refused in normalise().
     */
    private static function q(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /** Map sftp's message to a code the interface can translate. */
    private static function readError(string $text): string
    {
        $lower = strtolower($text);

        return match (true) {
            str_contains($lower, 'permission denied') => 'permission_denied',
            str_contains($lower, 'no such file') || str_contains($lower, 'not found') => 'not_found',
            str_contains($lower, 'directory not empty') => 'not_empty',
            str_contains($lower, 'failure') => 'failed',
            default => 'failed',
        };
    }

    /**
     * Run one batch of sftp commands.
     *
     * Overridable so a test can record what would have been sent without an
     * SSH connection: the commands are the contract worth asserting on.
     *
     * @param  list<string>  $commands
     * @return array{ok:bool,out:string,err:string,exit:int|null}|null
     */
    protected function batch(Server $server, array $commands, int $timeout): ?array
    {
        $hostKey = (string) $server->host_key;
        if ($hostKey === '') {
            return null;
        }

        $target = ServerTarget::fromServer($server);
        $pem = $this->probe->privateKeyFor($target);
        if ($pem === null) {
            return null;
        }

        // RAII: the key never outlives this call on disk.
        $keyFile = DiskTempFile::create('ll-serverkey');
        $knownHosts = DiskTempFile::create('ll-knownhosts');
        file_put_contents($keyFile->path(), $pem);
        chmod($keyFile->path(), 0600);
        file_put_contents($knownHosts->path(), $this->probe->knownHostsFor($target, $hostKey));

        $result = BinaryProcess::runCapture(
            self::argv($target, $keyFile->path(), $knownHosts->path()),
            $timeout,
            input: implode("\n", $commands)."\n",
        );

        // A connection that never came up is different from a command the host
        // refused, and the caller says different things about them.
        if (! $result['ok'] && $result['out'] === '' && str_contains(strtolower($result['err']), 'connect')) {
            return null;
        }

        return $result;
    }

    /** @return array<int, string> */
    private static function argv(ServerTarget $target, string $keyPath, string $knownHostsPath): array
    {
        return [
            'sftp',
            // Same hardening as the shell path: pinned host key, no agent, no
            // forwarding, no prompting. It either works from what we passed or
            // fails; it never hangs on a question.
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile='.$knownHostsPath,
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'IdentityAgent=none',
            '-o', 'PasswordAuthentication=no',
            '-o', 'KbdInteractiveAuthentication=no',
            '-o', 'ClearAllForwardings=yes',
            '-o', 'ConnectTimeout=10',
            '-o', 'LogLevel=ERROR',
            '-i', $keyPath,
            '-P', (string) $target->port,
            // Commands arrive on stdin, one per line.
            '-b', '-',
            $target->username.'@'.$target->host,
        ];
    }
}
