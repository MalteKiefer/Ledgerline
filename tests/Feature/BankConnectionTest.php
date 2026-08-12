<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\GoCardlessClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BankConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        $s = AppSettings::current();
        $s->forceFill(['gocardless_secret_id' => 'sid', 'gocardless_secret_key' => 'skey'])->save();
    }

    public function test_index_reports_configured_state(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/finance/bank-connections')->assertOk()->assertJson(['configured' => false]);
        $this->configure();
        $this->getJson('/api/v1/finance/bank-connections')->assertOk()->assertJson(['configured' => true]);
    }

    public function test_connect_creates_a_requisition_and_returns_the_link(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configure();

        $mock = Mockery::mock(GoCardlessClient::class);
        $mock->shouldReceive('createRequisition')->once()->andReturn(['id' => 'req-1', 'link' => 'https://bank.example/consent']);
        $this->app->instance(GoCardlessClient::class, $mock);

        $res = $this->postJson('/api/v1/finance/bank-connections', [
            'institution_id' => 'SOLARIS_DE', 'institution_name' => 'Solaris', 'redirect' => 'https://home.pinlo.me/finance',
        ]);
        $res->assertCreated()->assertJson(['link' => 'https://bank.example/consent']);
        $this->assertDatabaseHas('bank_connections', ['requisition_id' => 'req-1', 'status' => 'created']);
    }

    public function test_sync_imports_and_dedupes_transactions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->configure();
        $pm = PaymentMethod::query()->create(['type' => 'bank', 'name' => 'Solaris']);
        $conn = new BankConnection;
        $conn->forceFill([
            'user_id' => $user->id, 'payment_method_id' => $pm->id, 'provider' => 'gocardless',
            'institution_id' => 'SOLARIS_DE', 'reference' => 'll-abc', 'account_id' => 'acc-1', 'status' => 'linked',
        ])->save();

        $mock = Mockery::mock(GoCardlessClient::class);
        $mock->shouldReceive('transactions')->andReturn([
            ['date' => '2026-08-01', 'amount' => '-12.50', 'counterparty' => 'Shop', 'counterparty_iban' => null, 'purpose' => 'Kauf', 'sig' => 'gc:aaa'],
            ['date' => '2026-08-02', 'amount' => '99.00', 'counterparty' => 'Client', 'counterparty_iban' => null, 'purpose' => 'Invoice', 'sig' => 'gc:bbb'],
        ]);
        $this->app->instance(GoCardlessClient::class, $mock);

        $this->postJson("/api/v1/finance/bank-connections/{$conn->id}/sync")->assertOk()->assertJson(['imported' => 2]);
        $this->assertSame(2, BankTransaction::count());
        // Re-sync of the same signatures imports nothing.
        $this->postJson("/api/v1/finance/bank-connections/{$conn->id}/sync")->assertOk()->assertJson(['imported' => 0]);
        $this->assertSame(2, BankTransaction::count());
    }

    public function test_connections_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $conn = new BankConnection;
        $conn->forceFill([
            'user_id' => $owner->id, 'provider' => 'gocardless', 'institution_id' => 'X', 'reference' => 'll-x', 'status' => 'created',
        ])->save();
        $this->actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/finance/bank-connections/{$conn->id}")->assertNotFound();
    }

    public function test_credentials_are_never_returned(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->configure();
        $res = $this->getJson('/api/v1/admin/finance/gocardless');
        $res->assertOk()->assertJson(['configured' => true]);
        $this->assertArrayNotHasKey('secret_id', $res->json());
        $this->assertArrayNotHasKey('gocardless_secret_key', $res->json());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
