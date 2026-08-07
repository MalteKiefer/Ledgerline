<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_screen_renders(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_a_reset_link_is_emailed(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_user_can_reset_their_password_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-passphrase',
                'password_confirmation' => 'a-brand-new-passphrase',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('a-brand-new-passphrase', (string) $user->refresh()->password));
    }

    public function test_reset_enforces_the_twelve_character_floor(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])->assertSessionHasErrors('password');

            return true;
        });
    }

    public function test_reset_revokes_the_users_device_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        // A paired device / API bearer that an attacker might hold.
        $user->createToken('device');
        $this->assertSame(1, $user->tokens()->count());

        $this->post(route('password.email'), ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-passphrase',
                'password_confirmation' => 'a-brand-new-passphrase',
            ])->assertSessionHasNoErrors();

            return true;
        });

        // The reset acts as a kill switch: stolen device tokens are gone.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_purges_persisted_database_sessions(): void
    {
        // The default (prod) session driver is `database`; the test env uses
        // `array`, so force the DB path to exercise revokeAllAccess()'s session
        // purge. The `sessions` table is provided by the app's migrations.
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
        $this->assertTrue(Schema::hasTable('sessions'));

        Notification::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'victim-a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'victim-b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'bystander', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-passphrase',
                'password_confirmation' => 'a-brand-new-passphrase',
            ])->assertSessionHasNoErrors();

            return true;
        });

        // The victim's sessions are gone; another user's is untouched.
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
    }
}
