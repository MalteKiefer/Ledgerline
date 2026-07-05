<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-user reminder preferences: which channels are pre-selected when the user
 * creates a to-do or event reminder. The transport for each channel is still
 * configured workspace-wide (admin notification settings); this only chooses the
 * user's personal defaults.
 */
class RemindersController extends Controller
{
    public const CHANNELS = ['desktop', 'ntfy', 'mail', 'webhook'];

    public function edit(Request $request): View
    {
        return view('settings.reminders.edit', [
            'channels' => UserSetting::for($request->user()->id)->reminder_channels ?? ['desktop'],
            'all' => self::CHANNELS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channels' => ['array'],
            'channels.*' => ['string'],
        ]);

        // Keep only known channels, in the canonical order (drops anything else).
        $chosen = array_values(array_intersect(self::CHANNELS, $data['channels'] ?? []));
        UserSetting::for($request->user()->id)->update(['reminder_channels' => $chosen]);

        return redirect()->route('settings.reminders.edit')->with('status', __('settings.reminders_saved'));
    }
}
