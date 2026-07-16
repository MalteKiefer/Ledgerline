<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_and_save_the_company_profile(): void
    {
        $this->signIn(); // single-user install = admin

        $this->get(route('settings.company.edit'))->assertOk();

        $this->put(route('settings.company.update'), [
            'company_name' => 'Acme GmbH',
            'company_address' => "Main St 1\n12345 City",
            'company_email' => 'billing@acme.test',
            'company_vat_id' => 'DE123456789',
            'invoice_number_prefix' => '2026-',
            'invoice_number_padding' => 4,
            'invoice_default_vat_rate' => 19,
            'invoice_payment_terms_days' => 14,
        ])->assertRedirect(route('settings.company.edit'));

        $s = AppSettings::current();
        $this->assertSame('Acme GmbH', $s->company_name);
        $this->assertSame('2026-', $s->invoice_number_prefix);
        $this->assertSame(4, $s->invoice_number_padding);
        $this->assertSame(14, $s->invoice_payment_terms_days);
        $this->assertSame('19.00', (string) $s->invoice_default_vat_rate);
    }

    public function test_it_validates_the_ranges(): void
    {
        $this->signIn();

        $this->put(route('settings.company.update'), [
            'company_email' => 'not-an-email',
            'invoice_number_padding' => 0,
            'invoice_default_vat_rate' => 500,
            'invoice_payment_terms_days' => 9999,
        ])->assertSessionHasErrors([
            'company_email',
            'invoice_number_padding',
            'invoice_default_vat_rate',
            'invoice_payment_terms_days',
        ]);
    }

    public function test_logo_upload_stores_and_streams_then_removes(): void
    {
        Storage::fake();
        $this->signIn();

        $this->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect();

        $path = AppSettings::current()->company_logo_path;
        $this->assertNotNull($path);
        Storage::assertExists($path);

        $this->get(route('settings.company.logo'))->assertOk();

        $this->put(route('settings.company.update'), ['remove_logo' => 1])->assertRedirect();
        $this->assertNull(AppSettings::current()->company_logo_path);
        Storage::assertMissing($path);
    }

    public function test_svg_logo_is_rejected(): void
    {
        Storage::fake();
        $this->signIn();

        $this->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull(AppSettings::current()->company_logo_path);
    }

    public function test_invoices_page_renders_for_authenticated_user(): void
    {
        $this->signIn();
        $this->get(route('invoices.index'))->assertOk();
    }
}
