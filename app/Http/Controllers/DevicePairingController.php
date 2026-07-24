<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DevicePairing;
use App\Services\Auth\Pairing;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Web (session) side of QR device pairing. The signed-in owner starts a pairing
 * (rendered as a QR), watches it become claimed by a named device, and approves
 * or rejects it. All actions are scoped to the owner's own pairing rows.
 */
class DevicePairingController extends Controller
{
    /** Begin a pairing; return its id, a QR (the app scans it) and the expiry. */
    public function store(Request $request, Pairing $pairing): JsonResponse
    {
        $user = $this->requireUser($request);
        ['pairing' => $row, 'code' => $code] = $pairing->create($user);

        // The QR carries only the short-lived one-time code plus this server's
        // base URL, as a deep link the app handles. The real token is never here.
        $appUrl = config('app.url');
        $payload = 'ledgerline://pair?url='.rawurlencode(is_string($appUrl) ? $appUrl : '').'&code='.rawurlencode($code);
        $qr = (new SvgWriter)->write(new QrCode($payload))->getDataUri();

        return response()->json([
            'id' => $row->id,
            'qr' => $qr,
            'expires_at' => $row->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Begin a pairing for a command-line client. Unlike the app flow the code is
     * shown to the owner as copyable text (there is nothing to scan), and it lives
     * for a shorter 60-second window. Everything downstream — the app-side claim,
     * the owner's approval, and the one-time token collect — is unchanged.
     */
    public function storeCli(Request $request, Pairing $pairing): JsonResponse
    {
        $user = $this->requireUser($request);
        ['pairing' => $row, 'code' => $code] = $pairing->create($user, Pairing::CLI_TTL_SECONDS);

        return response()->json([
            'id' => $row->id,
            'code' => $code,
            'expires_at' => $row->expires_at?->toIso8601String(),
        ]);
    }

    /** Poll a pairing's state (the web page shows the claiming device + approve/reject). */
    public function show(Request $request, DevicePairing $devicePairing): JsonResponse
    {
        $this->authorizeOwner($request, $devicePairing);

        return response()->json([
            'status' => $devicePairing->isExpired() ? 'expired' : $devicePairing->status,
            'device_name' => $devicePairing->device_name,
        ]);
    }

    /** Approve the claimed device (the app will then collect its token). */
    public function approve(Request $request, DevicePairing $devicePairing, Pairing $pairing): JsonResponse
    {
        $this->authorizeOwner($request, $devicePairing);
        $pairing->approve($devicePairing);

        AuditLog::record('device.paired', $devicePairing);

        return response()->json(['status' => $devicePairing->refresh()->status]);
    }

    /** Decline the claimed device. */
    public function reject(Request $request, DevicePairing $devicePairing, Pairing $pairing): JsonResponse
    {
        $this->authorizeOwner($request, $devicePairing);
        $pairing->reject($devicePairing);

        AuditLog::record('device.pairing_rejected', $devicePairing);

        return response()->json(['status' => $devicePairing->refresh()->status]);
    }

    /** The caller's paired devices (Sanctum tokens), newest first — for the live list. */
    public function devices(Request $request): JsonResponse
    {
        // The token making THIS request (so a client can refuse to revoke/wipe
        // itself). Null for session-auth callers (web). Resolved once, then
        // captured into the map closure (a bare closure would not see $request).
        $user = $this->requireUser($request);
        $currentKey = $user->currentAccessToken()?->getKey();
        $devices = $user->tokens()
            // Most-recently-used first (nulls last), so the live device is on top and
            // any stale row sinks — a web caller can't match currentAccessToken().
            ->orderByRaw('last_used_at is null')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')->get()
            ->map(function ($t) use ($currentKey): array {
                // Custom (non-Sanctum) columns come back as strings; parse the
                // heartbeat time. A client counts as actively syncing only if it
                // reported so recently (a stale "syncing" is treated as idle).
                $reportedAt = $t->sync_reported_at ? Carbon::parse($t->sync_reported_at) : null;
                $syncing = $t->sync_state === 'syncing' && $reportedAt && $reportedAt->gt(now()->subMinutes(3));

                // Non-secret client-correlation fields for the list (help the owner
                // tell the live device from a stale row): app/OS build + a short
                // install id. All already stored on the token; never a secret.
                $installId = is_string($t->install_id) && $t->install_id !== '' ? mb_substr($t->install_id, -6) : null;
                $version = trim(implode(' · ', array_filter([
                    is_string($t->os_version) ? $t->os_version : null,
                    is_string($t->app_version) ? $t->app_version : null,
                ])));

                return [
                    'id' => $t->id,
                    'current' => $t->getKey() === $currentKey,
                    'name' => $t->name ?: __('account.sessions_unknown'),
                    'meta' => trim(implode(' · ', array_filter([
                        $t->ip,
                        $t->last_used_at
                            ? __('account.devices_last_used', ['when' => $t->last_used_at->diffForHumans()])
                            : __('account.devices_never_used'),
                    ]))),
                    'version' => $version !== '' ? $version : null,
                    'installId' => $installId,
                    'syncing' => $syncing,
                    'syncDetail' => $syncing ? $t->sync_detail : null,
                    'syncSeen' => $reportedAt?->diffForHumans(),
                    'wipeRequested' => $t->wipe_requested_at !== null,
                ];
            })->all();

        return response()->json(['devices' => $devices]);
    }

    /** Revoke a paired device (delete its Sanctum token). Owner-scoped. */
    public function revokeDevice(Request $request, string $tokenId): JsonResponse
    {
        $this->requireUser($request)->tokens()->whereKey($tokenId)->delete();

        // Reason code distinguishes a deliberate owner removal from the automatic
        // killers (cap/idle/expired/wipe); via marks web-session vs API-token actor.
        AuditLog::record('device.revoked', null, [
            'token_id' => $tokenId,
            'reason' => 'manual',
            'via' => $request->user()?->currentAccessToken() ? 'api' : 'web',
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Flag a device to wipe its local state on next contact (remote kill switch).
     * The token stays valid so the client can fetch the flag and self-erase; the
     * owner can still revoke it outright. Owner-scoped.
     */
    public function wipeDevice(Request $request, string $tokenId): JsonResponse
    {
        $this->requireUser($request)->tokens()->whereKey($tokenId)->update(['wipe_requested_at' => now()]);

        AuditLog::record('device.wipe_requested', null, ['token_id' => $tokenId]);

        return response()->json(['ok' => true]);
    }

    /** A pairing belongs to exactly one user; anyone else gets a 404 (not a 403 — no existence leak). */
    private function authorizeOwner(Request $request, DevicePairing $devicePairing): void
    {
        abort_if((int) $devicePairing->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
