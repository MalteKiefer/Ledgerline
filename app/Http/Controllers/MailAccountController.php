<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mail accounts as plain rows (the login password is encrypted at rest). Served
 * as a JSON API for the client; the password is never returned to the browser.
 */
class MailAccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'accounts' => MailAccount::orderBy('name')->get()->map(fn (MailAccount $a) => $this->toArray($a)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, requirePassword: true);
        $account = MailAccount::create($data);

        return response()->json($this->toArray($account), 201);
    }

    public function update(Request $request, MailAccount $account): JsonResponse
    {
        $data = $this->validated($request, requirePassword: false);
        // Empty password keeps the stored one (so it need not be retyped).
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $account->update($data);

        return response()->json($this->toArray($account->refresh()));
    }

    public function destroy(MailAccount $account): JsonResponse
    {
        $account->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, bool $requirePassword): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(['ssl', 'starttls'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$requirePassword ? 'required' : 'nullable', 'string', 'max:1024'],
            'validate_cert' => ['sometimes', 'boolean'],
        ]);
    }

    /** Public shape — never includes the password. */
    private function toArray(MailAccount $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'host' => $a->host,
            'port' => $a->port,
            'encryption' => $a->encryption,
            'username' => $a->username,
            'validateCert' => $a->validate_cert,
            'lastSyncedAt' => $a->last_synced_at?->toIso8601String(),
        ];
    }
}
