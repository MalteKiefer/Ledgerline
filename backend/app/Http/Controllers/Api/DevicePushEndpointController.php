<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Support\OutboundUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-device UnifiedPush endpoint registration. The Android client registers an
 * HTTPS endpoint (typically on the self-hosted ntfy) and hands it to the server
 * tied to the calling device token; SendPushJob later POSTs notification payloads
 * there. The endpoint is stored encrypted on the token (App\Models\PersonalAccessToken).
 */
class DevicePushEndpointController extends Controller
{
    /** Upsert the push endpoint on the current device token. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', 'url', 'starts_with:https://'],
        ]);
        $endpoint = $request->string('endpoint')->value();

        // The server will POST to this URL later, so reject one whose host resolves
        // to a blocked target (link-local / metadata / private range) up front.
        if (! OutboundUrl::safe($endpoint)) {
            return response()->json(['error' => 'endpoint_not_allowed'], 422);
        }

        $token = $this->currentToken($request);
        $token->forceFill(['push_endpoint' => $endpoint])->save();

        return response()->json(['ok' => true]);
    }

    /** Clear the push endpoint (push disabled / distributor removed). */
    public function destroy(Request $request): JsonResponse
    {
        $this->currentToken($request)->forceFill(['push_endpoint' => null])->save();

        return response()->json(['ok' => true]);
    }

    private function currentToken(Request $request): PersonalAccessToken
    {
        $token = $request->user()?->currentAccessToken();
        // Device-scoped route → always a real bearer token, never a session guard.
        abort_unless($token instanceof PersonalAccessToken, 403);

        return $token;
    }
}
