<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * The mail settings PAGE (Task 10) drives its account CRUD over SESSION-authed
 * web routes rather than `/api/v1` (which is Sanctum bearer-token only — the
 * browser page has no device token). These routes mount the exact same
 * Api\MailAccountController used by /api/v1/mail/accounts (guard-agnostic via
 * Controller::requireUser()), so the request/response contract is identical;
 * MailAccountApiTest already covers that contract in depth (password never
 * returned, blank-password-keeps-existing, owner scoping, link-local host
 * rejection, module gate, …). This file only proves the web wiring itself:
 * the page renders and the primary web-route actions work end to end.
 */
class MailAccountWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mail_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/mail')->assertOk();
    }

    public function test_an_account_can_be_created_listed_and_deleted_over_the_web_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/mail/accounts', [
                'name' => 'Work',
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'me@example.com',
                'password' => 'super-secret-imap-pw',
                'encryption' => 'ssl',
            ])
            ->assertCreated()
            ->assertJsonMissingPath('account.password');

        $account = MailAccount::query()->firstOrFail();
        $this->assertSame($user->id, $account->user_id);
        $this->assertSame('super-secret-imap-pw', $account->password);

        $list = $this->actingAs($user)->getJson('/mail/accounts')->assertOk();
        $list->assertJsonPath('accounts.0.id', $account->id);
        $list->assertJsonMissingPath('accounts.0.password');

        $this->actingAs($user)
            ->deleteJson("/mail/accounts/{$account->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('mail_accounts', ['id' => $account->id]);
    }

    public function test_the_web_route_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->putJson("/mail/accounts/{$account->id}", [
                'name' => 'Hijacked',
                'host' => $account->host,
                'port' => $account->port,
                'username' => $account->username,
                'password' => '',
                'encryption' => $account->encryption,
            ])
            ->assertNotFound();
    }

    public function test_sync_now_dispatches_the_sync_job_over_the_web_route(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/mail/accounts/{$account->id}/sync")
            ->assertOk();
    }

    public function test_status_is_readable_over_the_web_route(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create([
            'user_id' => $user->id,
            'status' => 'error',
            'last_error' => 'connection refused',
        ]);

        $this->actingAs($user)
            ->getJson("/mail/accounts/{$account->id}/status")
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('last_error', 'connection refused');
    }

    public function test_a_user_without_the_mail_module_is_blocked_on_the_web_route(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['modules' => ['dashboard']])->save();

        $this->actingAs($user)->get('/mail')->assertForbidden();
        $this->actingAs($user)->getJson('/mail/accounts')->assertForbidden();
    }
}
