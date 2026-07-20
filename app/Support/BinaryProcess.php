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
     * Check whether a binary is available on the system PATH.
     */
    public static function available(string $binary): bool
    {
        $finder = new ExecutableFinder;

        return $finder->find($binary) !== null;
    }

    private function __construct() {}
}
