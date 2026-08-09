<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ReindexMail;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailFolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function message(User $user, MailAccount $account, array $attrs = []): MailMessage
    {
        $id = (string) Str::uuid();
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => $id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => hash('sha256', $id),
            'size' => 100,
            'subject' => 'Subject',
            'seen' => false,
            'spam' => false,
            'created_at' => now(),
        ], $attrs))->save();

        return $m;
    }

    public function test_folders_returns_total_and_unread_counts(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->message($user, $account, ['folder' => 'INBOX', 'seen' => false]);
        $this->message($user, $account, ['folder' => 'INBOX', 'seen' => true]);
        $this->message($user, $account, ['folder' => 'Sent', 'seen' => true]);
        // Trashed messages are excluded from folder counts.
        $this->message($user, $account, ['folder' => 'INBOX', 'trashed_at' => now()]);

        $res = $this->actingAs($user)->getJson(route('mail.folders.index'))->assertOk();

        $folders = collect($res->json('folders'))->keyBy('folder');
        $this->assertSame(2, $folders['INBOX']['total']);
        $this->assertSame(1, $folders['INBOX']['unread']);
        $this->assertSame(1, $folders['Sent']['total']);
        $this->assertSame(0, $folders['Sent']['unread']);
        $this->assertSame(3, $res->json('total'));
        $this->assertSame(1, $res->json('unread'));
    }

    public function test_folders_unified_across_accounts_and_account_filter(): void
    {
        $user = User::factory()->create();
        $a1 = MailAccount::factory()->create(['user_id' => $user->id]);
        $a2 = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->message($user, $a1, ['folder' => 'INBOX']);
        $this->message($user, $a2, ['folder' => 'INBOX']);

        // Unified: both accounts.
        $this->actingAs($user)->getJson(route('mail.folders.index'))
            ->assertOk()->assertJsonCount(2, 'folders');
        // Scoped to one account.
        $this->getJson(route('mail.folders.index', ['account_id' => $a1->id]))
            ->assertOk()->assertJsonCount(1, 'folders');
    }

    public function test_folders_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $this->message($owner, $account);

        $this->actingAs(User::factory()->create())
            ->getJson(route('mail.folders.index'))
            ->assertOk()->assertJsonCount(0, 'folders');
    }

    public function test_reindex_backfills_missing_search_text(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        // A legacy row with no search index.
        $m = $this->message($user, $account, [
            'subject' => 'Quarterly report',
            'from_email' => 'acme@example.com',
            'text_body' => 'the numbers are attached',
            'search_text' => null,
            'indexed_at' => null,
        ]);

        $this->artisan(ReindexMail::class)->assertSuccessful();

        $m->refresh();
        $this->assertNotNull($m->indexed_at);
        $this->assertStringContainsString('Quarterly report', (string) $m->search_text);
        $this->assertStringContainsString('acme@example.com', (string) $m->search_text);
        $this->assertStringContainsString('numbers', (string) $m->search_text);

        // Now searchable via the messages index (sqlite LIKE / pgsql tsvector).
        $this->actingAs($user)
            ->getJson(route('mail.messages.index', ['q' => 'Quarterly']))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reindex_skips_already_indexed_unless_all(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $m = $this->message($user, $account, [
            'subject' => 'New subject',
            'search_text' => 'stale index',
            'indexed_at' => now()->subDay(),
        ]);

        // Default pass skips indexed rows.
        $this->artisan(ReindexMail::class)->assertSuccessful();
        $this->assertSame('stale index', (string) $m->fresh()?->search_text);

        // --all rebuilds from the denormalised columns.
        $this->artisan(ReindexMail::class, ['--all' => true])->assertSuccessful();
        $this->assertStringContainsString('New subject', (string) $m->fresh()?->search_text);
    }
}
