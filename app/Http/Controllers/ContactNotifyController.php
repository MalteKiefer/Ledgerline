<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserSetting;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One-off relay for contact birthday / anniversary alerts. Contacts are
 * zero-knowledge, so the SERVER can't know whose birthday it is — the CLIENT
 * detects the due date (it holds the decrypted data) and posts a ready-made
 * message here, which is forwarded to the user's chosen channels and never
 * stored. The requested channels are intersected with the user's saved
 * preference for that kind, so a message can only go where the user allowed.
 */
class ContactNotifyController extends Controller
{
    public function send(Request $request, ChannelNotifier $channels): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['birthday', 'anniversary'])],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $s = UserSetting::for($user->id);
        $allowed = (array) ($data['kind'] === 'birthday'
            ? $s->contact_birthday_channels
            : $s->contact_anniversary_channels);

        // Only the channels the user enabled for this kind — never more.
        $targets = array_values(array_intersect($allowed, ['desktop', 'ntfy', 'mail', 'webhook']));
        if ($targets === []) {
            return response()->json(['sent' => false]);
        }

        $channels->send($targets, $data['title'], $data['body'], [
            'user_id' => $user->id,
            'event' => 'reminder',
            'category' => 'reminder',
        ]);

        return response()->json(['sent' => true]);
    }
}
