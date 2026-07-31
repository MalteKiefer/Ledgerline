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
        $user = $this->requireUser($request);
        $s = UserSetting::for($user->id);

        return view('settings.contacts.edit', [
            'channels' => self::CHANNELS,
            'birthday' => (array) ($s->contact_birthday_channels ?? []),
            'anniversary' => (array) ($s->contact_anniversary_channels ?? []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'birthday' => ['nullable', 'array'],
            'birthday.*' => [Rule::in(self::CHANNELS)],
            'anniversary' => ['nullable', 'array'],
            'anniversary.*' => [Rule::in(self::CHANNELS)],
        ]);

        $channels = static fn (string $key): array => $request->collect($key)
            ->map(fn (mixed $c) => is_scalar($c) ? (string) $c : '')
            ->unique()
            ->values()
            ->all();

        $user = $this->requireUser($request);
        UserSetting::for($user->id)->update([
            'contact_birthday_channels' => $channels('birthday'),
            'contact_anniversary_channels' => $channels('anniversary'),
        ]);

        return $this->savedRedirect('settings.contacts.edit', 'settings.contacts_saved');
    }
}
