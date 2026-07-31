<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContactNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_save_persists_channels(): void
    {
        $user = $this->signIn();
        $this->put(route('settings.contacts.update'), [
            'birthday' => ['mail', 'ntfy', 'mail'],
            'anniversary' => ['desktop'],
        ])->assertRedirect();

        $s = UserSetting::for($user->id);
        $this->assertSame(['mail', 'ntfy'], $s->contact_birthday_channels);
        $this->assertSame(['desktop'], $s->contact_anniversary_channels);
    }

    public function test_notify_relays_only_the_users_enabled_channels(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->update(['contact_birthday_channels' => ['mail', 'ntfy']]);

        $mock = Mockery::mock(ChannelNotifier::class);
        $mock->shouldReceive('send')->once()->with(['mail', 'ntfy'], 'Birthday', Mockery::type('string'), Mockery::type('array'));
        $this->app->instance(ChannelNotifier::class, $mock);

        $this->postJson(route('contacts.notify'), ['kind' => 'birthday', 'title' => 'Birthday', 'body' => 'X has a birthday today.'])
            ->assertOk()->assertJson(['sent' => true]);
    }

    public function test_notify_does_nothing_when_no_channels_enabled(): void
    {
        $this->signIn();

        $mock = Mockery::mock(ChannelNotifier::class);
        $mock->shouldNotReceive('send');
        $this->app->instance(ChannelNotifier::class, $mock);

        $this->postJson(route('contacts.notify'), ['kind' => 'anniversary', 'title' => 'Anniversary', 'body' => 'X.'])
            ->assertOk()->assertJson(['sent' => false]);
    }
}
