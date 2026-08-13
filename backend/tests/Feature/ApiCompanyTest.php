<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET/PUT /api/v1/company expose the per-user company profile + invoice defaults
 * (non-secret business identity) so mobile apps can render invoices. Owner-scoped.
 */
class ApiCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    public function test_requires_a_device_token(): void
    {
        $this->getJson('/api/v1/company')->assertUnauthorized();
    }

    public function test_returns_the_company_profile(): void
    {
        $user = User::factory()->create();
        UserSetting::for($user->id)->update(['company_name' => 'Acme GmbH', 'invoice_number_format' => 'YYYY-NNNN']);

        $this->getJson('/api/v1/company', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJsonPath('company.company_name', 'Acme GmbH')
            ->assertJsonPath('company.invoice_number_format', 'YYYY-NNNN')
            ->assertJsonPath('company.has_logo', false);
    }

    public function test_updates_only_submitted_fields(): void
    {
        $user = User::factory()->create();
        UserSetting::for($user->id)->update(['company_name' => 'Old', 'company_email' => 'keep@example.com']);

        $this->putJson('/api/v1/company', ['company_name' => 'New GmbH'], ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJsonPath('company.company_name', 'New GmbH')
            ->assertJsonPath('company.company_email', 'keep@example.com');

        $this->assertSame('New GmbH', UserSetting::for($user->id)->company_name);
    }

    public function test_validates_fields(): void
    {
        $user = User::factory()->create();

        $this->putJson('/api/v1/company', ['company_email' => 'not-an-email', 'invoice_template' => 'bogus'], ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_email', 'invoice_template']);
    }

    public function test_logo_is_404_when_none_stored(): void
    {
        $user = User::factory()->create();

        $this->get('/api/v1/company/logo', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertNotFound();
    }

    public function test_is_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        UserSetting::for($a->id)->update(['company_name' => 'A Co']);
        UserSetting::for($b->id)->update(['company_name' => 'B Co']);

        $this->getJson('/api/v1/company', ['Authorization' => 'Bearer '.$this->token($b)])
            ->assertJsonPath('company.company_name', 'B Co');
    }
}
