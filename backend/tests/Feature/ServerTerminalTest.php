<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunServerTerminal;
use App\Models\AuditLog;
use App\Models\Server;
use App\Models\User;
use App\Support\TerminalChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerTerminalTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery-staple';

    #[Test]
    public function opening_a_session_requires_the_account_password(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/terminal", ['current_password' => 'not-it'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'password_invalid');

        // Nothing was started, and nothing was recorded as if it had been.
        Queue::assertNothingPushed();
        $this->assertSame(0, AuditLog::query()->where('action', 'server.terminal_opened')->count());
    }

    #[Test]
    public function the_password_is_required_again_for_every_session(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);

        $first = $this->open($user, $server);
        $this->actingAs($user)->deleteJson("/api/v1/servers/{$server->id}/terminal/{$first}")->assertOk();

        // A second session without the password is refused even though one was
        // just opened successfully — nothing about the first is remembered.
        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/terminal", [])
            ->assertStatus(422);
    }

    #[Test]
    public function opening_a_session_is_audited_and_queued_on_its_own_queue(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);

        $this->open($user, $server);

        // The terminal must not share the queue that carries the probes and the
        // backups: a session holds its worker for as long as somebody is typing.
        Queue::assertPushed(RunServerTerminal::class, fn (RunServerTerminal $job): bool => $job->queue === 'terminal' && $job->serverId === $server->id);

        $audit = AuditLog::query()->where('action', 'server.terminal_opened')->firstOrFail();
        $this->assertSame($user->id, $audit->user_id);
    }

    #[Test]
    public function a_session_belongs_to_one_user_and_one_server(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);
        $other = $this->server($user, 'db01');
        $session = $this->open($user, $server);

        // Right session id, wrong server: the id alone is not a capability.
        $this->actingAs($user)
            ->getJson("/api/v1/servers/{$other->id}/terminal/{$session}")
            ->assertNotFound();

        // Another account cannot reach it at all.
        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->getJson("/api/v1/servers/{$server->id}/terminal/{$session}")
            ->assertNotFound();
    }

    #[Test]
    public function output_is_delivered_once_and_in_order(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);
        $session = $this->open($user, $server);

        $channel = new TerminalChannel($session);
        $channel->markReady();
        $channel->pushOutput('one ');
        $channel->pushOutput('two');

        $first = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/terminal/{$session}?cursor=0")->assertOk()->json();
        $this->assertTrue($first['ready']);
        $this->assertSame('one two', base64_decode($first['data']));

        // Polling again from the returned cursor yields nothing: a reader never
        // sees the same bytes twice.
        $second = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/terminal/{$session}?cursor={$first['cursor']}")->assertOk()->json();
        $this->assertSame('', base64_decode($second['data']));

        $channel->pushOutput('three');
        $third = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/terminal/{$session}?cursor={$second['cursor']}")->assertOk()->json();
        $this->assertSame('three', base64_decode($third['data']));
    }

    #[Test]
    public function keystrokes_reach_the_channel_and_polling_keeps_it_alive(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);
        $session = $this->open($user, $server);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/terminal/{$session}/input", ['data' => base64_encode("ls -la\n")])
            ->assertOk();

        $channel = new TerminalChannel($session);
        // The newline survives. It travels base64-encoded, out of reach of the
        // global TrimStrings middleware that would otherwise eat it — and a
        // terminal that cannot send Enter cannot run anything.
        $this->assertSame("ls -la\n", $channel->readInput(0)['data']);
        // The job reads this to decide whether anyone is still watching.
        $this->assertLessThanOrEqual(1, $channel->idleSeconds());
    }

    #[Test]
    public function closing_marks_the_session_and_further_polls_report_it(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);
        $session = $this->open($user, $server);

        $this->actingAs($user)->deleteJson("/api/v1/servers/{$server->id}/terminal/{$session}")->assertOk();

        $body = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/terminal/{$session}")->assertOk()->json();
        $this->assertSame('closed', $body['closed']);
    }

    #[Test]
    public function a_third_concurrent_session_is_refused(): void
    {
        Queue::fake();
        $user = $this->owner();
        $server = $this->server($user);

        $this->open($user, $server);
        $this->open($user, $server);

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/terminal", ['current_password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertJsonPath('error', 'too_many_sessions');
    }

    private function open(User $user, Server $server): string
    {
        $body = $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/terminal", ['current_password' => self::PASSWORD, 'cols' => 100, 'rows' => 30])
            ->assertStatus(201)
            ->json();

        return (string) $body['session'];
    }

    private function owner(): User
    {
        return User::factory()->create(['password' => Hash::make(self::PASSWORD)]);
    }

    private function server(User $owner, string $name = 'web01'): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => $owner->id,
            'name' => $name,
            'host' => '10.0.0.9',
            'port' => 22,
            'username' => 'monitor',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'KEY', 'passphrase' => ''],
            'host_fingerprint' => 'SHA256:'.str_repeat('a', 43),
            'host_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyBlobForTesting0000000',
            'enabled' => true,
        ])->save();

        return $server;
    }
}
