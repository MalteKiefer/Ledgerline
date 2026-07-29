<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-user, non-secret application settings a mobile client needs but that are
 * NOT display preferences (those live at /preferences). Currently: the contact
 * birthday/anniversary notification channels (on the ZK critical path — the
 * client detects a due date and relays via POST /contacts/notify, which the
 * server intersects with these channels) and the personal file version cap.
 * None of this is zero-knowledge content — it is per-user configuration.
 */
class SettingsController extends Controller
{
    private const CHANNELS = ['desktop', 'ntfy', 'mail', 'webhook'];

    /** The caller's per-user settings. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    /** Update any subset; unspecified keys are left unchanged. */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'contact_birthday_channels' => ['sometimes', 'array'],
            'contact_birthday_channels.*' => [Rule::in(self::CHANNELS)],
            'contact_anniversary_channels' => ['sometimes', 'array'],
            'contact_anniversary_channels.*' => [Rule::in(self::CHANNELS)],
            'file_max_versions' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $setting = UserSetting::for($this->requireUser($request)->id);
        $update = [];

        $channels = static fn (string $key): array => $request->collect($key)
            ->map(fn (mixed $c) => is_scalar($c) ? (string) $c : '')
            ->unique()->values()->all();

        if ($request->has('contact_birthday_channels')) {
            $update['contact_birthday_channels'] = $channels('contact_birthday_channels');
        }
        if ($request->has('contact_anniversary_channels')) {
            $update['contact_anniversary_channels'] = $channels('contact_anniversary_channels');
        }
        if ($request->has('file_max_versions')) {
            $update['file_max_versions'] = $request->integer('file_max_versions');
        }

        if ($update !== []) {
            $setting->update($update);
        }

        return response()->json($this->payload($request));
    }

    /** @return array{contact_birthday_channels: list<string>, contact_anniversary_channels: list<string>, file_max_versions: int} */
    private function payload(Request $request): array
    {
        $s = UserSetting::for($this->requireUser($request)->id);

        return [
            'contact_birthday_channels' => array_values((array) ($s->contact_birthday_channels ?? [])),
            'contact_anniversary_channels' => array_values((array) ($s->contact_anniversary_channels ?? [])),
            'file_max_versions' => (int) ($s->file_max_versions ?? 10),
        ];
    }
}
