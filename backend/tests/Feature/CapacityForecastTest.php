<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerFact;
use App\Models\User;
use App\Services\Servers\CapacityForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "Days until this filesystem is full" is only worth printing when the data
 * supports it. These tests are almost entirely about the cases where it does
 * not: too little history, a flat series, a shrinking one, and one that jumps
 * around. Inventing a date out of noise is worse than saying nothing, because a
 * date is read as a fact and a silence is not.
 */
class CapacityForecastTest extends TestCase
{
    use RefreshDatabase;

    private function server(): Server
    {
        $server = new Server;
        $server->forceFill([
            'user_id' => User::factory()->create()->id,
            'name' => 'web01',
            'host' => '10.0.0.9',
            'port' => 22,
            'username' => 'monitor',
            'auth_type' => 'key',
            'credentials' => ['private_key' => 'k'],
            'host_fingerprint' => 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'enabled' => true,
        ])->save();

        return $server;
    }

    /**
     * ServerFact has an empty $fillable (it is written only by the collector),
     * so fixtures go through forceFill, and collected_at has to be explicit —
     * the model carries no timestamps of its own.
     *
     * @param  array<string, mixed>  $facts
     */
    private function fact(Server $server, Carbon $at, array $facts, bool $ok = true): void
    {
        (new ServerFact)->forceFill([
            'server_id' => $server->id,
            'ok' => $ok,
            'error' => $ok ? null : 'auth_failed',
            'facts' => $facts,
            'duration_ms' => 12,
            'collected_at' => $at,
        ])->save();
    }

    /**
     * A run of samples ending now, spaced evenly, one filesystem.
     *
     * @param  list<float>  $percentages
     */
    private function diskSeries(Server $server, array $percentages, float $hoursApart = 6.0, string $mount = '/'): void
    {
        $count = count($percentages);
        foreach ($percentages as $i => $pct) {
            $this->fact($server, Carbon::now()->subMinutes((int) round(($count - 1 - $i) * $hoursApart * 60)), [
                'disks' => [['mount' => $mount, 'used_pct' => $pct]],
            ]);
        }
    }

    /** @return list<float> */
    private function ramp(float $from, float $to, int $points): array
    {
        $step = ($to - $from) / ($points - 1);

        return array_map(static fn (int $i): float => $from + ($step * $i), range(0, $points - 1));
    }

    /**
     * @param  array<string, mixed>  $forecast
     * @return array<string, mixed>|null
     */
    private function disk(array $forecast, string $mount = '/'): ?array
    {
        foreach ($forecast['disks'] as $row) {
            if ($row['mount'] === $mount) {
                return $row;
            }
        }

        return null;
    }

    public function test_two_samples_over_an_hour_are_not_enough_to_forecast(): void
    {
        // The important one. Two points always describe a perfect line, and an
        // hour of history says nothing about a week — quoting a date from that
        // would be arithmetic dressed up as a prediction.
        $server = $this->server();
        $this->diskSeries($server, [60.0, 62.0], hoursApart: 1.0);

        $forecast = (new CapacityForecast)->forServer($server);

        $this->assertFalse($forecast['ready']);
        $this->assertSame(2, $forecast['samples']);
    }

    public function test_no_history_at_all_is_not_ready(): void
    {
        $forecast = (new CapacityForecast)->forServer($this->server());

        $this->assertFalse($forecast['ready']);
        $this->assertSame(0, $forecast['samples']);
        $this->assertSame([], $forecast['disks']);
        $this->assertNull($forecast['memory']);
    }

    public function test_a_steady_climb_produces_a_date(): void
    {
        // 60% to 80% across four days: five points a day of growth, twenty
        // points of headroom left, so four days.
        $server = $this->server();
        $this->diskSeries($server, $this->ramp(60.0, 80.0, 17));

        $forecast = (new CapacityForecast)->forServer($server);
        $disk = $this->disk($forecast);

        $this->assertTrue($forecast['ready']);
        $this->assertNotNull($disk);
        $this->assertEqualsWithDelta(5.0, $disk['per_day'], 0.1);
        $this->assertNotNull($disk['days_to_full']);
        $this->assertEqualsWithDelta(4.0, $disk['days_to_full'], 0.2);
    }

    public function test_a_filesystem_parked_at_ninety_one_percent_gets_no_date(): void
    {
        // The case the plain percentage ranks wrongly: full, but not moving, and
        // therefore not the thing to wake anybody for.
        $server = $this->server();
        $this->diskSeries($server, array_fill(0, 17, 91.0));

        $disk = $this->disk((new CapacityForecast)->forServer($server));

        $this->assertNotNull($disk);
        $this->assertSame(91.0, $disk['used_pct']);
        $this->assertEqualsWithDelta(0.0, $disk['per_day'], 0.01);
        $this->assertNull($disk['days_to_full']);
    }

