<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user, non-secret application settings a mobile client needs but that are
 * NOT display preferences (those live at /preferences). Currently: the personal
 * file version cap. None of this is zero-knowledge content — it is per-user
 * configuration.
 */
class SettingsController extends Controller
{
    /** The caller's per-user settings. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    /** Update any subset; unspecified keys are left unchanged. */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'file_max_versions' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $setting = UserSetting::for($this->requireUser($request)->id);
        $update = [];

        if ($request->has('file_max_versions')) {
            $update['file_max_versions'] = $request->integer('file_max_versions');
        }

        if ($update !== []) {
            $setting->update($update);
        }

        return response()->json($this->payload($request));
    }

    /** @return array{file_max_versions: int} */
    private function payload(Request $request): array
    {
        $s = UserSetting::for($this->requireUser($request)->id);

        return [
            'file_max_versions' => (int) ($s->file_max_versions ?? 10),
        ];
    }
}
