<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\InviteLink;
use App\Models\User;
use App\Notifications\InviteLinkNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Mail-independent invite / password-reset links. An admin generates a link (chosen
 * validity) that can be copied and handed over directly, or emailed. Consuming the
 * link lets the user set a password — guarded by a hashed, single-use, expiring token.
 */
class InviteLinkController extends Controller
{
    /** Admin generates a link for a user (copy-paste, and optionally email it). */
    public function create(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'ttl_hours' => ['required', 'integer', 'in:'.implode(',', InviteLink::TTL_HOURS)],
            'send' => ['nullable', 'boolean'],
        ]);

        $token = InviteLink::newToken();
        $link = new InviteLink;
        $link->forceFill([
            'user_id' => $user->id,
            'token_hash' => InviteLink::hashToken($token),
            'expires_at' => now()->addHours($request->integer('ttl_hours')),
            'created_by' => $this->requireUser($request)->id,
        ])->save();

        $url = route('invite.show', ['invite' => $link->id, 'token' => $token]);

        AuditLog::record('user.invite_link_created', null, [
            'user_id' => $user->id,
            'ttl_hours' => $request->integer('ttl_hours'),
            'emailed' => $request->boolean('send') && AppSettings::current()->mail_enabled,
        ]);

        $sent = false;
        if ($request->boolean('send') && AppSettings::current()->mail_enabled) {
            $user->notify(new InviteLinkNotification($url, $link->expires_at));
            $sent = true;
        }

        // The plaintext URL is shown once so the admin can copy it. It is never
        // stored (only the token hash is) and never logged.
        return back()->with('invite_url', $url)->with('invite_sent', $sent);
    }

    /** Public: show the set-password form if the token is valid. */
    public function show(Request $request, InviteLink $invite, string $token): View|RedirectResponse
    {
        $user = $invite->user;
        if ($user === null || ! $invite->matches($token) || ! $invite->isValid()) {
            return redirect()->route('login')->withErrors(['email' => __('auth_ui.invite_invalid')]);
        }

        return view('auth.invite', [
            'invite' => $invite,
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    /** Public: consume the link — set the password, sign the user in. */
    public function store(Request $request, InviteLink $invite, string $token): RedirectResponse
    {
        $user = $invite->user;
        if ($user === null || ! $invite->matches($token) || ! $invite->isValid()) {
            return redirect()->route('login')->withErrors(['email' => __('auth_ui.invite_invalid')]);
        }

        $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->value()),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $invite->forceFill(['used_at' => now()])->save();

        AuditLog::record('user.invite_link_used', null, ['user_id' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('finance.index');
    }
}
