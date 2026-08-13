<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PurgeUserAccount;
use App\Models\AuditLog;
use App\Support\UserData\UserDataContributor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Self-service account: export all my data (GDPR portability), delete my account
 * (GDPR erasure) and revoke other active sessions.
 */
class AccountController extends Controller
{
    /** Stream a zip of the user's data (one JSON file per module). Data only, no blobs. */
    public function export(Request $request): StreamedResponse
    {
        $user = $this->requireUser($request);
        $sections = [];
        $contributors = config('user_data.contributors', []);
        foreach (is_array($contributors) ? $contributors : [] as $class) {
            if (! is_string($class)) {
                continue;
            }
            /** @var UserDataContributor $contributor */
            $contributor = app($class);
            $sections[$contributor->key()] = $contributor->export($user);
        }
        $sections['account'] = [
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'llexport');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($sections as $key => $data) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $zip->close();
                @unlink($tmp);
                throw new \RuntimeException("Failed to encode export section {$key}: ".json_last_error_msg());
            }
            $zip->addFromString($key.'.json', $json);
        }
        $zip->close();

        $filename = 'ledgerline-export-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($tmp): void {
            readfile($tmp);
            @unlink($tmp);
        }, $filename, ['Content-Type' => 'application/zip']);
    }

    /**
     * List the caller's active web sessions (DB session driver only; empty
     * otherwise). Mirrors the profile Sessions page. Newest first; `current`
     * flags the caller's own browser session (never present on a token request).
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $currentId = $request->hasSession() ? $request->session()->getId() : null;

        $rows = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()
            : collect();

        $sessions = $rows->map(fn ($s): array => [
            'id' => is_scalar($s->id) ? (string) $s->id : '',
            'ip' => is_string($s->ip_address) ? $s->ip_address : null,
            'user_agent' => is_string($s->user_agent) ? $s->user_agent : null,
            'last_active' => is_numeric($s->last_activity) ? (int) $s->last_activity : 0,
            'current' => $s->id === $currentId,
        ])->all();

        return response()->json(['sessions' => $sessions]);
    }

    /** Revoke another active session (not the current one). */
    public function revokeSession(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $user = $this->requireUser($request);
        // Exclude the caller's own web session (if any — a token API request has none).
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->delete();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('status', __('account.session_revoked'));
    }

    /** Permanently delete the account and all owned data. */
    public function destroy(Request $request, PurgeUserAccount $purge): RedirectResponse|JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'confirmation' => ['required', 'string', 'in:'.$user->email],
        ], ['confirmation.in' => __('account.delete_confirm_mismatch')]);

        // Capture the pre-purge identity: after the purge + logout, $request->user() is null.
        $deletedUser = $user;
        $deletedUserId = $user->id;

        $purge->handle($user);

        AuditLog::record('account.deleted', $deletedUser, [], $deletedUserId);

        // Token API request (no web session): revoke the presented token and answer JSON.
        if ($request->expectsJson() || ! $request->hasSession()) {
            // Only a real PersonalAccessToken can be deleted; under SPA cookie
            // auth this is a TransientToken (no delete()) and the row is gone anyway.
            $tok = $user->currentAccessToken();
            if ($tok instanceof PersonalAccessToken) {
                $tok->delete();
            }

            return response()->json(['deleted' => true]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('account.deleted'));
    }
}
