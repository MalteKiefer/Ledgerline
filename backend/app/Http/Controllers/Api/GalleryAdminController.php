<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DetectGalleryFaces;
use App\Jobs\EmbedGalleryPhoto;
use App\Jobs\RefreshGalleryExif;
use App\Models\AppSettings;
use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Providers\AppServiceProvider;
use App\Rules\SafeUrl;
use App\Support\Vector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Site-settings Gallery & ML admin surface (can:manage-global-settings). Toggle
 * ML features, pick models, tune thresholds, watch the worker queue, clear/retry
 * it, and re-scan the whole library. Container restart/update is deliberately NOT
 * executed from the app (no docker socket — that would be a container-escape
 * vector); the operator runs those commands on the host.
 */
class GalleryAdminController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $s = AppSettings::current();

        return response()->json([
            'settings' => [
                // Stored overrides (null = inherit the env/config default).
                'ml_enabled' => $s->ml_enabled,
                'ml_face_enabled' => $s->ml_face_enabled,
                'ml_url' => $s->ml_url,
                'ml_clip_model' => $s->ml_clip_model,
                'ml_face_model' => $s->ml_face_model,
                'ml_search_distance' => $s->ml_search_distance,
                'ml_dup_distance' => $s->ml_dup_distance,
                'ml_face_min_score' => $s->ml_face_min_score,
                'ml_face_match_distance' => $s->ml_face_match_distance,
            ],
            'effective' => [
                'enabled' => (bool) config('ml.enabled'),
                'face_enabled' => (bool) config('ml.face_enabled'),
                'url' => $this->cfgStr('ml.url'),
                'clip_model' => $this->cfgStr('ml.clip_model'),
                'face_model' => $this->cfgStr('ml.face_model'),
                'search_max_distance' => $this->cfgFloat('ml.search_max_distance'),
                'dup_max_distance' => $this->cfgFloat('ml.dup_max_distance'),
                'face_min_score' => $this->cfgFloat('ml.face_min_score'),
                'face_match_distance' => $this->cfgFloat('ml.face_match_distance'),
                'vector' => Vector::available(),
            ],
            'status' => $this->status(),
            'operator' => $this->operatorCommands(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'ml_enabled' => ['sometimes', 'nullable', 'boolean'],
            'ml_face_enabled' => ['sometimes', 'nullable', 'boolean'],
            'ml_url' => ['sometimes', 'nullable', 'string', 'max:255', new SafeUrl],
            'ml_clip_model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ml_face_model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ml_search_distance' => ['sometimes', 'nullable', 'numeric', 'between:0,2'],
            'ml_dup_distance' => ['sometimes', 'nullable', 'numeric', 'between:0,2'],
            'ml_face_min_score' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'ml_face_match_distance' => ['sometimes', 'nullable', 'numeric', 'between:0,2'],
        ]);
        $bools = ['ml_enabled', 'ml_face_enabled'];
        $nums = ['ml_search_distance', 'ml_dup_distance', 'ml_face_min_score', 'ml_face_match_distance'];
        $strs = ['ml_url', 'ml_clip_model', 'ml_face_model'];
        $patch = [];
        foreach ($bools as $k) {
            if ($request->has($k)) {
                $patch[$k] = $request->input($k) === null ? null : $request->boolean($k);
            }
        }
        foreach ($nums as $k) {
            if ($request->has($k)) {
                $patch[$k] = $request->input($k) === null ? null : $request->float($k);
            }
        }
        foreach ($strs as $k) {
            if ($request->has($k)) {
                $patch[$k] = $request->filled($k) ? $request->string($k)->value() : null;
            }
        }
        $s = AppSettings::current();
        $s->fill($patch)->save();
        Cache::forget(AppServiceProvider::ML_CACHE_KEY);

        return response()->json(['ok' => true]);
    }

    /** Clear the pending backlog (does not kill the job already running). */
    public function clearQueue(Request $request): JsonResponse
    {
        $conn = is_string(config('queue.default')) ? config('queue.default') : 'redis';
        Artisan::call('queue:clear', [$conn, '--queue' => 'default']);

        return response()->json(['ok' => true, 'pending' => $this->queuePending()]);
    }

    public function retryFailed(Request $request): JsonResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return response()->json(['ok' => true]);
    }

    public function flushFailed(Request $request): JsonResponse
    {
        Artisan::call('queue:flush');

        return response()->json(['ok' => true]);
    }

    /** Re-queue ML processing across the whole library (site-wide). */
    public function reprocess(Request $request): JsonResponse
    {
        $scope = $request->string('scope')->lower()->value();
        $scope = in_array($scope, ['faces', 'embeddings', 'exif', 'all'], true) ? $scope : 'all';
        $doFaces = $scope === 'faces' || $scope === 'all';
        $doEmb = $scope === 'embeddings' || $scope === 'all';
        $doExif = $scope === 'exif' || $scope === 'all';
        $count = 0;
        GalleryPhoto::withoutGlobalScopes()->orderBy('id')
            ->chunkById(300, function ($photos) use (&$count, $doFaces, $doEmb, $doExif): void {
                foreach ($photos as $photo) {
                    if ($doExif) {
                        RefreshGalleryExif::dispatch($photo->id);
                    }
                    if ($doEmb) {
                        EmbedGalleryPhoto::dispatch($photo->id);
                    }
                    if ($doFaces) {
                        DetectGalleryFaces::dispatch($photo->id);
                    }
                    $count++;
                }
            });

        return response()->json(['ok' => true, 'queued' => $count, 'scope' => $scope]);
    }

    private function cfgStr(string $key): string
    {
        $v = config($key);

        return is_scalar($v) ? (string) $v : '';
    }

    private function cfgFloat(string $key): float
    {
        $v = config($key);

        return is_numeric($v) ? (float) $v : 0.0;
    }

    /** @return array<string,mixed> */
    private function status(): array
    {
        return [
            'sidecar' => $this->pingSidecar(),
            'queue' => ['pending' => $this->queuePending(), 'failed' => $this->failedCount()],
            'counts' => [
                'photos' => GalleryPhoto::withoutGlobalScopes()->count(),
                'videos' => GalleryPhoto::withoutGlobalScopes()->where('media_type', 'video')->count(),
                'embedded' => Vector::available() ? GalleryPhoto::withoutGlobalScopes()->whereNotNull('embedded_at')->count() : 0,
                'with_date' => GalleryPhoto::withoutGlobalScopes()->whereNotNull('taken_at')->count(),
                'located' => GalleryPhoto::withoutGlobalScopes()->whereNotNull('lat')->count(),
                'faces' => GalleryFace::withoutGlobalScopes()->count(),
                'people' => GalleryPerson::withoutGlobalScopes()->count(),
            ],
        ];
    }

    /** Ping the ML sidecar's /ping (reachable + which is deliberately not fatal). */
    private function pingSidecar(): string
    {
        if (! config('ml.enabled')) {
            return 'disabled';
        }
        $url = is_string(config('ml.url')) ? rtrim(config('ml.url'), '/') : '';
        if ($url === '') {
            return 'unconfigured';
        }
        try {
            $res = Http::connectTimeout(2)->timeout(3)->get($url.'/ping');

            return $res->successful() ? 'up' : 'down';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function queuePending(): int
    {
        try {
            return (int) Queue::size();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function failedCount(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string,string> */
    private function operatorCommands(): array
    {
        return [
            'restart' => 'docker compose --profile ml restart ml',
            'update' => 'docker compose --profile ml pull ml && docker compose --profile ml up -d ml',
            'logs' => 'docker compose --profile ml logs -f --tail=100 ml',
        ];
    }
}
