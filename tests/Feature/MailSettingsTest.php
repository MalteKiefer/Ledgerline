<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
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

    public function test_the_sync_interval_is_per_user(): void
    {
        $alice = $this->signIn();

        $this->put(route('settings.mail.update'), ['mail_sync_minutes' => 15])
            ->assertRedirect(route('settings.mail.edit'));

        $this->assertSame(15, UserSetting::for($alice->id)->mail_sync_minutes);

        // A second user keeps the default, unaffected by Alice's choice.
        $bob = $this->signIn();
        $this->assertSame(5, UserSetting::for($bob->id)->mail_sync_minutes);
    }

    public function test_sync_must_be_at_least_five_minutes(): void
    {
        $this->signIn();

        $this->put(route('settings.mail.update'), ['mail_sync_minutes' => 3])
            ->assertSessionHasErrors('mail_sync_minutes');
    }

    public function test_sync_cannot_exceed_the_daily_maximum(): void
    {
        $this->signIn();

        $this->put(route('settings.mail.update'), ['mail_sync_minutes' => 1441])
            ->assertSessionHasErrors('mail_sync_minutes');
    }
}
