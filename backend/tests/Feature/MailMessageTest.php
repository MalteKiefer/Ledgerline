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
}
