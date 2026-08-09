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
 * Admin-gated workspace Files limits: the two AppSettings columns that overlay
 * config/files.php (max upload size + blob-orphan grace window — see
 * AppServiceProvider::SETTING_OVERRIDES). Personal per-user file_max_versions is
 * NOT here (that lives at PUT /api/v1/settings). Admin-gated at the route level.
 */
class FilesLimitsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'files_max_upload_mb' => ['sometimes', 'integer', 'min:1', 'max:1048576'],
            'files_blob_orphan_grace_hours' => ['sometimes', 'integer', 'min:0', 'max:8760'],
        ]);

        $settings = AppSettings::current();
        $data = [];
        if ($request->has('files_max_upload_mb')) {
            $data['files_max_upload_mb'] = $request->integer('files_max_upload_mb');
        }
        if ($request->has('files_blob_orphan_grace_hours')) {
            $data['files_blob_orphan_grace_hours'] = $request->integer('files_blob_orphan_grace_hours');
        }

        if ($data !== []) {
            $settings->update($data);
            // These columns overlay config via a cached lookup; clear it so the
            // new limits take effect on the next request (mirrors the intent in
            // AppServiceProvider::applySettingOverrides).
            Cache::forget(AppServiceProvider::OVERRIDES_CACHE_KEY);
            AuditLog::record('settings.updated', null, ['group' => 'files']);
        }

        return response()->json($this->payload());
    }

    /**
     * Effective limits: the admin override on AppSettings if set, else the
     * config/files.php default.
     *
     * @return array{files_max_upload_mb: int, files_blob_orphan_grace_hours: int}
     */
    private function payload(): array
    {
        $s = AppSettings::current();
        $defaultUpload = config('files.max_upload_mb', 512);
        $defaultGrace = config('files.blob_orphan_grace_hours', 24);

        return [
            'files_max_upload_mb' => $s->files_max_upload_mb
                ?: (is_numeric($defaultUpload) ? (int) $defaultUpload : 512),
            'files_blob_orphan_grace_hours' => $s->files_blob_orphan_grace_hours
                ?? (is_numeric($defaultGrace) ? (int) $defaultGrace : 24),
        ];
    }
}
