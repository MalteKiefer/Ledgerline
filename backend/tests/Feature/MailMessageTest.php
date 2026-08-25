<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailMessageTest extends TestCase
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
            'subject' => 'Subject '.$id,
            'from_email' => 'sender@example.com',
            'from_name' => 'Sender',
            'to_json' => [['name' => null, 'email' => 'me@example.com']],
            'cc_json' => [],
            'text_body' => 'plain body',
            'html_sanitized' => '<p>hi</p>',
            'search_text' => 'searchable content here',
            'seen' => false,
            'spam' => false,
            'date' => now(),
            'created_at' => now(),
        ], $attrs))->save();

        return $m;
    }

    public function test_index_lists_owner_messages_with_filters(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->message($user, $account, ['folder' => 'INBOX', 'seen' => false]);
        $this->message($user, $account, ['folder' => 'Sent', 'seen' => true]);
        $this->message($user, $account, ['trashed_at' => now()]);

        $this->actingAs($user);

        // Default excludes trashed.
        $this->getJson(route('mail.messages.index'))->assertOk()->assertJsonCount(2, 'data');
        // Folder filter.
        $this->getJson(route('mail.messages.index', ['folder' => 'Sent']))->assertOk()->assertJsonCount(1, 'data');
        // Seen filter.
        $this->getJson(route('mail.messages.index', ['seen' => 0]))->assertOk()->assertJsonCount(1, 'data');
        // Trashed only.
        $this->getJson(route('mail.messages.index', ['trashed' => 1]))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_full_text_search(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->message($user, $account, ['search_text' => 'quarterly invoice from acme', 'subject' => 'Invoice']);
        $this->message($user, $account, ['search_text' => 'lunch plans', 'subject' => 'Lunch']);

        $this->actingAs($user);
        $res = $this->getJson(route('mail.messages.index', ['q' => 'invoice']))->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Invoice', $res->json('data.0.subject'));
    }

    public function test_show_returns_reader_payload_and_owner_scope(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $m = $this->message($owner, $account);
        // The reader reads raw headers from the blob; write one.
        Storage::disk(config('files.disk'))->put('mail/'.$m->id, "Subject: X\r\nFrom: a@b.c\r\n\r\nbody");

        $this->actingAs($owner)
            ->getJson(route('mail.messages.show', $m->id))
            ->assertOk()
            ->assertJsonPath('message.text_body', 'plain body')
            ->assertJsonPath('message.html', '<p>hi</p>')
            ->assertJsonPath('message.headers_raw', "Subject: X\nFrom: a@b.c");

        // Foreign user → 404.
        $this->actingAs(User::factory()->create())
            ->getJson(route('mail.messages.show', $m->id))->assertNotFound();
    }

    public function test_seen_and_trash_toggles_bulk(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $a = $this->message($user, $account);
        $b = $this->message($user, $account);

        $this->actingAs($user);

        $this->postJson(route('mail.messages.seen'), ['ids' => [$a->id, $b->id], 'seen' => true])
            ->assertOk()->assertJson(['updated' => 2]);
        $this->assertTrue(MailMessage::findOrFail($a->id)->seen);
        $this->assertNotNull(MailMessage::findOrFail($a->id)->seen_at);

        $this->postJson(route('mail.messages.trash'), ['ids' => [$a->id]])
            ->assertOk()->assertJson(['updated' => 1]);
        $this->assertNotNull(MailMessage::findOrFail($a->id)->trashed_at);

        $this->postJson(route('mail.messages.restore'), ['ids' => [$a->id]])
            ->assertOk()->assertJson(['updated' => 1]);
        $this->assertNull(MailMessage::findOrFail($a->id)->trashed_at);
    }

    public function test_seen_and_trash_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $m = $this->message($owner, $account);

        // A different user cannot flip another user's message.
        $this->actingAs(User::factory()->create())
            ->postJson(route('mail.messages.trash'), ['ids' => [$m->id]])
            ->assertOk()->assertJson(['updated' => 0]);
        $this->assertNull(MailMessage::findOrFail($m->id)->trashed_at);
    }

    public function test_raw_eml_is_owner_scoped_and_sandboxed(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $m = $this->message($owner, $account);
        Storage::disk(config('files.disk'))->put('mail/'.$m->id, 'RAW-EML-BYTES');

        $res = $this->actingAs($owner)->get(route('mail.raw', $m->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertSame('RAW-EML-BYTES', $res->streamedContent());

        $this->actingAs(User::factory()->create())->get(route('mail.raw', $m->id))->assertNotFound();
    }

    public function test_the_list_is_ordered_by_the_message_date_not_the_import_time(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        // The state a backfill leaves behind: imported in one go (so created_at is
        // identical — the ingestor snaps it to the hour) but sent years apart.
        $importedAt = now()->startOfHour();
        $old = $this->message($user, $account, ['subject' => 'from 2019', 'date' => '2019-04-01 09:00:00', 'created_at' => $importedAt]);
        $recent = $this->message($user, $account, ['subject' => 'yesterday', 'date' => now()->subDay(), 'created_at' => $importedAt]);

        $subjects = collect($this->getJson(route('mail.messages.index'))->assertOk()->json('data'))->pluck('subject')->all();

        $this->assertSame(['yesterday', 'from 2019'], $subjects, 'newest by its own date first');
        $this->assertNotNull($old->id);
        $this->assertNotNull($recent->id);
    }

    public function test_a_message_without_a_date_header_falls_back_to_when_we_saw_it(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->message($user, $account, ['subject' => 'dated', 'date' => now()->subDays(5)]);
        // No Date header at all: it must still appear, ordered by arrival.
        $this->message($user, $account, ['subject' => 'undated', 'date' => null, 'created_at' => now()]);

        $subjects = collect($this->getJson(route('mail.messages.index'))->assertOk()->json('data'))->pluck('subject')->all();

        $this->assertSame(['undated', 'dated'], $subjects);
    }

    public function test_the_list_can_be_sorted_and_reversed(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->message($user, $account, ['subject' => 'Beta', 'from_name' => 'Zoe', 'size' => 900, 'date' => now()->subDay()]);
        $this->message($user, $account, ['subject' => 'Alpha', 'from_name' => 'Adam', 'size' => 100, 'date' => now()]);

        $order = fn (array $q): array => collect($this->getJson(route('mail.messages.index', $q))->assertOk()->json('data'))->pluck('subject')->all();

        $this->assertSame(['Alpha', 'Beta'], $order(['sort' => 'subject', 'dir' => 'asc']));
        $this->assertSame(['Beta', 'Alpha'], $order(['sort' => 'subject', 'dir' => 'desc']));
        $this->assertSame(['Alpha', 'Beta'], $order(['sort' => 'from', 'dir' => 'asc']));
        $this->assertSame(['Beta', 'Alpha'], $order(['sort' => 'size', 'dir' => 'desc']));
        // An unknown sort key must not 500 or return nothing — it falls back.
        $this->assertCount(2, $order(['sort' => 'nonsense']));
    }

    public function test_unread_first_still_puts_the_newest_on_top_within_each_group(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->message($user, $account, ['subject' => 'read-old', 'seen' => true, 'date' => now()->subDays(3)]);
        $this->message($user, $account, ['subject' => 'unread-old', 'seen' => false, 'date' => now()->subDays(2)]);
        $this->message($user, $account, ['subject' => 'unread-new', 'seen' => false, 'date' => now()]);

        $subjects = collect($this->getJson(route('mail.messages.index', ['sort' => 'unread']))->assertOk()->json('data'))->pluck('subject')->all();

        $this->assertSame(['unread-new', 'unread-old', 'read-old'], $subjects);
    }

    public function test_the_row_carries_a_snippet_without_quoted_text(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->message($user, $account, [
            // A reply: the quoted original says nothing about THIS message.
            'text_body' => "Passt so,   danke.\n\n> Am 1.4. schrieb Bob:\n> Sollen wir?\n--\nSignatur",
        ]);

        $row = $this->getJson(route('mail.messages.index'))->assertOk()->json('data.0');

        $this->assertSame('Passt so, danke.', $row['snippet']);
    }

    public function test_starring_a_message_and_the_flags_reaching_the_row(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $m = $this->message($user, $account);

        $this->assertFalse($this->getJson(route('mail.messages.index'))->json('data.0.flagged'));

        $this->postJson(route('mail.messages.flag'), ['ids' => [$m->id], 'flagged' => true])
            ->assertOk()->assertJson(['updated' => 1]);
        $this->assertTrue($this->getJson(route('mail.messages.index'))->json('data.0.flagged'));

        // Setting it again changes nothing — the write is skipped, not repeated.
        $this->postJson(route('mail.messages.flag'), ['ids' => [$m->id], 'flagged' => true])
            ->assertOk()->assertJson(['updated' => 0]);
    }

    public function test_starring_never_reaches_another_account(): void
    {
        $owner = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner);
        $m = $this->message($owner, $account);

        app('auth')->forgetGuards();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->postJson(route('mail.messages.flag'), ['ids' => [$m->id], 'flagged' => true])
            ->assertOk()->assertJson(['updated' => 0]);
        $this->assertFalse((bool) $m->fresh()?->flagged);
    }

    public function test_search_narrows_by_field_flag_and_date(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->message($user, $account, [
            'subject' => 'Rechnung 2026-01', 'from_name' => 'netcup GmbH', 'from_email' => 'billing@netcup.de',
            'seen' => false, 'has_attachment' => true, 'date' => '2026-03-01 10:00:00',
        ]);
        $this->message($user, $account, [
            'subject' => 'Newsletter', 'from_name' => 'Telekom', 'from_email' => 'news@telekom.de',
            'seen' => true, 'has_attachment' => false, 'date' => '2026-03-02 10:00:00',
        ]);

        $subjects = fn (string $q): array => collect(
            $this->getJson(route('mail.messages.index', ['q' => $q]))->assertOk()->json('data')
        )->pluck('subject')->all();

        $this->assertSame(['Rechnung 2026-01'], $subjects('from:netcup'));
        $this->assertSame(['Rechnung 2026-01'], $subjects('subject:rechnung'));
        $this->assertSame(['Rechnung 2026-01'], $subjects('is:unread'));
        $this->assertSame(['Newsletter'], $subjects('is:read'));
        $this->assertSame(['Rechnung 2026-01'], $subjects('has:attachment'));
        // AND: both terms must hold.
        $this->assertSame(['Rechnung 2026-01'], $subjects('from:netcup is:unread'));
        $this->assertSame([], $subjects('from:netcup is:read'));
        // Inclusive date bounds.
        $this->assertSame(['Rechnung 2026-01'], $subjects('before:2026-03-01'));
        $this->assertSame(['Newsletter'], $subjects('after:2026-03-02'));
    }

    public function test_search_matches_recipients_and_leaves_a_typo_as_text(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->message($user, $account, ['subject' => 'To me', 'to_json' => [['name' => 'Malte', 'email' => 'me@example.org']]]);
        $this->message($user, $account, ['subject' => 'To someone else', 'to_json' => [['name' => 'Bob', 'email' => 'bob@example.net']]]);

        $subjects = fn (string $q): array => collect(
            $this->getJson(route('mail.messages.index', ['q' => $q]))->assertOk()->json('data')
        )->pluck('subject')->all();

        $this->assertSame(['To me'], $subjects('to:me@example.org'));
        // A mistyped field searches for the text instead of filtering to nothing.
        $this->assertSame([], $subjects('fom:me@example.org'));
        $this->assertCount(2, $subjects(''));
    }
}
