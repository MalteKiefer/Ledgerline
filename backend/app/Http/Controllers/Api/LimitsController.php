<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Providers\AppServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable workspace limits: session/auth lifetimes, retention windows and
 * the Files quota. Each column overlays a config('...') key at boot
 * (AppServiceProvider::SETTING_OVERRIDES); NULL inherits the env/config default.
 */
class LimitsController extends Controller
{
    /** column => [config key, min, max]. */
    private const FIELDS = [
        'files_quota_mb' => ['files.quota_mb', 0, 100_000_000],
        'sanctum_expiration_minutes' => ['sanctum.expiration', 0, 5_256_000],
        'session_lifetime_minutes' => ['session.lifetime', 1, 525_600],
        'device_wipe_grace_minutes' => ['devices.wipe_grace_minutes', 0, 10_080],
        'device_idle_days' => ['devices.idle_days', 0, 3650],
        'audit_retention_days' => ['ops.audit_retention_days', 0, 3650],
        'access_log_retention_days' => ['ops.access_log_retention_days', 0, 3650],
        'request_log_retention_days' => ['ops.request_log_retention_days', 0, 3650],
        'backup_stale_hours' => ['ops.backup_stale_hours', 0, 8760],
        'mail_log_retention_days' => ['mail_archive.log_retention_days', 0, 3650],
        'mail_blob_orphan_grace_hours' => ['mail_archive.blob_orphan_grace_hours', 0, 8760],
    ];

    public function show(Request $request): JsonResponse
    {
        $s = AppSettings::current();
        $settings = [];
        $effective = [];
        foreach (self::FIELDS as $col => [$key]) {
            $settings[$col] = $s->{$col};
            $cfg = config($key);
            $effective[$col] = is_numeric($cfg) ? (int) $cfg : null;
        }

        return response()->json(['settings' => $settings, 'effective' => $effective]);
    }

    public function update(Request $request): JsonResponse
    {
        $rules = [];
        foreach (self::FIELDS as $col => [, $min, $max]) {
            $rules[$col] = ['sometimes', 'nullable', 'integer', "min:$min", "max:$max"];
        }
        $request->validate($rules);

        $s = AppSettings::current();
        $changes = [];
        $patch = [];
        foreach (array_keys(self::FIELDS) as $col) {
            if (! $request->has($col)) {
                continue;
            }
            $new = $request->input($col) === null ? null : $request->integer($col);
            if ($s->{$col} !== $new) {
                $changes[$col] = ['from' => $s->{$col}, 'to' => $new];
            }
            $patch[$col] = $new;
        }
        if ($patch !== []) {
            $s->fill($patch)->save();
            Cache::forget(AppServiceProvider::OVERRIDES_CACHE_KEY);
            if ($changes !== []) {
                AuditLog::record('settings.limits_changed', null, ['changes' => $changes]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
