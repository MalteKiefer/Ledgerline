<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Support\WebAuthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * WebAuthn passkeys / hardware security keys (YubiKey, Nitrokey, platform
 * authenticators). Bearer-SPA aware: registration/management require the
 * authenticated user (+ a current-password step-up to register); the login pair
 * is public and mints a Sanctum device token on a valid assertion — coexisting
 * with email+password+TOTP.
 */
class PasskeyController extends Controller
{
    public function __construct(private readonly WebAuthn $webauthn) {}

    // ---- Management (auth) ----

    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $rows = WebauthnCredential::query()->where('user_id', $uid)->orderByDesc('id')->get()
            ->map(fn (WebauthnCredential $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'last_used_at' => $c->last_used_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all();

        return response()->json(['passkeys' => $rows, 'enabled' => $this->webauthn->enabled()]);
    }

    public function registerOptions(Request $request): Response
    {
        $user = $this->requireUser($request);

        return response($this->webauthn->registerOptions($user))->header('Content-Type', 'application/json');
    }

    public function register(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'credential' => ['required', 'array'],
            'name' => ['nullable', 'string', 'max:120'],
            'current_password' => ['required', 'string'],
        ]);
        // Step-up: registering a new sign-in credential requires the password.
        abort_unless(Hash::check($request->string('current_password')->value(), (string) $user->password), 422, 'invalid_password');

        $cred = $this->webauthn->verifyRegistration(
            $user,
            (string) json_encode($request->input('credential')),
            $request->filled('name') ? $request->string('name')->value() : null,
        );

        return response()->json(['id' => $cred->id, 'name' => $cred->name, 'created_at' => $cred->created_at?->toIso8601String()], 201);
    }

    public function rename(Request $request, int $credential): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->ownedCredential($request, $credential)->update(['name' => $request->string('name')->value()]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $credential): JsonResponse
    {
        $this->ownedCredential($request, $credential)->delete();

        return response()->json(['ok' => true]);
    }

    /** Owner-scoped credential lookup (404 for anyone else). */
    private function ownedCredential(Request $request, int $id): WebauthnCredential
    {
        return WebauthnCredential::query()
            ->where('user_id', (int) $this->requireUser($request)->id)
            ->findOrFail($id);
    }

    // ---- Login (public) ----

    public function loginOptions(Request $request): JsonResponse
    {
        $r = $this->webauthn->loginOptions();

        return response()->json(['handle' => $r['handle'], 'options' => json_decode($r['options'])]);
    }

    public function loginVerify(Request $request): JsonResponse
    {
        $request->validate([
            'handle' => ['required', 'string'],
            'credential' => ['required', 'array'],
        ]);
        $user = $this->webauthn->verifyAssertion(
            $request->string('handle')->value(),
            (string) json_encode($request->input('credential')),
        );
        if (! $user instanceof User) {
            return response()->json(['message' => 'passkey_failed'], 422);
        }
        if ($user->isBlocked()) {
            return response()->json(['message' => 'blocked'], 403);
        }
        if (! $user->hasVerifiedEmail()) {
            return response()->json(['status' => 'verify-email'], 403);
        }

        $token = $user->createToken('web', ['device'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => app(AuthController::class)->userPayload($user),
        ]);
    }
}
