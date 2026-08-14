<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Thin wrapper around Symfony Process for running trusted local binaries.
 *
 * Uses array-based command construction exclusively (no shell strings) to
 * prevent command injection. Returns stdout as a string on success, null on
 * any failure (non-zero exit or exception).
 */
final class BinaryProcess
{
    /**
     * Run a command and return its stdout, or null on failure.
     *
     * @param  array<int, string>  $argv  The command + arguments as a flat array
     *                                    (never a shell string — no injection risk).
     * @param  int  $timeout  Process timeout in seconds (default 60).
     */
    public static function run(array $argv, int $timeout = 60): ?string
    {
        try {
            $process = new Process($argv);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            return $process->getOutput();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Run a command and capture its full result: success flag, stdout, stderr
     * and exit code. Unlike run() (which collapses every failure to null), this
     * preserves the tool's own output so a caller can surface a diagnostic tail
     * (e.g. mbsync's "Login failed" / "Cannot connect"). Array-argv only — no
     * shell string, no injection. On an exception (binary missing, spawn error)
     * returns ok=false with the throwable message in `err` and exit=null.
     *
     * @param  array<int, string>  $argv
     * @param  string|null  $cwd  Working directory to run in (e.g. a staging dir for
     *                            7z, which has no -C flag). Null = inherit.
     * @return array{ok: bool, out: string, err: string, exit: ?int}
     */
    public static function runCapture(array $argv, int $timeout = 60, ?string $cwd = null): array
    {
        try {
            $process = new Process($argv, $cwd);
            $process->setTimeout($timeout);
            $process->run();

            return [
                'ok' => $process->isSuccessful(),
                'out' => $process->getOutput(),
                'err' => $process->getErrorOutput(),
                'exit' => $process->getExitCode(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'out' => '', 'err' => $e->getMessage(), 'exit' => null];
        }
    }

    /**
     * Check whether a binary is available on the system PATH.
     */
    public static function available(string $binary): bool
    {
        $finder = new ExecutableFinder;

        return $finder->find($binary) !== null;
    }

    private function __construct() {}
}
