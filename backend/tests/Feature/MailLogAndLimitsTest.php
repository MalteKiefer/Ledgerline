<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\MailAccount;
use App\Models\User;
use App\Support\Mail\MailLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two small surfaces that had no coverage: the per-account sync log (which
 * belongs to one account and must not be readable across accounts) and the
 * admin-only workspace Files limits.
 *
 * Each authenticated case gets its own test: the auth guard caches the first
 * resolved user for the lifetime of a test, so a second actingAs/bearer in the
 * same test would silently keep authenticating as the first one.
 */
class MailLogAndLimitsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> Device-scoped bearer, as the API expects. */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    public function test_the_owner_reads_the_sync_log_of_their_account(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        MailLogger::record($account, 'info', 'sync_done', null, 'Sync finished.');

        $this->getJson(route('api.mail.accounts.logs', $account->id), $this->bearer($owner))
            ->assertOk()
            ->assertJsonPath('data.0.event', 'sync_done');
    }

    public function test_a_stranger_cannot_read_another_accounts_sync_log(): void
    {
        $account = MailAccount::factory()->create(['user_id' => User::factory()->create()->id]);
        MailLogger::record($account, 'info', 'sync_done', null, 'Sync finished.');

        $this->getJson(route('api.mail.accounts.logs', $account->id), $this->bearer(User::factory()->create()))
            ->assertNotFound();
    }

    public function test_files_limits_reject_an_anonymous_caller(): void
    {
        $this->getJson(route('api.admin.files-limits.show'))->assertUnauthorized();
    }

    public function test_files_limits_are_forbidden_for_a_plain_user(): void
    {
        $this->getJson(route('api.admin.files-limits.show'), $this->bearer(User::factory()->create()))
            ->assertForbidden();
    }

    public function test_an_admin_reads_and_updates_the_files_limits(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $headers = $this->bearer($admin);

        $this->getJson(route('api.admin.files-limits.show'), $headers)
            ->assertOk()
            ->assertJsonStructure(['files_max_upload_mb', 'files_blob_orphan_grace_hours']);

        $this->putJson(route('api.admin.files-limits.update'), [
            'files_max_upload_mb' => 256,
            'files_blob_orphan_grace_hours' => 48,
        ], $headers)->assertOk()->assertJsonPath('files_max_upload_mb', 256);

        $settings = AppSettings::current()->fresh();
        $this->assertSame(256, (int) $settings->files_max_upload_mb);
        $this->assertSame(48, (int) $settings->files_blob_orphan_grace_hours);
    }
}
