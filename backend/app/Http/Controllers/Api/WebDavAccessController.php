<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * JSON mirror of the web WebDavAccessController: set or clear the signed-in
 * user's app-specific WebDAV password (used to mount the Files tree as a network
 * drive). Stored hashed; NEVER echoed back. The GET reports whether it is set
 * plus the mount URL + username the client should use.
 */
class WebDavAccessController extends Controller
{
    /** Whether WebDAV access is enabled + the connection URL/username. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate(['webdav_password' => ['required', 'string', 'min:12', 'max:255']]);
        $user = $this->requireUser($request);
        $user->forceFill(['webdav_password' => Hash::make($request->string('webdav_password')->value())])->save();

        return response()->json($this->payload($request));
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $user->forceFill(['webdav_password' => null])->save();

        return response()->json($this->payload($request));
    }

    /** @return array{enabled: bool, username: string, url: string} */
    private function payload(Request $request): array
    {
        $user = $this->requireUser($request);
        $base = config('app.url');

        return [
            'enabled' => $user->webdav_password !== null,
            'username' => (string) $user->email,
            'url' => rtrim(is_string($base) ? $base : '', '/').'/dav/',
        ];
    }
}
