<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Marking a selection read or unread.
 *
 * The endpoint is metadata-only, but the rows are not: an archived message
 * carries its search text, so a flag change rarely stays heap-only and rewrites
 * every index on the table. Marking fifty took 5.4 seconds on the live archive
 * and a few hundred came back as a 502. So the two properties worth holding are
 * that it only touches rows that actually change, and that it stays owner-scoped
 * while doing it.
 */
class MailSeenTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user): MailAccount
    {
        $account = new MailAccount;
        $account->forceFill([
            'user_id' => $user->id,
            'name' => 'Test',
            'host' => 'imap.example',
            'port' => 993,
            'username' => 'me@example.com',
            'password' => 'x',
            'encryption' => 'ssl',
        ])->save();

        return $account;
    }

    /** @param array<string,mixed> $attrs */
    private function message(User $user, MailAccount $account, array $attrs = []): MailMessage
    {
        $id = (string) Str::uuid();
        $m = new MailMessage;
        $m->forceFill(array_merge([
            'id' => $id, 'user_id' => $user->id, 'account_id' => $account->id, 'folder' => 'INBOX',
            'content_hash' => hash('sha256', $id), 'size' => 12, 'subject' => 'S', 'created_at' => now(),
        ], $attrs))->save();

        return $m;
    }

    public function test_it_reports_only_the_rows_it_actually_changed(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        $unread = $this->message($user, $account, ['seen' => false]);
        $alreadyRead = $this->message($user, $account, ['seen' => true, 'seen_at' => now()->subDay()]);

        $this->actingAs($user)
            ->postJson('/api/v1/mail/messages/seen', ['ids' => [$unread->id, $alreadyRead->id], 'seen' => true])
            ->assertOk()
            // Two were selected, one needed writing. Rewriting a row to the
            // value it already holds costs exactly as much as a real change.
            ->assertJsonPath('updated', 1);

        $this->assertTrue(MailMessage::findOrFail($unread->id)->seen);
        $this->assertNotNull(MailMessage::findOrFail($unread->id)->seen_at);
    }

    public function test_marking_unread_clears_the_timestamp(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $m = $this->message($user, $account, ['seen' => true, 'seen_at' => now()]);

        $this->actingAs($user)
            ->postJson('/api/v1/mail/messages/seen', ['ids' => [$m->id], 'seen' => false])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $fresh = MailMessage::findOrFail($m->id);
        $this->assertFalse($fresh->seen);
        $this->assertNull($fresh->seen_at);
    }

    public function test_a_large_selection_is_split_into_statements_and_still_lands(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        $ids = [];
        for ($i = 0; $i < 250; $i++) {
            $ids[] = $this->message($user, $account, ['seen' => false])->id;
        }

        $this->actingAs($user)
            ->postJson('/api/v1/mail/messages/seen', ['ids' => $ids, 'seen' => true])
            ->assertOk()
            ->assertJsonPath('updated', 250);

        $this->assertSame(0, MailMessage::query()->where('user_id', $user->id)->where('seen', false)->count());
    }

    public function test_it_cannot_touch_someone_elses_mail(): void
    {
        $owner = User::factory()->create();
        $theirs = $this->message($owner, $this->account($owner), ['seen' => false]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/mail/messages/seen', ['ids' => [$theirs->id], 'seen' => true])
            ->assertOk()
            ->assertJsonPath('updated', 0);

        $this->assertFalse(MailMessage::findOrFail($theirs->id)->seen);
    }
}
