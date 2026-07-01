<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Kiefer Networks',
            'default_language' => 'de',
            'default_currency' => 'EUR',
            'default_tax_rate' => 19,
            'tax_display' => 'line',
            'paper_size' => 'A4',
            'gallery_trip_gap_days' => 2,
            'gallery_trip_radius_km' => 100,
            'invoice_number_prefix' => 'RE',
            'invoice_number_next' => 100,
            'payment_terms_days' => 14,
        ], $overrides);
    }

    public function test_guests_cannot_access_the_company_profile(): void
    {
        $this->get(route('settings.company.edit'))->assertRedirect(route('login'));
    }

    public function test_edit_form_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.company.edit'))
            ->assertOk()
            ->assertSee('Company profile');
    }

    public function test_update_persists_a_single_profile(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('settings.company.update'), $this->payload(['legal_name' => 'First Name']))
            ->assertRedirect(route('settings.company.edit'));
        $this->put(route('settings.company.update'), $this->payload(['legal_name' => 'Second Name']));

        $this->assertSame(1, CompanyProfile::count());
        $this->assertSame('Second Name', CompanyProfile::current()->legal_name);
        $this->assertSame(100, CompanyProfile::current()->invoice_number_next);
    }

    public function test_update_requires_a_legal_name(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('settings.company.update'), $this->payload(['legal_name' => '']))
            ->assertSessionHasErrors('legal_name');
    }

    public function test_update_validates_language_and_currency(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('settings.company.update'), $this->payload(['default_language' => 'fr', 'default_currency' => 'XYZ']))
            ->assertSessionHasErrors(['default_language', 'default_currency']);
    }

    public function test_logo_can_be_uploaded_and_served(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $this->put(route('settings.company.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]))->assertRedirect();

        $company = CompanyProfile::current();
        $this->assertNotNull($company->logo_path);
        Storage::disk('local')->assertExists($company->logo_path);

        $this->get(route('settings.company.logo'))->assertOk();
    }

    public function test_number_series_is_locked_once_a_numbered_invoice_exists(): void
    {
        $this->signIn();
        CompanyProfile::current()->update(['invoice_number_prefix' => 'RE', 'invoice_number_next' => 100]);
        Invoice::factory()->create(['number' => 'RE-2026-0100', 'finalized_at' => now()]);

        $this->put(route('settings.company.update'), $this->payload([
            'invoice_number_prefix' => 'XX',
            'invoice_number_next' => 999,
        ]))->assertRedirect();

        $company = CompanyProfile::current();
        $this->assertSame('RE', $company->invoice_number_prefix);
        $this->assertSame(100, $company->invoice_number_next);
    }

    public function test_logo_route_404s_without_a_logo(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.company.logo'))
            ->assertNotFound();
    }
}
