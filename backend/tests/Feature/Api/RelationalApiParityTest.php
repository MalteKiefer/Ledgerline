<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the /api/v1 twin of the Finance module is reachable with a real Sanctum
 * bearer token (abilities:device) the way the native app calls it: it returns
 * JSON (not a redirect), creates owner-stamped rows, lists them, honours
 * optimistic version -> 409, and is owner-scoped (user B cannot see or mutate
 * user A's rows). The api and web routes share the same controller, so this
 * locks the contract the mobile clients depend on.
 */
class RelationalApiParityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device', ['device'])->plainTextToken];
    }

    /** Switch the acting bearer within a single test (Sanctum guard memoises per process). */
    private function reauth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_relational_api_requires_a_bearer(): void
    {
        $this->getJson('/api/v1/finance/data')->assertUnauthorized();
    }

    public function test_finance_api_create_list_version_conflict_and_owner_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->postJson(route('api.finance.partners.store'), ['name' => 'ACME GmbH'], $this->bearer($a))
            ->assertCreated()->assertJsonPath('partner.name', 'ACME GmbH')->json('partner.id');
        $this->getJson(route('api.finance.data'), $this->bearer($a))->assertOk()->assertJsonCount(1, 'partners');

        $this->putJson(route('api.finance.partners.update', $id), ['name' => 'ACME AG', 'version' => 0], $this->bearer($a))
            ->assertOk()->assertJsonPath('partner.version', 1);
        $this->putJson(route('api.finance.partners.update', $id), ['name' => 'nope', 'version' => 0], $this->bearer($a))
            ->assertStatus(409);

        $this->reauth();
        $this->getJson(route('api.finance.data'), $this->bearer($b))->assertOk()->assertJsonCount(0, 'partners');
        $this->reauth();
        $this->deleteJson(route('api.finance.partners.destroy', $id), [], $this->bearer($b))->assertNotFound();
    }
}
