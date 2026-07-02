<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_vault_idle_timeout_can_be_configured(): void
    {
        $this->signIn();

        $this->get(route('settings.security.edit'))->assertOk();

        $this->put(route('settings.security.update'), ['vault_idle_minutes' => 10, 'mail_sync_minutes' => 5])
            ->assertRedirect(route('settings.security.edit'));

        $this->assertSame(10, AppSettings::current()->vault_idle_minutes);
        $this->assertSame(5, AppSettings::current()->mail_sync_minutes);
    }

    public function test_idle_timeout_is_bounded(): void
    {
        $this->signIn();

        $this->put(route('settings.security.update'), ['vault_idle_minutes' => 0, 'mail_sync_minutes' => 5])
            ->assertSessionHasErrors('vault_idle_minutes');
    }

    public function test_mail_sync_must_be_at_least_five_minutes(): void
    {
        $this->signIn();

        $this->put(route('settings.security.update'), ['vault_idle_minutes' => 10, 'mail_sync_minutes' => 3])
            ->assertSessionHasErrors('mail_sync_minutes');
    }

    public function test_mail_sync_cannot_exceed_the_idle_timeout(): void
    {
        $this->signIn();

        $this->put(route('settings.security.update'), ['vault_idle_minutes' => 6, 'mail_sync_minutes' => 10])
            ->assertSessionHasErrors('mail_sync_minutes');
    }
}
