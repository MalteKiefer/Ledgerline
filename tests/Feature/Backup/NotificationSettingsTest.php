<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\AppSettings;
use App\Services\Backup\BackupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_loads(): void
    {
        $this->signIn();
        $this->get(route('settings.notifications.edit'))->assertOk();
    }

    public function test_it_saves_channels_and_encrypts_secrets_at_rest(): void
    {
        $this->signIn();

        $this->put(route('settings.notifications.update'), [
            'mail_enabled' => '1',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'user',
            'smtp_password' => 'secret-pass',
            'smtp_from_address' => 'backup@example.test',
            'ntfy_enabled' => '1',
            'ntfy_url' => 'https://ntfy.sh',
            'ntfy_topic' => 'backups',
        ])->assertRedirect(route('settings.notifications.edit'));

        $settings = AppSettings::current();
        $this->assertTrue($settings->mail_enabled);
        $this->assertSame('smtp.example.test', $settings->smtp_host);
        $this->assertSame('secret-pass', $settings->smtp_password);

        // Stored ciphertext, not the plaintext password.
        $raw = \DB::table('app_settings')->value('smtp_password');
        $this->assertNotSame('secret-pass', $raw);
    }

    public function test_a_blank_secret_keeps_the_stored_value(): void
    {
        $this->signIn();
        AppSettings::current()->update(['smtp_password' => 'keep-me']);

        $this->put(route('settings.notifications.update'), [
            'mail_enabled' => '1',
            'smtp_host' => 'smtp.example.test',
            'smtp_password' => '',
        ])->assertRedirect();

        $this->assertSame('keep-me', AppSettings::current()->smtp_password);
    }

    public function test_a_test_message_can_be_sent(): void
    {
        $this->signIn();
        $this->mock(BackupNotifier::class, function ($mock): void {
            $mock->shouldReceive('test')->once()->with('ntfy')->andReturnNull();
        });

        $this->post(route('settings.notifications.test'), ['channel' => 'ntfy'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_a_failing_test_reports_the_error(): void
    {
        $this->signIn();
        $this->mock(BackupNotifier::class, function ($mock): void {
            $mock->shouldReceive('test')->andThrow(new \RuntimeException('no route to host'));
        });

        $this->post(route('settings.notifications.test'), ['channel' => 'mail'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
