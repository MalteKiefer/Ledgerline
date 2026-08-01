<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserSetting;
use App\Services\Invoices\InvoiceMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Send an invoice PDF by e-mail through the user's own invoice SMTP server.
 * The PDF is generated client-side and posted transiently; nothing is persisted
 * or logged (the invoice content stays ZK at rest — this is a deliberate,
 * user-initiated boundary crossing, mirroring receipt→Paperless). Guard-agnostic
 * (`requireUser`) so it mounts on both web (session) and /api/v1 (Sanctum).
 */
class InvoiceMailController extends Controller
{
    public function send(Request $request, InvoiceMailer $mailer): JsonResponse
    {
        $user = $this->requireUser($request);
        $settings = UserSetting::for($user->id);

        if (! $mailer->configured($settings)) {
            // Not set up → client falls back to download/manual send.
            return response()->json(['error' => 'not_configured'], 501)
                ->header('Cache-Control', 'no-store');
        }

        $request->validate([
            'to' => ['required', 'email', 'max:254'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'], // 20 MiB
        ]);

        try {
            $bytes = (string) file_get_contents($request->file('pdf')->getRealPath());
            $mailer->send(
                $settings,
                $request->string('to')->value(),
                $request->string('subject')->value(),
                $request->string('body')->value(),
                $bytes,
                'invoice.pdf',
            );
        } catch (\Throwable $e) {
            // Never leak SMTP internals / recipient into the response.
            return response()->json(['error' => 'send_failed'], 422)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }
}
