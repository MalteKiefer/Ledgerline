<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DevicePairing;
use App\Services\Auth\Pairing;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ['pairing' => $row, 'code' => $code] = $pairing->create($request->user());

        // The QR carries only the short-lived one-time code plus this server's
        // base URL, as a deep link the app handles. The real token is never here.
        $payload = 'ledgerline://pair?url='.rawurlencode((string) config('app.url')).'&code='.rawurlencode($code);
        $qr = (new SvgWriter)->write(new QrCode($payload))->getDataUri();

        return response()->json([
            'id' => $row->id,
            'qr' => $qr,
            'expires_at' => $row->expires_at->toIso8601String(),
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

        return response()->json(['status' => $devicePairing->refresh()->status]);
    }

    /** Decline the claimed device. */
    public function reject(Request $request, DevicePairing $devicePairing, Pairing $pairing): JsonResponse
    {
        $this->authorizeOwner($request, $devicePairing);
        $pairing->reject($devicePairing);

        return response()->json(['status' => $devicePairing->refresh()->status]);
    }

    /** A pairing belongs to exactly one user; anyone else gets a 404 (not a 403 — no existence leak). */
    private function authorizeOwner(Request $request, DevicePairing $devicePairing): void
    {
        abort_if((int) $devicePairing->user_id !== (int) $request->user()->id, 404);
    }
}
