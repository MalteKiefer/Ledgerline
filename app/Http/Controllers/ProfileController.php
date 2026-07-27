<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\FileBlob;
use App\Models\GalleryBlob;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in user's personal area, modelled on iOS Settings: a root hub
 * (/profile) that drills into focused sub-pages (account, devices, sessions,
 * encryption, appearance, export, danger). Identity data is owned by Pocket-ID
 * and refreshed on each login (read-only).
 */
class ProfileController extends Controller
{
    /** Root hub: hero + at-a-glance stats + grouped links to every sub-page. */
    public function index(Request $request): View
    {
        $user = $this->requireUser($request);

        return view('profile.index', [
            'user' => $user,
            'sessionCount' => $this->sessionsFor($user)->count(),
            'deviceCount' => $user->tokens()->count(),
            'deviceMax' => $this->deviceMax($user),
            'storageUsed' => $this->storageUsedBytes($user),
            'storageQuota' => $this->storageQuotaBytes($user),
        ]);
    }

    /** Account identity (read-only, from Pocket-ID). */
    public function account(Request $request): View
    {
        return view('profile.account', ['user' => $this->requireUser($request)]);
    }

    /** Paired mobile/CLI devices (loaded + kept live client-side). */
    public function devices(Request $request): View
    {
        return view('profile.devices', ['deviceMax' => $this->deviceMax($this->requireUser($request))]);
    }

    /** Active web sessions + last sign-in. */
    public function sessions(Request $request): View
    {
        $user = $this->requireUser($request);

        return view('profile.sessions', [
            'user' => $user,
            'sessions' => $this->sessionsFor($user)->all(),
        ]);
    }

    /** Zero-knowledge vault: change passphrase / reset via recovery code. */
    public function encryption(Request $request): View
    {
        $this->requireUser($request);

        return view('profile.encryption');
    }

    /** Login security: two-factor authentication (TOTP) via Fortify. */
    public function security(Request $request): View
    {
        $user = $this->requireUser($request);
        $enabled = filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null;
        $pending = filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;

        $qr = null;
        $recovery = [];
        if ($pending || $enabled) {
            try {
                $qr = $user->twoFactorQrCodeSvg();
            } catch (\Throwable) {
                $qr = null;
            }
            $codes = $user->two_factor_recovery_codes;
            if (is_string($codes)) {
                $decoded = json_decode($codes, true);
                $recovery = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
            }
        }

        return view('profile.security', [
            'enabled' => $enabled,
            'pending' => $pending,
            'qr' => $qr,
            'recovery' => $recovery,
        ]);
    }

    /** Colour scheme + interface language. */
    public function appearance(Request $request): View
    {
        $user = $this->requireUser($request);

        return view('profile.appearance', [
            'theme' => UserSetting::for($user->id)->theme ?? 'system',
        ]);
    }

    /** GDPR data export. */
    public function exportPage(Request $request): View
    {
        $this->requireUser($request);

        return view('profile.export');
    }

    /** Danger zone: delete account. */
    public function danger(Request $request): View
    {
        return view('profile.danger', ['user' => $this->requireUser($request)]);
    }

    /**
     * Active web sessions for the user, newest first. Empty when the session
     * driver is not the database (nothing to enumerate).
     *
     * @return Collection<int, array{id: string, ip: ?string, agent: ?string, last_activity: int, current: bool}>
     */
    private function sessionsFor(User $user): Collection
    {
        $currentId = request()->hasSession() ? request()->session()->getId() : null;
        $rows = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()
            : collect();

        return $rows
            ->map(fn ($s): array => [
                'id' => is_scalar($s->id) ? (string) $s->id : '',
                'ip' => is_string($s->ip_address) ? $s->ip_address : null,
                'agent' => is_string($s->user_agent) ? $s->user_agent : null,
                'last_activity' => is_numeric($s->last_activity) ? (int) $s->last_activity : 0,
                'current' => $s->id === $currentId,
            ]);
    }

    /** Device cap: per-user override → workspace setting → config default (same as pairing). */
    private function deviceMax(User $user): int
    {
        $configured = config('devices.max', 3);

        return $user->max_connected_devices
            ?: (AppSettings::current()->max_connected_devices
                ?: (is_numeric($configured) ? (int) $configured : 3));
    }

    /**
     * Storage the account occupies: the user's OWN sealed blob bytes (files +
     * gallery). The server sees only ciphertext sizes — non-secret, the same
     * figures the quota check + usage endpoints use.
     */
    private function storageUsedBytes(User $user): int
    {
        return (int) FileBlob::query()->where('user_id', $user->id)->sum('size')
            + (int) GalleryBlob::query()->where('user_id', $user->id)->sum('size');
    }

    /** Combined files+gallery quota in bytes, or 0 when either module is unlimited. */
    private function storageQuotaBytes(User $user): int
    {
        $filesQuota = $user->files_quota_mb ?: config('files.quota_mb', 0);
        $galleryQuota = $user->gallery_quota_mb ?: config('gallery.quota_mb', 0);
        $filesQuotaMb = is_numeric($filesQuota) ? (int) $filesQuota : 0;
        $galleryQuotaMb = is_numeric($galleryQuota) ? (int) $galleryQuota : 0;

        return ($filesQuotaMb > 0 && $galleryQuotaMb > 0)
            ? ($filesQuotaMb + $galleryQuotaMb) * 1024 * 1024
            : 0;
    }
}
