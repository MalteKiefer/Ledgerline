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
}
