<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\PaperlessTerm;
use App\Models\UserSetting;
use App\Rules\SafeUrl;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessSync;
use App\Support\KeepBlankSecrets;
use App\Support\OutboundUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-user Paperless-ngx integration: each user connects their own instance URL
 * + API token (stored encrypted on their user_settings row), with a connection
 * test and an on-demand cache refresh scoped to that user.
 */
class PaperlessController extends Controller
{
    use RedirectsToSettings;

    public function edit(Request $request): View
    {
        $user = $this->requireUser($request);

        return view('settings.paperless.edit', [
            'settings' => UserSetting::for($user->id),
            'counts' => $this->counts($user->id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'paperless_enabled' => ['sometimes', 'boolean'],
            'paperless_url' => ['nullable', 'url', 'max:255', new SafeUrl],
            'paperless_token' => ['nullable', 'string', 'max:255'],
        ], [], [
            'paperless_url' => __('settings.paperless_url'),
            'paperless_token' => __('settings.paperless_token'),
        ]);

        $user = $this->requireUser($request);
        $settings = UserSetting::for($user->id);
        $validated = [
            'paperless_url' => $request->input('paperless_url'),
            'paperless_token' => $request->string('paperless_token')->value(),
        ];
        // An empty token field keeps the stored one (so it need not be retyped).
        $validated = KeepBlankSecrets::preserve($validated, ['paperless_token']);
        $validated['paperless_enabled'] = $request->boolean('paperless_enabled');
        $settings->update($validated);

        return $this->savedRedirect('settings.paperless.edit', 'flash.paperless_settings_saved');
    }

    /** Test the connection using the posted URL + token (falling back to stored). */
    public function test(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $settings = UserSetting::for($user->id);
        $rawUrl = $request->input('paperless_url') ?: $settings->paperless_url;
        $rawToken = $request->input('paperless_token') ?: $settings->paperless_token;
        $url = trim(is_string($rawUrl) ? $rawUrl : '');
        $token = trim(is_string($rawToken) ? $rawToken : '');

        if ($url === '' || $token === '') {
            return response()->json(['ok' => false, 'detail' => __('settings.paperless_test_missing')]);
        }

        // Guard the raw posted URL before any request is issued.
        if (! OutboundUrl::safe($url)) {
            return response()->json(['ok' => false, 'detail' => __('settings.safe_url', ['attribute' => __('settings.paperless_url')])]);
        }

        try {
            $client = new PaperlessClient($url, $token);
            $client->ping();
            $tags = $client->count('tag');
            $types = $client->count('document_type');
            $correspondents = $client->count('correspondent');

            return response()->json([
                'ok' => true,
                'detail' => __('settings.paperless_test_ok_detail', [
                    'tags' => $tags,
                    'types' => $types,
                    'correspondents' => $correspondents,
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()]);
        }
    }

    /** Refresh the current user's cached terms now. */
    public function sync(Request $request, PaperlessSync $sync): JsonResponse
    {
        $user = $this->requireUser($request);
        try {
            $sync->run($user->id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'counts' => $this->counts($user->id)]);
    }

    /** @return array{tag:int, document_type:int, correspondent:int} */
    private function counts(int $userId): array
    {
        $by = PaperlessTerm::query()->where('user_id', $userId)
            ->selectRaw('kind, count(*) as c')
            ->groupBy('kind')
            ->pluck('c', 'kind');

        $count = static function (mixed $value): int {
            return is_numeric($value) ? (int) $value : 0;
        };

        return [
            'tag' => $count($by['tag'] ?? 0),
            'document_type' => $count($by['document_type'] ?? 0),
            'correspondent' => $count($by['correspondent'] ?? 0),
        ];
    }
}
