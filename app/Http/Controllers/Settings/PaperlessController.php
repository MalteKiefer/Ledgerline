<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\PaperlessTerm;
use App\Rules\SafeUrl;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessSync;
use App\Support\OutboundUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Paperless-ngx integration settings: the instance URL + API token, plus a
 * connection test and an on-demand cache refresh. Credentials are stored
 * encrypted on the settings row (like the backup destinations).
 */
class PaperlessController extends Controller
{
    public function edit(): View
    {
        return view('settings.paperless.edit', [
            'settings' => AppSettings::current(),
            'counts' => $this->counts(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paperless_enabled' => ['sometimes', 'boolean'],
            'paperless_url' => ['nullable', 'url', 'max:255', new SafeUrl],
            'paperless_token' => ['nullable', 'string', 'max:255'],
        ], [], [
            'paperless_url' => __('settings.paperless_url'),
            'paperless_token' => __('settings.paperless_token'),
        ]);

        $settings = AppSettings::current();
        // An empty token field keeps the stored one (so it need not be retyped).
        if (empty($validated['paperless_token'])) {
            unset($validated['paperless_token']);
        }
        $validated['paperless_enabled'] = $request->boolean('paperless_enabled');
        $settings->update($validated);

        return redirect()->route('settings.paperless.edit')->with('status', __('flash.paperless_settings_saved'));
    }

    /** Test the connection using the posted URL + token (falling back to stored). */
    public function test(Request $request): JsonResponse
    {
        $settings = AppSettings::current();
        $url = trim((string) ($request->input('paperless_url') ?: $settings->paperless_url));
        $token = trim((string) ($request->input('paperless_token') ?: $settings->paperless_token));

        if ($url === '' || $token === '') {
            return response()->json(['ok' => false, 'detail' => __('settings.paperless_test_missing')]);
        }

        // Guard the raw posted URL before any request is issued (it may not have
        // been persisted yet, so the update() rule has not necessarily run).
        if (! OutboundUrl::safe($url)) {
            return response()->json(['ok' => false, 'detail' => __('settings.safe_url', ['attribute' => __('settings.paperless_url')])]);
        }

        try {
            // A real end-to-end check: authenticate, then confirm read access on
            // each collection the transfer modal relies on, reporting the counts.
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

    /** Refresh the cached terms now. */
    public function sync(PaperlessSync $sync): JsonResponse
    {
        try {
            $counts = $sync->run();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'counts' => $this->counts()]);
    }

    /** @return array{tag:int, document_type:int, correspondent:int} */
    private function counts(): array
    {
        $by = PaperlessTerm::query()
            ->selectRaw('kind, count(*) as c')
            ->groupBy('kind')
            ->pluck('c', 'kind');

        return [
            'tag' => (int) ($by['tag'] ?? 0),
            'document_type' => (int) ($by['document_type'] ?? 0),
            'correspondent' => (int) ($by['correspondent'] ?? 0),
        ];
    }
}
