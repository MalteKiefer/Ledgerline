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
 * Suggesting the addresses you actually write to.
 */
class MailRecipientTest extends TestCase
{
    use RefreshDatabase;

    /** Named `received`, not `from`: Illuminate's TestCase already has from(). */
    private function received(User $user, string $email, string $name, int $times = 1): void
    {
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        for ($i = 0; $i < $times; $i++) {
            (new MailMessage)->forceFill([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'account_id' => $account->id,
                'folder' => 'INBOX',
                'from_email' => $email,
                'from_name' => $name,
                'content_hash' => hash('sha256', $email.$i),
                'size' => 1,
                'created_at' => now(),
            ])->save();
        }
    }

    public function test_the_address_you_hear_from_most_comes_first(): void
    {
        $user = User::factory()->create();
        $this->received($user, 'rare@example.com', 'Rare Sender', 1);
        $this->received($user, 'often@example.com', 'Often Sender', 5);

        $res = $this->actingAs($user)->getJson('/mail/recipients?q=example')->assertOk();

        $this->assertSame('often@example.com', $res->json('recipients.0.email'));
        $this->assertSame(5, $res->json('recipients.0.hits'));
    }

    public function test_it_matches_a_name_as_well_as_an_address(): void
    {
        $user = User::factory()->create();
        $this->received($user, 'mb@example.com', 'Marlene Böhm');

        $res = $this->actingAs($user)->getJson('/mail/recipients?q=marlene')->assertOk();

        $this->assertSame('mb@example.com', $res->json('recipients.0.email'));
    }

    public function test_it_is_case_insensitive_on_both_sides(): void
    {
        // Postgres LIKE is case-sensitive and SQLite's is not, so without
        // lower() on both sides this passes here and fails in production.
        $user = User::factory()->create();
        $this->received($user, 'Mixed@Example.com', 'Mixed Case');

        $this->actingAs($user)->getJson('/mail/recipients?q=MIXED')
            ->assertOk()
            ->assertJsonPath('recipients.0.email', 'mixed@example.com');
    }

    public function test_one_letter_suggests_nothing(): void
    {
        // Otherwise the answer is just "your busiest senders", which is not
        // what someone typing a name asked for.
        $user = User::factory()->create();
        $this->received($user, 'a@example.com', 'Someone', 9);

        $this->actingAs($user)->getJson('/mail/recipients?q=a')
            ->assertOk()
            ->assertJsonPath('recipients', []);
    }

    public function test_it_never_suggests_someone_elses_correspondents(): void
    {
        $mine = User::factory()->create();
        $this->received(User::factory()->create(), 'theirs@example.com', 'Theirs', 9);

        $this->actingAs($mine)->getJson('/mail/recipients?q=example')
            ->assertOk()
            ->assertJsonPath('recipients', []);
    }
}
