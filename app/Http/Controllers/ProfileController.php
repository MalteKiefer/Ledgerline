<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileBlob;
use App\Models\GalleryBlob;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shows the signed-in user's profile.
 *
 * Identity data is owned by Pocket-ID and refreshed on each login (read-only).
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $this->requireUser($request);
        $currentId = $request->session()->getId();
        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)
                ->orderByDesc('last_activity')->get()
                ->map(fn ($s): array => [
                    'id' => $s->id,
                    'ip' => $s->ip_address,
                    'agent' => $s->user_agent,
                    'last_activity' => $s->last_activity,
                    'current' => $s->id === $currentId,
                ])->all()
            : [];

        $deviceMax = config('devices.max', 3);

        // Storage the account occupies: the user's OWN sealed blob bytes (files +
        // gallery). The server sees only ciphertext sizes — non-secret, the same
        // figures the quota check + usage endpoints use. A combined quota is shown
        // only when BOTH modules are capped; a 0 anywhere means "unlimited".
        $filesQuota = config('files.quota_mb', 0);
        $galleryQuota = config('gallery.quota_mb', 0);
        $filesQuotaMb = is_numeric($filesQuota) ? (int) $filesQuota : 0;
        $galleryQuotaMb = is_numeric($galleryQuota) ? (int) $galleryQuota : 0;
        $storageUsed = (int) FileBlob::query()->where('user_id', $user->id)->sum('size')
            + (int) GalleryBlob::query()->where('user_id', $user->id)->sum('size');
        $storageQuota = ($filesQuotaMb > 0 && $galleryQuotaMb > 0)
            ? ($filesQuotaMb + $galleryQuotaMb) * 1024 * 1024
            : 0;

        // Paired devices are loaded + kept live client-side (GET /devices); this
        // is the snapshot count for the stat tile.
        return view('profile', [
            'user' => $user,
            'sessions' => $sessions,
            'deviceMax' => is_numeric($deviceMax) ? (int) $deviceMax : 3,
            'deviceCount' => $user->tokens()->count(),
            'storageUsed' => $storageUsed,
            'storageQuota' => $storageQuota,
        ]);
    }
}
