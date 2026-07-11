<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-user contact settings: which notification channels (if any) to use for
 * birthday and anniversary alerts. Contacts stay zero-knowledge — the client
 * detects a due date and relays a one-off message through the chosen channels;
 * the server never stores the contact data.
 */
class ContactsController extends Controller
{
    use RedirectsToSettings;

    private const CHANNELS = ['desktop', 'ntfy', 'mail', 'webhook'];

    public function edit(Request $request): View
    {
        $s = UserSetting::for($request->user()->id);

        return view('settings.contacts.edit', [
            'channels' => self::CHANNELS,
            'birthday' => (array) ($s->contact_birthday_channels ?? []),
            'anniversary' => (array) ($s->contact_anniversary_channels ?? []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'birthday' => ['nullable', 'array'],
            'birthday.*' => [Rule::in(self::CHANNELS)],
            'anniversary' => ['nullable', 'array'],
            'anniversary.*' => [Rule::in(self::CHANNELS)],
        ]);

        UserSetting::for($request->user()->id)->update([
            'contact_birthday_channels' => array_values(array_unique($data['birthday'] ?? [])),
            'contact_anniversary_channels' => array_values(array_unique($data['anniversary'] ?? [])),
        ]);

        return $this->savedRedirect('settings.contacts.edit', 'settings.contacts_saved');
    }
}
