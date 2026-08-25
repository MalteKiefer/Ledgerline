<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CollectServerFacts;
use App\Models\Server;
use App\Models\ServerFact;
use App\Models\User;
use App\Services\Servers\ProbeResult;
use App\Services\Servers\ServerProbe;
use App\Services\Servers\ServerTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The per-server alert thresholds over the API.
 *
 * The case worth guarding is the empty one: null is a real value here — it means
 * "fall back to the built-in default" — so a cleared field has to erase the
 * override rather than be treated as "unchanged" and silently keep it.
 *
 * The probe is faked: saving a server re-reads the host key, and these tests are
 * about the controller's rules, not about whether OpenSSH works.
 */
class ServerAlertThresholdApiTest extends TestCase
{
    use RefreshDatabase;

    private const FP = 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    private const HOST_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleKeyBlobForTesting0000000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ServerProbe::class, new class extends ServerProbe
        {
            public function __construct() {}

            public function run(ServerTarget $target, bool $interactive = false): ProbeResult
            {
                return new ProbeResult(true, fingerprint: ServerAlertThresholdApiTest::FP, hostKey: ServerAlertThresholdApiTest::HOST_KEY);
            }
        });
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    /** @param  array<string, mixed>  $columns */
    private function server(User $owner, array $columns = []): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => $owner->id,
            'name' => 'web01',
            'host' => '10.0.0.9',
            'port' => 22,
            'username' => 'monitor',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'PRIVATE-KEY-BODY', 'passphrase' => ''],
            'host_fingerprint' => self::FP,
            'host_key' => self::HOST_KEY,
            'enabled' => true,
            ...$columns,
        ])->save();

        return $server;
    }

    public function test_the_poll_interval_is_bounded_and_honoured_by_the_sweep(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        // Bounds: half a minute to half an hour. Below that it is a load
        // generator, above it the history is too coarse to read.
        $this->putJson(route('api.servers.update', $server), ['poll_interval_s' => 29], $this->bearer($user))
            ->assertStatus(422);
        $this->putJson(route('api.servers.update', $server), ['poll_interval_s' => 1801], $this->bearer($user))
            ->assertStatus(422);

        $this->putJson(route('api.servers.update', $server), ['poll_interval_s' => 60], $this->bearer($user))
            ->assertOk()->assertJsonPath('server.poll_interval_s', 60);

        // A snapshot taken just now: not due yet on a 60s interval...
        ServerFact::forceCreate([
            'server_id' => $server->id, 'ok' => true, 'facts' => [],
            'duration_ms' => 5, 'collected_at' => now(),
        ]);
        Queue::fake();
        $this->artisan('servers:poll')->assertSuccessful();
        Queue::assertNothingPushed();

        // ...but due once the interval has passed.
        ServerFact::query()->where('server_id', $server->id)->update(['collected_at' => now()->subSeconds(120)]);
        $this->artisan('servers:poll')->assertSuccessful();
        Queue::assertPushed(CollectServerFacts::class);
    }

    public function test_clearing_the_poll_interval_falls_back_to_the_default(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user, ['poll_interval_s' => 60]);

        $this->putJson(route('api.servers.update', $server), ['poll_interval_s' => null], $this->bearer($user))
            ->assertOk()->assertJsonPath('server.poll_interval_s', null);

        // Default is 300s: a snapshot from two minutes ago is not due yet.
        ServerFact::forceCreate([
            'server_id' => $server->id, 'ok' => true, 'facts' => [],
            'duration_ms' => 5, 'collected_at' => now()->subSeconds(120),
        ]);
        Queue::fake();
        $this->artisan('servers:poll')->assertSuccessful();
        Queue::assertNothingPushed();

        // --force is the manual "refresh now" path and ignores the interval.
        $this->artisan('servers:poll --force')->assertSuccessful();
        Queue::assertPushed(CollectServerFacts::class);
    }

    public function test_thresholds_can_be_set(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->putJson(route('api.servers.update', $server), [
            'disk_alert_pct' => 95,
            'mem_alert_pct' => 80,
            'temp_alert_c' => 70,
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('server.disk_alert_pct', 95)
            ->assertJsonPath('server.mem_alert_pct', 80)
            ->assertJsonPath('server.temp_alert_c', 70);

        $server->refresh();
        $this->assertSame(95, $server->disk_alert_pct);
        $this->assertSame(80, $server->mem_alert_pct);
        $this->assertSame(70, $server->temp_alert_c);
    }

    public function test_clearing_a_threshold_restores_the_default(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user, ['disk_alert_pct' => 95, 'mem_alert_pct' => 80, 'temp_alert_c' => 70]);

        $this->putJson(route('api.servers.update', $server), [
            'disk_alert_pct' => null,
            'mem_alert_pct' => null,
            'temp_alert_c' => null,
        ], $this->bearer($user))->assertOk();

        $server->refresh();
        $this->assertNull($server->disk_alert_pct);
        $this->assertNull($server->mem_alert_pct);
        $this->assertNull($server->temp_alert_c);
    }

    public function test_an_edit_that_does_not_mention_the_thresholds_keeps_them(): void
    {
        // Renaming a server must not quietly reset its alerting.
        $user = User::factory()->create();
        $server = $this->server($user, ['disk_alert_pct' => 95]);

        $this->putJson(route('api.servers.update', $server), ['name' => 'renamed'], $this->bearer($user))->assertOk();

        $server->refresh();
        $this->assertSame('renamed', $server->name);
        $this->assertSame(95, $server->disk_alert_pct);
    }

    public function test_a_disk_threshold_outside_the_allowed_range_is_rejected(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->putJson(route('api.servers.update', $server), ['disk_alert_pct' => 30], $this->bearer($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors('disk_alert_pct');
    }

    public function test_a_temperature_threshold_outside_the_allowed_range_is_rejected(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->putJson(route('api.servers.update', $server), ['temp_alert_c' => 500], $this->bearer($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors('temp_alert_c');
    }

    public function test_a_non_numeric_threshold_is_rejected(): void
    {
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->putJson(route('api.servers.update', $server), ['mem_alert_pct' => 'high'], $this->bearer($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mem_alert_pct');
    }

    public function test_the_detail_view_carries_a_forecast(): void
    {
        // Computed server-side: the client only ever holds a window of the
        // history, and the slope needs all of it.
        $user = User::factory()->create();
        $server = $this->server($user);

        $this->getJson(route('api.servers.show', $server), $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('forecast.ready', false)
            ->assertJsonStructure(['forecast' => ['ready', 'hours_of_history', 'samples', 'disks']]);
    }
}
