<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\Models\Server;
use App\Models\ServerFact;
use Illuminate\Support\Carbon;

/**
 * How long until a filesystem runs out.
 *
 * The snapshot answers "how full is it"; this answers "when does it matter",
 * which is the more useful question and the one the data already supports. A
 * disk that has sat at 91% for months is not the emergency; one at 62% climbing
 * two points a day is, and the plain figure ranks them the wrong way round.
 *
 * Straight least-squares over the samples we already keep. Deliberately not
 * anything cleverer: growth on a real machine is lumpy, and a model that
 * pretends otherwise would produce confident numbers nobody should believe.
 */
final class CapacityForecast
{
    /** Below this much history any slope is noise dressed up as a trend. */
    private const MIN_HOURS = 12.0;

    /** Fewer points than this and one outlier decides the answer. */
    private const MIN_SAMPLES = 8;

    /**
     * How well the line has to fit before we are willing to quote a date.
     *
     * A filesystem that jumps around with builds and log rotation has no
     * meaningful slope, and saying "full in 3 days" from a poor fit is worse
     * than saying nothing.
     */
    private const MIN_FIT = 0.5;

    /** Beyond this the answer is "not any time soon", not a date. */
    private const MAX_DAYS = 365;

    /**
     * Per-filesystem and memory projections for one server.
     *
     * @return array{ready:bool,hours_of_history:float,samples:int,disks:list<array{mount:string,used_pct:float,per_day:float,days_to_full:float|null,fit:float}>,memory:array{used_pct:float,per_day:float,days_to_full:float|null,fit:float}|null}
     */
    public function forServer(Server $server, int $days = 14): array
    {
        /** @var list<ServerFact> $rows */
        $rows = ServerFact::query()
            ->where('server_id', $server->id)
            ->where('ok', true)
            ->where('collected_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('collected_at')
            ->get()
            ->all();

        if (count($rows) < 2) {
            return ['ready' => false, 'hours_of_history' => 0.0, 'samples' => count($rows), 'disks' => [], 'memory' => null];
        }

        $first = $rows[0]->collected_at;
        $last = $rows[count($rows) - 1]->collected_at;
        $hours = abs($last->diffInMinutes($first)) / 60;
        $ready = $hours >= self::MIN_HOURS && count($rows) >= self::MIN_SAMPLES;

        // Series keyed by mount, so a filesystem that appeared or disappeared
        // mid-window does not shift the others.
        /** @var array<string,list<array{0:float,1:float}>> $byMount */
        $byMount = [];
        /** @var list<array{0:float,1:float}> $memory */
        $memory = [];

        foreach ($rows as $row) {
            $t = (float) $row->collected_at->getTimestamp();
            $facts = $row->facts ?? [];

            $disks = is_array($facts['disks'] ?? null) ? $facts['disks'] : [];
            foreach ($disks as $d) {
                if (! is_array($d) || ! is_string($d['mount'] ?? null) || ! is_numeric($d['used_pct'] ?? null)) {
                    continue;
                }
                $byMount[$d['mount']][] = [$t, (float) $d['used_pct']];
            }

            $mem = is_array($facts['mem'] ?? null) ? $facts['mem'] : [];
            if (is_numeric($mem['used_pct'] ?? null)) {
                $memory[] = [$t, (float) $mem['used_pct']];
            }
        }

        $diskOut = [];
        foreach ($byMount as $mount => $series) {
            $projection = $this->project($series);
            if ($projection === null) {
                continue;
            }
            $diskOut[] = ['mount' => $mount] + $projection;
        }

        // Fullest first: that is the order somebody scanning the list wants,
        // and it matches how the filesystems are shown elsewhere.
        usort($diskOut, static fn (array $a, array $b): int => $b['used_pct'] <=> $a['used_pct']);

        return [
            'ready' => $ready,
            'hours_of_history' => round($hours, 1),
            'samples' => count($rows),
            'disks' => $diskOut,
            'memory' => $memory === [] ? null : $this->project($memory),
        ];
    }

    /**
     * Least squares over (timestamp, percent), expressed per day.
     *
     * @param  list<array{0:float,1:float}>  $series
     * @return array{used_pct:float,per_day:float,days_to_full:float|null,fit:float}|null
     */
    private function project(array $series): ?array
    {
        $n = count($series);
        if ($n < 1) {
            return null;
        }

        // Per series, not just overall: a drive plugged in an hour ago has a
        // handful of samples of its own however long the rest of the history
        // runs, and a slope through four points is a line through noise.
        //
        // The filesystem still appears, though - dropping it would read as
        // "gone" rather than "too soon to say". It simply carries no
        // projection.
        if ($n < self::MIN_SAMPLES) {
            return [
                'used_pct' => round($series[$n - 1][1], 1),
                'per_day' => 0.0,
                'days_to_full' => null,
                'fit' => 0.0,
            ];
        }

        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($series as [$x, $y]) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0.0) {
            return null;
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // R²: how much of the movement the line actually accounts for.
        $meanY = $sumY / $n;
        $ssTot = $ssRes = 0.0;
        foreach ($series as [$x, $y]) {
            $predicted = ($slope * $x) + $intercept;
            $ssTot += ($y - $meanY) ** 2;
            $ssRes += ($y - $predicted) ** 2;
        }
        $fit = $ssTot > 0.0 ? max(0.0, 1 - ($ssRes / $ssTot)) : 1.0;

        $current = $series[$n - 1][1];
        $perDay = $slope * 86400;

        $daysToFull = null;
        // Only project upward movement that the line actually explains. A
        // filesystem that is shrinking, flat, or merely noisy gets no date.
        if ($perDay > 0.01 && $fit >= self::MIN_FIT && $current < 100.0) {
            $days = (100.0 - $current) / $perDay;
            $daysToFull = $days <= self::MAX_DAYS ? round($days, 1) : null;
        }

        return [
            'used_pct' => round($current, 1),
            'per_day' => round($perDay, 2),
            'days_to_full' => $daysToFull,
            'fit' => round($fit, 2),
        ];
    }
}
