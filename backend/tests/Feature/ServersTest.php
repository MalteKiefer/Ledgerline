<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CollectServerFacts;
use App\Models\Server;
use App\Models\ServerFact;
use App\Models\User;
use App\Services\Servers\ProbeResult;
use App\Services\Servers\ServerMonitor;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The monitored-server surface. What matters here is not the happy path (a
 * snapshot renders) but the properties the feature rests on: the host key must
 * be confirmed and pinned before anything is saved, credentials must never come
 * back out, and a request must never open an SSH session on the collection path.
 *
 * The probe is faked throughout — these tests are about the controller's rules,
 * not about whether OpenSSH works. Each authenticated case gets its own test:
 * the auth guard caches the first resolved user for the lifetime of a test.
 */
class ServersTest extends TestCase
{
    use RefreshDatabase;

    /** A fingerprint in the exact shape the controller accepts. */
    private const FP = 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    private const HOST_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyBlobForTesting0000000';

    protected function setUp(): void
    {
        parent::setUp();
        // Saving a server re-scans the host key to verify the confirmed
        // fingerprint, so every create/update path needs a probe that answers.
        $this->fakeProbe(new ProbeResult(true, fingerprint: self::FP, hostKey: self::HOST_KEY));
    }

    private function fakeProbe(ProbeResult $result): void
    {
        $this->app->instance(ServerProbe::class, new class($result) extends ServerProbe
        {
            public function __construct(private ProbeResult $result) {}

            public function run(ServerTarget $target, bool $interactive = false): ProbeResult
            {
                return $this->result;
            }
        });
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
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
            'credentials' => ['private_key' => 'PRIVATE-KEY-BODY', 'passphrase' => ''],
            'host_fingerprint' => self::FP,
            'host_key' => self::HOST_KEY,
            'enabled' => true,
        ])->save();

        return $server;
    }

    /** @return array<string, mixed> */
    private function newServerPayload(): array
    {
        return [
            'name' => 'web01',
            'host' => '10.0.0.9',
            'username' => 'monitor',
            'auth_type' => 'key',
            'private_key' => 'PRIVATE-KEY-BODY',
            'host_fingerprint' => self::FP,
        ];
    }

    public function test_creating_a_server_requires_a_confirmed_host_key(): void
    {
        // Without a pin the very first connection — the one carrying the
        // credentials — would be interceptable, so the field is required.
        $payload = $this->newServerPayload();
        unset($payload['host_fingerprint']);

        $this->postJson(route('api.servers.store'), $payload, $this->bearer(User::factory()->create()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_fingerprint');
    }

    public function test_a_malformed_fingerprint_is_rejected(): void
    {
        $this->postJson(route('api.servers.store'), [...$this->newServerPayload(), 'host_fingerprint' => 'md5:aa:bb:cc'], $this->bearer(User::factory()->create()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_fingerprint');
    }

    public function test_a_host_that_answers_with_a_different_key_is_not_saved(): void
    {
        // The confirmed fingerprint is re-verified against a fresh scan; a
        // mismatch means nobody actually verified this host.
        $this->fakeProbe(new ProbeResult(false, error: 'fingerprint_mismatch', fingerprint: 'SHA256:BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB'));

        $this->postJson(route('api.servers.store'), $this->newServerPayload(), $this->bearer(User::factory()->create()))
            ->assertStatus(422)
            ->assertJsonPath('error', 'host_key_unconfirmed');
        $this->assertDatabaseCount('servers', 0);
    }

    public function test_a_created_server_pins_the_host_key_and_hides_its_credentials(): void
    {
        Queue::fake();

        $response = $this->postJson(route('api.servers.store'), $this->newServerPayload(), $this->bearer(User::factory()->create()))
            ->assertCreated();

        $response->assertJsonPath('server.name', 'web01')
            ->assertJsonMissingPath('server.credentials')
            ->assertJsonMissingPath('server.private_key');
        $this->assertStringNotContainsString('PRIVATE-KEY-BODY', $response->getContent() ?: '');

        // Stored encrypted, so the plaintext is not in the column either.
        $raw = (string) DB::table('servers')->value('credentials');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('PRIVATE-KEY-BODY', $raw);

        // The whole key is kept, not only its fingerprint: OpenSSH enforces the
        // pin from a known_hosts entry, which a fingerprint alone cannot fill.
        $server = Server::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(self::HOST_KEY, $server->host_key);

        // The first snapshot is collected in the queue, not in this request.
        Queue::assertPushed(CollectServerFacts::class);
    }

    public function test_a_blank_secret_on_update_preserves_the_stored_one(): void
    {
        $owner = User::factory()->create();
        $server = $this->server($owner);

        $this->putJson(route('api.servers.update', $server->id), [
            'name' => 'web01-renamed',
            'private_key' => '',
        ], $this->bearer($owner))->assertOk();

        $server->refresh();
        $this->assertSame('web01-renamed', $server->name);
        $this->assertSame('PRIVATE-KEY-BODY', $server->credentials['private_key'] ?? null);
    }

    public function test_renaming_does_not_require_the_host_to_be_reachable(): void
    {
        $owner = User::factory()->create();
        $server = $this->server($owner);
        // Nothing about the address or the pin changed, so an unreachable host
        // must not block an edit that never touches the connection.
        $this->fakeProbe(new ProbeResult(false, error: 'timeout'));

        $this->putJson(route('api.servers.update', $server->id), ['name' => 'renamed'], $this->bearer($owner))
            ->assertOk();

        $server->refresh();
        $this->assertSame('renamed', $server->name);
        $this->assertSame(self::HOST_KEY, $server->host_key);
    }

    public function test_password_authentication_is_refused(): void
    {
        // OpenSSH takes no password without a terminal, so offering the option
        // would promise something the transport cannot do.
        $owner = User::factory()->create();

        $this->putJson(route('api.servers.update', $this->server($owner)->id), [
            'auth_type' => 'password',
        ], $this->bearer($owner))
            ->assertStatus(422)
            ->assertJsonValidationErrors('auth_type');
    }

    public function test_root_is_allowed_but_warned_about_in_the_form(): void
    {
        // Not refused: a host may have no other account. The dialog says what it
        // costs, and that is where the judgement belongs.
        Queue::fake();

        $this->postJson(route('api.servers.store'), [...$this->newServerPayload(), 'username' => 'root'], $this->bearer(User::factory()->create()))
            ->assertCreated()
            ->assertJsonPath('server.username', 'root');
    }

    public function test_the_detail_view_carries_the_public_key_for_the_removal_steps(): void
    {
        $owner = User::factory()->create();
        // A real generated key, so the derivation is exercised rather than mocked.
        $token = $this->postJson(route('api.servers.keypair'), [], $this->bearer($owner))->json('token');
        $payload = $this->newServerPayload();
        unset($payload['private_key']);
        $id = $this->postJson(route('api.servers.store'), [...$payload, 'keypair_token' => $token], $this->bearer($owner))
            ->assertCreated()->json('server.id');

        $public = $this->getJson(route('api.servers.show', $id), $this->bearer($owner))
            ->assertOk()->json('server.public_key');

        // The public half only — the private key must never appear anywhere.
        $this->assertIsString($public);
        $this->assertStringStartsWith('ssh-ed25519 ', $public);
    }

    public function test_the_detail_view_never_leaks_the_private_key(): void
    {
        $owner = User::factory()->create();
        $server = $this->server($owner);

        $body = (string) $this->getJson(route('api.servers.show', $server->id), $this->bearer($owner))->assertOk()->getContent();

        $this->assertStringNotContainsString('PRIVATE-KEY-BODY', $body);
        $this->assertStringNotContainsString('PRIVATE KEY', $body);
    }

    public function test_a_stranger_cannot_read_another_users_server(): void
    {
        $server = $this->server(User::factory()->create());

        $this->getJson(route('api.servers.show', $server->id), $this->bearer(User::factory()->create()))
            ->assertNotFound();
    }

    public function test_a_stranger_cannot_delete_another_users_server(): void
    {
        $server = $this->server(User::factory()->create());

        $this->deleteJson(route('api.servers.destroy', $server->id), [], $this->bearer(User::factory()->create()))
            ->assertNotFound();
        $this->assertDatabaseHas('servers', ['id' => $server->id]);
    }

    public function test_refresh_queues_a_probe_instead_of_connecting(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $server = $this->server($owner);

        $this->postJson(route('api.servers.refresh', $server->id), [], $this->bearer($owner))
            ->assertStatus(202)
            ->assertJsonPath('queued', true);

        Queue::assertPushed(CollectServerFacts::class, fn (CollectServerFacts $job): bool => $job->serverId === $server->id);
    }

    public function test_refresh_all_only_queues_the_callers_enabled_servers(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $this->server($owner, 'enabled-one');
        $this->server($owner, 'disabled-one')->forceFill(['enabled' => false])->save();
        $this->server(User::factory()->create(), 'someone-elses');

        $this->postJson(route('api.servers.refresh-all'), [], $this->bearer($owner))
            ->assertStatus(202)
            ->assertJsonPath('queued', 1);

        Queue::assertPushed(CollectServerFacts::class, 1);
    }

    public function test_the_module_gate_blocks_a_user_without_the_servers_module(): void
    {
        $user = User::factory()->create(['role' => 'user', 'modules' => ['finance']]);

        $this->getJson(route('api.servers.index'), $this->bearer($user))->assertForbidden();
    }

    public function test_the_published_probe_script_is_the_one_the_collector_runs(): void
    {
        // An operator installs this as the forced command on the target. If the
        // two ever drifted apart, every restricted key would return output the
        // parser does not understand.
        $this->getJson(route('api.servers.probe-script'), $this->bearer(User::factory()->create()))
            ->assertOk()
            ->assertJsonPath('script', ServerProbe::PROBE);
    }

    public function test_generating_a_keypair_never_returns_the_private_half(): void
    {
        $response = $this->postJson(route('api.servers.keypair'), [], $this->bearer(User::factory()->create()))
            ->assertOk()
            ->assertJsonStructure(['token', 'public_key', 'expires_in_minutes']);

        $this->assertStringContainsString('ssh-ed25519 ', (string) $response->json('public_key'));
        // The whole point of the token: the private key stays on this host.
        $this->assertStringNotContainsString('PRIVATE KEY', (string) $response->getContent());
    }

    public function test_a_generated_keypair_is_redeemed_by_its_token_on_create(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $token = $this->postJson(route('api.servers.keypair'), [], $this->bearer($owner))->json('token');

        $payload = $this->newServerPayload();
        // No private_key in the payload — the browser never had one.
        unset($payload['private_key']);

        $this->postJson(route('api.servers.store'), [...$payload, 'keypair_token' => $token], $this->bearer($owner))
            ->assertCreated();

        $server = Server::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertStringContainsString('OPENSSH PRIVATE KEY', (string) ($server->credentials['private_key'] ?? ''));
    }

    public function test_a_keypair_token_cannot_be_redeemed_by_another_user(): void
    {
        Queue::fake();
        $token = $this->postJson(route('api.servers.keypair'), [], $this->bearer(User::factory()->create()))->json('token');

        // The guard caches the first resolved user for the lifetime of a test, so
        // without this the second request would silently run as the FIRST user
        // and the cross-user claim would never actually be exercised.
        app('auth')->forgetGuards();

        $payload = $this->newServerPayload();
        unset($payload['private_key']);

        $this->postJson(route('api.servers.store'), [...$payload, 'keypair_token' => $token], $this->bearer(User::factory()->create()))
            ->assertCreated();

        $server = Server::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('', $server->credentials['private_key'] ?? null);
    }

    public function test_the_index_exposes_the_latest_snapshot(): void
    {
        $owner = User::factory()->create();
        $server = $this->server($owner);

        // Two runs: the newest must win, whichever order they were written in.
        $this->fact($server, ok: false, at: '2026-08-20 10:00:00', facts: null);
        $this->fact($server, ok: true, at: '2026-08-20 11:00:00', facts: ['kernel' => '6.1.0', 'disk_max_pct' => 61.0]);

        $this->getJson(route('api.servers.index'), $this->bearer($owner))
            ->assertOk()
            ->assertJsonPath('servers.0.status.ok', true)
            ->assertJsonPath('servers.0.facts.kernel', '6.1.0');
    }

    /** @param  array<string, mixed>|null  $facts */
    private function fact(Server $server, bool $ok, string $at, ?array $facts): void
    {
        $row = new ServerFact;
        $row->forceFill([
            'server_id' => $server->id,
            'ok' => $ok,
            'error' => $ok ? null : 'auth_failed',
            'facts' => $facts,
            'duration_ms' => 120,
            'collected_at' => $at,
        ])->save();
    }

    public function test_a_paused_server_still_answers_the_refresh_button(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $server->forceFill(['enabled' => false])->save();

        Queue::fake();
        $this->actingAs($user)->postJson("/api/v1/servers/{$server->id}/refresh")->assertStatus(202);

        // Pausing silences the schedule, not the button, so the queued job must
        // be the forced one -- otherwise it would bail out without a word and
        // the button would do nothing at all.
        Queue::assertPushed(CollectServerFacts::class, fn (CollectServerFacts $job): bool => $job->serverId === $server->id && $job->force);
    }

    public function test_the_scheduled_probe_still_skips_a_paused_server(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);
        $server->forceFill(['enabled' => false])->save();

        // A probe would need the transport; if the job ran, this would try to
        // reach 10.0.0.9. Instead it must return without touching anything, so
        // no snapshot appears.
        (new CollectServerFacts($server->id))->handle(app(ServerMonitor::class));

        $this->assertSame(0, ServerFact::query()->where('server_id', $server->id)->count());
    }
}
