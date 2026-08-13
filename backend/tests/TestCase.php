<?php

declare(strict_types=1);

namespace Tests;

use App\Support\RequestMemo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithTeams;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithTeams;

    protected function setUp(): void
    {
        parent::setUp();

        // RequestMemo is process-static (Octane keeps it across requests, so a
        // listener flushes it per request). PHPUnit runs many tests in one process,
        // so reset it here too — otherwise one test's memoised AppSettings/UserSetting
        // row would bleed into the next.
        RequestMemo::flush();
    }
}
