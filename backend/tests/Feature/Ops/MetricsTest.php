<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_disabled_without_a_token(): void
    {
        config(['ops.metrics_token' => '']);

        $this->get('/metrics')->assertNotFound();
    }

    public function test_it_rejects_a_wrong_token(): void
    {
        config(['ops.metrics_token' => 'secret']);

        $this->get('/metrics?token=nope')->assertForbidden();
    }

    public function test_it_exposes_metrics_with_the_token(): void
    {
        config(['ops.metrics_token' => 'secret']);

        $res = $this->get('/metrics?token=secret');

        $res->assertOk();
        $res->assertSee('ledgerline_up 1', false);
        $res->assertSee('ledgerline_storage_bytes', false);
    }

    public function test_it_accepts_a_bearer_token(): void
    {
        config(['ops.metrics_token' => 'secret']);

        $this->withHeaders(['Authorization' => 'Bearer secret'])
            ->get('/metrics')
            ->assertOk();
    }
}
