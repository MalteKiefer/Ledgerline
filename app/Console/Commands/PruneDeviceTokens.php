<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DeviceAudit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revoke device bearer tokens that have gone idle past the configured window, that
 * were flagged for a remote wipe more than the grace window ago (a backstop for a
 * client that never comes back to be revoked at request time), or that have passed
 * their absolute expiry. Every token deleted here writes exactly one audit entry
 * with its reason, so a device never vanishes silently — each row is selected,
 * audited, then deleted (no blind mass DELETE).
 */
class PruneDeviceTokens extends Command
{
    protected $signature = 'devices:prune-tokens';

    protected $description = 'Revoke idle, wipe-flagged and expired device tokens (audited)';

    public function handle(): int
    {
        $idleCfg = config('devices.idle_days', 0);
        $idleDays = is_numeric($idleCfg) ? (int) $idleCfg : 0;
        $graceCfg = config('devices.wipe_grace_minutes', 15);
        $graceMin = is_numeric($graceCfg) ? (int) $graceCfg : 15;

        $idle = 0;
        if ($idleDays > 0) {
            // A token that was paired but never used has last_used_at = NULL — fall
            // back to created_at so a leaked, never-touched token still dies.
            $cutoff = now()->subDays($idleDays);
            $idle = $this->auditThenDelete(
                PersonalAccessToken::query()->where(function ($q) use ($cutoff): void {
                    $q->where('last_used_at', '<', $cutoff)
                        ->orWhere(fn ($q2) => $q2->whereNull('last_used_at')->where('created_at', '<', $cutoff));
                }),
                'device.idle_pruned',
                ['idle_days' => $idleDays],
            );
        }

        $wiped = $this->auditThenDelete(
            PersonalAccessToken::query()
                ->whereNotNull('wipe_requested_at')
                ->where('wipe_requested_at', '<', now()->subMinutes($graceMin)),
            'device.wipe_finalized',
            ['grace_minutes' => $graceMin],
        );

        // Absolute-expiry sweep: Sanctum stops accepting an expired token but never
        // deletes it, so it would 401 forever with no audit. Delete + audit any that
        // are past expires_at (set explicitly at pairing time).
        $expired = $this->auditThenDelete(
            PersonalAccessToken::query()->whereNotNull('expires_at')->where('expires_at', '<', now()),
            'device.expired',
            [],
        );

        $this->info("Pruned {$idle} idle + {$wiped} wiped + {$expired} expired device token(s).");

        return self::SUCCESS;
    }

    /**
     * Select the matching tokens, write one audit entry per token (with its reason
     * + the given extra meta), then delete them. Returns the count deleted.
     *
     * @param  Builder<PersonalAccessToken>  $query
     * @param  array<string, mixed>  $extra
     */
    private function auditThenDelete($query, string $action, array $extra): int
    {
        $count = 0;
        // Chunk so a large backlog never loads every row into memory at once.
        $query->clone()->orderBy('id')->chunkById(500, function ($tokens) use ($action, $extra, &$count): void {
            foreach ($tokens as $token) {
                $meta = $extra;
                if ($action === 'device.wipe_finalized' && $token->wipe_requested_at !== null) {
                    // Custom column — Sanctum doesn't cast it, so it's a raw string.
                    $meta['wipe_requested_at'] = Carbon::parse((string) $token->wipe_requested_at)->toIso8601String();
                }
                DeviceAudit::record($token, $action, $meta);
                $token->delete();
                $count++;
            }
        });

        return $count;
    }
}
