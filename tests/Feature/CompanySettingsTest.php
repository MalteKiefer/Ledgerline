<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    /** The current user's company profile, read fresh from the DB. */
    private function settings(int $userId): UserSetting
    {
        return UserSetting::query()->where('user_id', $userId)->firstOrFail();
    }

    public function test_user_can_edit_and_save_their_company_profile(): void
    {
        $user = $this->signIn();

        $this->get(route('settings.company.edit'))->assertOk();

        $this->put(route('settings.company.update'), [
            'company_name' => 'Acme GmbH',
            'company_address' => "Main St 1\n12345 City",
            'company_email' => 'billing@acme.test',
            'company_vat_id' => 'DE123456789',
            'invoice_number_format' => 'YYYY-NNNN',
            'invoice_next_number' => 42,
            'invoice_default_vat_rate' => 19,
            'invoice_payment_terms_days' => 14,
            'invoice_accent_color' => '#2563eb',
            'invoice_payment_methods' => 'Bank transfer',
        ])->assertRedirect(route('settings.company.edit'));

        $s = $this->settings($user->id);
        $this->assertSame('Acme GmbH', $s->company_name);
        $this->assertSame('YYYY-NNNN', $s->invoice_number_format);
        $this->assertSame(42, $s->invoice_next_number);
        $this->assertSame(14, $s->invoice_payment_terms_days);
        $this->assertSame('#2563eb', $s->invoice_accent_color);
        $this->assertSame('Bank transfer', $s->invoice_payment_methods);
        $this->assertSame('19.00', (string) $s->invoice_default_vat_rate);
    }

    public function test_company_profile_is_isolated_per_user(): void
    {
        $alice = $this->signIn();
        $this->put(route('settings.company.update'), ['company_name' => 'Alice Ltd'])->assertRedirect();

        $bob = $this->signIn();
        $this->put(route('settings.company.update'), ['company_name' => 'Bob Ltd'])->assertRedirect();

        $this->assertSame('Alice Ltd', $this->settings($alice->id)->company_name);
        $this->assertSame('Bob Ltd', $this->settings($bob->id)->company_name);
    }

    public function test_vat_scheme_toggle_persists(): void
    {
        $user = $this->signIn();
        // Default is Ist (true); unchecking sends the hidden 0 + present marker → Soll (false).
        $this->put(route('settings.company.update'), [
            'invoice_vat_ist_present' => '1',
            'invoice_vat_ist' => '0',
        ])->assertRedirect();
        $this->assertFalse($this->settings($user->id)->invoice_vat_ist);

        $this->put(route('settings.company.update'), [
            'invoice_vat_ist_present' => '1',
            'invoice_vat_ist' => '1',
        ])->assertRedirect();
        $this->assertTrue($this->settings($user->id)->fresh()->invoice_vat_ist);
    }

    public function test_it_rejects_a_bad_accent_colour(): void
    {
        $this->signIn();
        $this->put(route('settings.company.update'), ['invoice_accent_color' => 'blue'])
            ->assertSessionHasErrors('invoice_accent_color');
    }

    public function test_it_validates_the_ranges(): void
    {
        $this->signIn();

        $this->put(route('settings.company.update'), [
            'company_email' => 'not-an-email',
            'invoice_next_number' => 0,
            'invoice_default_vat_rate' => 500,
            'invoice_payment_terms_days' => 9999,
        ])->assertSessionHasErrors([
            'company_email',
            'invoice_next_number',
            'invoice_default_vat_rate',
            'invoice_payment_terms_days',
        ]);
    }

    public function test_it_stores_website_and_contacts_dropping_empty_rows(): void
    {
        $user = $this->signIn();

        $this->put(route('settings.company.update'), [
            'company_website' => 'https://acme.test',
            'company_contacts' => [
                ['name' => 'Jane', 'role' => 'Sales', 'email' => 'jane@acme.test', 'phone' => ''],
                ['name' => '', 'role' => '', 'email' => '', 'phone' => ''], // empty → dropped
            ],
        ])->assertRedirect();

        $s = \App\Models\UserSetting::for($user->id)->fresh();
        $this->assertSame('https://acme.test', $s->company_website);
        $this->assertCount(1, $s->company_contacts);
        $this->assertSame('Jane', $s->company_contacts[0]['name']);
        $this->assertSame('jane@acme.test', $s->company_contacts[0]['email']);
    }

    public function test_logo_upload_stores_and_streams_then_removes(): void
    {
        $disk = config('files.disk');
        Storage::fake($disk);
        $user = $this->signIn();

        $this->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect();

        $path = $this->settings($user->id)->company_logo_path;
        $this->assertNotNull($path);
        Storage::disk($disk)->assertExists($path);

        $this->get(route('settings.company.logo'))->assertOk();

        $this->put(route('settings.company.update'), ['remove_logo' => 1])->assertRedirect();
        $this->assertNull($this->settings($user->id)->company_logo_path);
        Storage::disk($disk)->assertMissing($path);
    }

    public function test_svg_logo_is_rejected(): void
    {
        Storage::fake();
        $user = $this->signIn();

        $this->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull(UserSetting::for($user->id)->company_logo_path);
    }

    public function test_invoices_page_renders_for_authenticated_user(): void
    {
        $this->signIn();
        $this->get(route('finance.index'))->assertOk();
    }
}
