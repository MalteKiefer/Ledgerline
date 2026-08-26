<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Global per-user DISPLAY preferences: measurement units + 12/24h clock. These are
 * non-secret presentation choices (like the interface language) — the underlying
 * data stays zero-knowledge; only the unit/format it is shown in is chosen here.
 * Applied client-side across web (window.LLPrefs) and mobile (GET /me.preferences).
 */
class PreferencesController extends Controller
{
    /**
     * Columns the mail list can show. Every one is a field the row already
     * carries — no new query, no new extraction.
     *
     * `subject` is not in the picker: a message list without a subject is not a
     * list of messages. The checkbox and the star are controls rather than data
     * and are likewise not configurable.
     *
     * @var list<string>
     */
    public const MAIL_COLUMNS = [
        'from',        // sender name/address
        'to',          // recipients — the only useful identity in a Sent folder
        'snippet',     // first unquoted body line, as its own column so it can be off
        'labels',
        'folder',      // which mailbox folder, worth a column in a unified inbox
        'account',     // which account it arrived in
        'attachment',  // paperclip
        'security',    // encrypted/signed marker
        'answered',    // replied-to marker
        'spam',
        'size',
        'date',
    ];

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'distance' => ['sometimes', 'string', 'in:km,mi'],
            'elevation' => ['sometimes', 'string', 'in:m,ft'],
            'weight' => ['sometimes', 'string', 'in:kg,lb'],
            'temp' => ['sometimes', 'string', 'in:c,f'],
            'glucose' => ['sometimes', 'string', 'in:mgdl,mmoll'],
            'time_format' => ['sometimes', 'string', 'in:24h,12h'],
            // Empty string clears the override (follow the browser); otherwise a
            // valid IANA zone. date_format is a small preset list.
            'timezone' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', timezone_identifiers_list())],
            'date_format' => ['sometimes', 'string', 'in:system,dmy,dmy_dot,mdy,ymd'],
            // Mail display prefs: always-load remote images default + send signature.
            'mail_load_remote' => ['sometimes', 'boolean'],
            'mail_signature' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mail_avatars' => ['nullable', 'string', 'in:off,contacts,domain'],
            // Per-category push toggle: { "<category>": { "push": bool } }.
            'notifications' => ['sometimes', 'array'],
            'notifications.*.push' => ['sometimes', 'boolean'],
            // Mail list columns, in display order. An empty array is allowed and
            // means "back to the default set".
            'mail_columns' => ['sometimes', 'nullable', 'array', 'max:'.count(self::MAIL_COLUMNS)],
            'mail_columns.*' => ['string', 'in:'.implode(',', self::MAIL_COLUMNS)],
        ]);

        $map = [
            'distance' => 'unit_distance',
            'elevation' => 'unit_elevation',
            'weight' => 'unit_weight',
            'temp' => 'unit_temp',
            'glucose' => 'unit_glucose',
            'time_format' => 'time_format',
            'date_format' => 'date_format',
        ];
        $update = [];
        foreach ($map as $key => $column) {
            if ($request->has($key)) {
                $update[$column] = $request->string($key)->value();
            }
        }
        if ($request->has('mail_columns')) {
            $raw = $request->input('mail_columns');
            $picked = [];
            foreach (is_array($raw) ? $raw : [] as $key) {
                // Keep the given order, drop duplicates and anything unknown: a
                // column removed in a later release must not break the list, and
                // a client cannot poison the set.
                if (is_string($key) && in_array($key, self::MAIL_COLUMNS, true) && ! in_array($key, $picked, true)) {
                    $picked[] = $key;
                }
            }
            // Empty selection = null = "use the default set", not "show nothing".
            $update['mail_columns'] = $picked === [] ? null : $picked;
        }

        // Timezone: empty string clears the override (follow browser) → null.
        if ($request->has('timezone')) {
            $tz = trim($request->string('timezone')->value());
            $update['timezone'] = $tz !== '' ? $tz : null;
        }
        if ($request->has('mail_load_remote')) {
            $update['mail_load_remote'] = $request->boolean('mail_load_remote');
        }
        if ($request->has('mail_signature')) {
            $sig = $request->input('mail_signature');
            $update['mail_signature'] = is_string($sig) && trim($sig) !== '' ? $sig : null;
        }
        if ($request->has('mail_avatars')) {
            $data['mail_avatars'] = $request->string('mail_avatars')->value();
        }

        $setting = UserSetting::for($this->requireUser($request)->id);

        // Merge per-category push prefs so setting one category leaves the rest.
        if ($request->has('notifications')) {
            $prefs = is_array($setting->notification_prefs) ? $setting->notification_prefs : [];
            foreach ((array) $request->input('notifications') as $category => $cfg) {
                if (! is_array($cfg) || ! array_key_exists('push', $cfg)) {
                    continue;
                }
                $entry = is_array($prefs[$category] ?? null) ? $prefs[$category] : [];
                $entry['push'] = (bool) $cfg['push'];
                $prefs[(string) $category] = $entry;
            }
            $update['notification_prefs'] = $prefs;
        }

        if ($update !== []) {
            $setting->update($update);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'preferences' => $setting->displayPrefs()])
            : back();
    }
}
