<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualTriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_command_rejects_unlisted(): void
    {
        $this->expectException(HttpException::class);
        new RunCommand('rm:everything');
    }
}
