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
     * @param  string|null  $input  Optional raw bytes fed to the child's stdin
     *                              (e.g. a document to OCR or a message to seal).
     *                              Symfony Process buffers this and stdout as raw
     *                              PHP byte strings — no UTF-8/newline mangling —
     *                              so this is safe for binary payloads either way.
     */
    public static function run(array $argv, int $timeout = 60, ?string $input = null): ?string
    {
        try {
            $process = new Process($argv);
            $process->setTimeout($timeout);
            if ($input !== null) {
                $process->setInput($input);
            }
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
     * Run a command and return its exit code plus captured stdout AND stderr,
     * even on failure. Use when the caller needs the failure DETAIL (e.g.
     * surfacing mbsync's real connection error to the account owner) rather
     * than just stdout-or-null. The caller is responsible for redacting the
     * captured streams before persisting/displaying them.
     *
     * @param  array<int, string>  $argv  command + args as a flat array (no shell string).
     * @return array{ok: bool, exit: int|null, out: string, err: string}
     */
    public static function runCapture(array $argv, int $timeout = 60, ?string $input = null): array
    {
        try {
            $process = new Process($argv);
            $process->setTimeout($timeout);
            if ($input !== null) {
                $process->setInput($input);
            }
            $process->run();

            return [
                'ok' => $process->isSuccessful(),
                'exit' => $process->getExitCode(),
                'out' => $process->getOutput(),
                'err' => $process->getErrorOutput(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'exit' => null, 'out' => '', 'err' => $e->getMessage()];
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
