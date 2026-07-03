<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_loads(): void
    {
        $this->signIn();
        $this->get(route('settings.mail.edit'))->assertOk();
    }

    public function test_the_sync_interval_can_be_configured(): void
    {
        $this->signIn();
        AppSettings::current()->update(['vault_idle_minutes' => 30]);

        $this->put(route('settings.mail.update'), ['mail_sync_minutes' => 15])
            ->assertRedirect(route('settings.mail.edit'));

        $this->assertSame(15, AppSettings::current()->mail_sync_minutes);
    }

    public function test_sync_must_be_at_least_five_minutes(): void
    {
        $this->signIn();

        $this->put(route('settings.mail.update'), ['mail_sync_minutes' => 3])
            ->assertSessionHasErrors('mail_sync_minutes');
    }

    public function test_sync_cannot_exceed_the_idle_timeout(): void
    {
        $this->signIn();
        AppSettings::current()->update(['vault_idle_minutes' => 6]);

        $this->put(route('settings.mail.update'), ['mail_sync_minutes' => 10])
            ->assertSessionHasErrors('mail_sync_minutes');
    }
}