    public function test_a_shrinking_filesystem_gets_no_date(): void
    {
        $server = $this->server();
        $this->diskSeries($server, $this->ramp(80.0, 60.0, 17));

        $disk = $this->disk((new CapacityForecast)->forServer($server));

        $this->assertNotNull($disk);
        $this->assertLessThan(0.0, $disk['per_day']);
        $this->assertNull($disk['days_to_full']);
    }

    public function test_a_noisy_series_is_refused_on_the_fit_even_though_it_trends_up(): void
    {
        // Builds and log rotation swing this filesystem between 40 and 90. There
        // is a faint upward drift underneath, so the slope alone would happily
        // quote a date; the fit is what stops it.
        $server = $this->server();
        $values = array_map(
            static fn (int $i): float => ($i % 2 === 0 ? 40.0 : 90.0) + ($i * 0.3),
            range(0, 16),
        );
        $this->diskSeries($server, $values);

        $disk = $this->disk((new CapacityForecast)->forServer($server));

        $this->assertNotNull($disk);
        $this->assertGreaterThan(0.01, $disk['per_day']);
        $this->assertLessThan(0.5, $disk['fit']);
        $this->assertNull($disk['days_to_full']);
    }

    public function test_a_filesystem_that_appears_midway_does_not_disturb_the_others(): void
    {
        // The series are kept per mount; a disk mounted yesterday must not drag
        // the root filesystem's slope with it.
        $reference = $this->server();
        $this->diskSeries($reference, $this->ramp(60.0, 80.0, 17));

        $server = $this->server();
        $rows = $this->ramp(60.0, 80.0, 17);
        $count = count($rows);
        foreach ($rows as $i => $pct) {
            $disks = [['mount' => '/', 'used_pct' => $pct]];
            // A second filesystem shows up for the last four samples only, and
            // at a wildly different level.
            if ($i >= $count - 4) {
                $disks[] = ['mount' => '/data', 'used_pct' => 10.0 + $i];
            }
            $this->fact($server, Carbon::now()->subMinutes((int) round(($count - 1 - $i) * 360)), ['disks' => $disks]);
        }

        $forecast = (new CapacityForecast)->forServer($server);

        $this->assertEquals(
            $this->disk((new CapacityForecast)->forServer($reference)),
            $this->disk($forecast),
        );
        $this->assertNotNull($this->disk($forecast, '/data'));
    }

    public function test_failed_runs_are_left_out_of_the_series(): void
    {
        // A failed probe records the reason, not a snapshot. If one ever leaked
        // into the regression it would drag the slope somewhere invented.
        $server = $this->server();
        $rows = $this->ramp(60.0, 80.0, 17);
        $count = count($rows);
        foreach ($rows as $i => $pct) {
            $at = Carbon::now()->subMinutes((int) round(($count - 1 - $i) * 360));
            $this->fact($server, $at, ['disks' => [['mount' => '/', 'used_pct' => $pct]]]);
            $this->fact($server, $at->copy()->addMinutes(5), ['disks' => [['mount' => '/', 'used_pct' => 5.0]]], ok: false);
        }

        $forecast = (new CapacityForecast)->forServer($server);
        $disk = $this->disk($forecast);

        $this->assertSame(17, $forecast['samples']);
        $this->assertNotNull($disk);
        $this->assertEqualsWithDelta(5.0, $disk['per_day'], 0.1);
        $this->assertEqualsWithDelta(4.0, $disk['days_to_full'], 0.2);
    }

    public function test_samples_outside_the_window_are_ignored(): void
    {
        $server = $this->server();
        $this->fact($server, Carbon::now()->subDays(40), ['disks' => [['mount' => '/', 'used_pct' => 5.0]]]);
        $this->diskSeries($server, $this->ramp(60.0, 80.0, 17));

        $forecast = (new CapacityForecast)->forServer($server);

        $this->assertSame(17, $forecast['samples']);
        // Ninety-six hours of real history, not the forty days the stale row
        // would have implied.
        $this->assertGreaterThan(90.0, $forecast['hours_of_history']);
        $this->assertLessThan(100.0, $forecast['hours_of_history']);
    }

    public function test_memory_is_projected_from_its_own_series(): void
    {
        $server = $this->server();
        $rows = $this->ramp(50.0, 70.0, 17);
        $count = count($rows);
        foreach ($rows as $i => $pct) {
            $this->fact($server, Carbon::now()->subMinutes((int) round(($count - 1 - $i) * 360)), [
                'mem' => ['used_pct' => $pct],
            ]);
        }

        $forecast = (new CapacityForecast)->forServer($server);

        $this->assertNotNull($forecast['memory']);
        $this->assertEqualsWithDelta(5.0, $forecast['memory']['per_day'], 0.1);
        $this->assertSame([], $forecast['disks']);
    }
}
