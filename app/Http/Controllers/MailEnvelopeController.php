<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store the client-built sealed ENVELOPE for a message (headers only, sealed to
 * the owner's identity keys). Zero-knowledge: the server cannot read the sealed
 * body, so the CLIENT parses the headers after decrypting the body once and
 * uploads the sealed envelope here; the server only stores opaque ciphertext so
 * future list-loads / other devices decrypt just the tiny envelope, never the
 * body. Owner-scoped; idempotent (overwrites the owner's own value).
 */
class MailEnvelopeController extends Controller
{
    private const MAX_BYTES = 65_536; // envelopes are tiny (headers only)

    public function __invoke(Request $request, MailMessage $message): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($message->user_id !== $user->id) {
            abort(404);
        }

        $request->validate([
            'envelope' => ['required', 'string', 'max:'.self::MAX_BYTES],
            'envelope_key' => ['required', 'string', 'max:'.self::MAX_BYTES],
        ]);

        $message->forceFill([
            'envelope' => $request->string('envelope')->value(),
            'envelope_key' => $request->string('envelope_key')->value(),
        ])->save();

        return response()->json(['stored' => true]);
    }
}
