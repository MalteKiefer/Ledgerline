<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BinaryProcess;
use PHPUnit\Framework\TestCase;

class BinaryProcessTest extends TestCase
{
    public function test_run_returns_stdout_on_success(): void
    {
        $out = BinaryProcess::run(['echo', 'hello']);
        $this->assertSame("hello\n", $out);
    }

    public function test_run_returns_null_on_nonzero_exit(): void
    {
        $out = BinaryProcess::run(['false']);
        $this->assertNull($out);
    }

    public function test_run_pipes_input_to_child_stdin(): void
    {
        // cat echoes stdin verbatim → proves the $input arg reaches the child.
        $out = BinaryProcess::run(['cat'], 5, 'hello');
        $this->assertSame('hello', $out);
    }

    public function test_run_passes_binary_input_through_unmangled(): void
    {
        // Bytes with NUL + high bytes + a newline must survive round-trip
        // untouched — the mail sealer relies on this for its binary blob.
        $binary = "line1\nwith\x00null\xffbyte";
        $out = BinaryProcess::run(['cat'], 5, $binary);
        $this->assertSame($binary, $out);
    }

    public function test_run_defaults_to_no_stdin_when_input_omitted(): void
    {
        // With no $input, cat sees EOF immediately and emits nothing.
        $out = BinaryProcess::run(['cat'], 5);
        $this->assertSame('', $out);
    }

    public function test_run_returns_null_on_missing_binary(): void
    {
        $out = BinaryProcess::run(['/nonexistent/binary/that/does/not/exist']);
        $this->assertNull($out);
    }

    public function test_available_returns_true_for_known_binary(): void
    {
        $this->assertTrue(BinaryProcess::available('echo'));
    }

    public function test_available_returns_false_for_missing_binary(): void
    {
        $this->assertFalse(BinaryProcess::available('this-binary-does-not-exist-xyz-12345'));
    }

    public function test_run_capture_returns_stdout_stderr_and_exit_on_failure(): void
    {
        $r = BinaryProcess::runCapture(['sh', '-c', 'echo out; echo boom >&2; exit 3']);
        $this->assertFalse($r['ok']);
        $this->assertSame(3, $r['exit']);
        $this->assertStringContainsString('out', $r['out']);
        $this->assertStringContainsString('boom', $r['err']);
    }

    public function test_run_capture_ok_true_on_success(): void
    {
        $r = BinaryProcess::runCapture(['sh', '-c', 'exit 0']);
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['exit']);
    }

    public function test_run_capture_handles_missing_binary(): void
    {
        // A nonexistent executable is not a Symfony setup exception — the
        // process just exits non-zero (127). ok is false either way.
        $r = BinaryProcess::runCapture(['/nonexistent/binary/xyz']);
        $this->assertFalse($r['ok']);
        $this->assertNotSame(0, $r['exit']);
    }
}
