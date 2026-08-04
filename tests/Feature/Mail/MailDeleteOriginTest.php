<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\Mail\ImapDeleter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MailDeleteOriginTest extends TestCase
{
    use RefreshDatabase;

    private function message(User $user, MailAccount $account): MailMessage
    {
        $m = new MailMessage([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'folder' => 'INBOX',
            'content_hash' => hash('sha256', 'x'),
            'size' => 3,
            'sealed_key' => '{}',
        ]);
        $m->user_id = $user->id;
        $m->save();

        return $m;
    }

    public function test_owner_can_delete_a_message_from_its_origin(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id, 'password' => 'secret-pw']);
        $message = $this->message($user, $account);

        $this->mock(ImapDeleter::class, function ($m): void {
            $m->shouldReceive('delete')->once()
                ->withArgs(fn ($acc, $folder, $mid): bool => $folder === 'INBOX' && $mid === '<abc@host>')
                ->andReturn(1);
        });

        $this->actingAs($user)
            ->postJson("/api/v1/mail/messages/{$message->id}/delete-origin", ['message_id' => '<abc@host>'])
            ->assertOk()
            ->assertJson(['deleted' => 1]);
    }

    public function test_a_different_user_cannot_delete_from_origin(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $owner->id]);
        $message = $this->message($owner, $account);

        $this->mock(ImapDeleter::class, fn ($m) => $m->shouldNotReceive('delete'));

        $this->actingAs($other)
            ->postJson("/api/v1/mail/messages/{$message->id}/delete-origin", ['message_id' => '<x@y>'])
            ->assertNotFound();
    }

    public function test_delete_failure_returns_502_without_leaking(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $message = $this->message($user, $account);

        $this->mock(ImapDeleter::class, fn ($m) => $m->shouldReceive('delete')->andThrow(new \RuntimeException('imap boom: secret host')));

        $response = $this->actingAs($user)
            ->postJson("/api/v1/mail/messages/{$message->id}/delete-origin", ['message_id' => '<x@y>']);

        $response->assertStatus(502)->assertJson(['error' => 'delete_failed']);
        $this->assertStringNotContainsString('secret host', $response->getContent() ?: '');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
