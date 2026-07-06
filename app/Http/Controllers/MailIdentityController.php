<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailIdentity;
use App\Models\MailSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sender identities for a mail account. Identities have no user_id: ownership is
 * enforced by resolving the parent account and checking isOwnedBy(). Each
 * account always keeps at least one identity, exactly one of which is default.
 */
class MailIdentityController extends Controller
{
    /** Dedicated management page for all of the user's identities. */
    public function page(): View
    {
        return view('mail.identities');
    }

    /** Every identity across the user's accounts, with account + signature info. */
    public function all(Request $request): JsonResponse
    {
        $accounts = MailAccount::with('identities')->orderBy('name')->get();
        $signatures = MailSignature::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'accounts' => $accounts->map(fn (MailAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'username' => $a->username,
                'identities' => $a->identities->map(fn (MailIdentity $i) => $this->toArray($i))->values(),
            ])->values(),
            'signatures' => $signatures->map(fn (MailSignature $s) => ['id' => $s->id, 'name' => $s->name])->values(),
        ]);
    }

    public function index(Request $request, int $account): JsonResponse
    {
        $mailAccount = $this->account($request, $account);

        return response()->json([
            'identities' => $mailAccount->identities->map(fn (MailIdentity $i) => $this->toArray($i))->values(),
        ]);
    }

    public function store(Request $request, int $account): JsonResponse
    {
        $mailAccount = $this->account($request, $account);
        $data = $this->validated($request);

        $identity = DB::transaction(function () use ($mailAccount, $data): MailIdentity {
            $isDefault = (bool) ($data['is_default'] ?? false);
            // The first identity is always the default regardless of the flag.
            if ($mailAccount->identities()->count() === 0) {
                $isDefault = true;
            }
            if ($isDefault) {
                $mailAccount->identities()->update(['is_default' => false]);
            }

            return $mailAccount->identities()->create([
                'from_name' => $data['from_name'] ?? null,
                'from_email' => $data['from_email'],
                'reply_to' => $data['reply_to'] ?? null,
                'signature' => $data['signature'] ?? null,
                'signature_id' => $this->ownedSignatureId($data['signature_id'] ?? null),
                'is_default' => $isDefault,
            ]);
        });

        return response()->json($this->toArray($identity), 201);
    }

    public function update(Request $request, int $account, int $identity): JsonResponse
    {
        $mailAccount = $this->account($request, $account);
        $model = $this->identity($mailAccount, $identity);
        $data = $this->validated($request);

        DB::transaction(function () use ($mailAccount, $model, $data): void {
            if ((bool) ($data['is_default'] ?? false)) {
                $mailAccount->identities()->where('id', '!=', $model->id)->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                // Never let the default become non-default without a replacement;
                // an account must always have exactly one default identity.
                $data['is_default'] = $model->is_default;
            }
            $model->update([
                'from_name' => $data['from_name'] ?? null,
                'from_email' => $data['from_email'],
                'reply_to' => $data['reply_to'] ?? null,
                'signature' => $data['signature'] ?? null,
                'signature_id' => $this->ownedSignatureId($data['signature_id'] ?? null),
                'is_default' => $data['is_default'],
            ]);
        });

        return response()->json($this->toArray($model->refresh()));
    }

    public function destroy(Request $request, int $account, int $identity): JsonResponse
    {
        $mailAccount = $this->account($request, $account);
        $model = $this->identity($mailAccount, $identity);

        // Always keep at least one identity.
        if ($mailAccount->identities()->count() <= 1) {
            return response()->json(['ok' => false, 'message' => __('mail.identity_last_cannot_delete')], 422);
        }

        DB::transaction(function () use ($mailAccount, $model): void {
            $wasDefault = $model->is_default;
            $model->delete();
            // Promote another identity to default if we removed the default one.
            if ($wasDefault) {
                $next = $mailAccount->identities()->first();
                $next?->update(['is_default' => true]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Resolve the parent account and enforce ownership (no global scope). */
    private function account(Request $request, int $accountId): MailAccount
    {
        $account = MailAccount::withoutGlobalScopes()->findOrFail($accountId);
        abort_unless((int) $account->user_id === (int) $request->user()->id, 403);

        return $account;
    }

    /** Resolve an identity and verify it belongs to the given account. */
    private function identity(MailAccount $account, int $identityId): MailIdentity
    {
        $identity = MailIdentity::findOrFail($identityId);
        abort_unless($identity->mail_account_id === $account->id, 404);

        return $identity;
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_email' => ['required', 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'signature' => ['nullable', 'string', 'max:5000'],
            'signature_id' => ['nullable', 'integer', 'exists:mail_signatures,id'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    /** Only accept a signature id the current user owns (else null). */
    private function ownedSignatureId(mixed $id): ?int
    {
        if (empty($id)) {
            return null;
        }

        return MailSignature::query()->whereKey($id)->exists() ? (int) $id : null;
    }

    /** @return array<string,mixed> */
    private function toArray(MailIdentity $i): array
    {
        return [
            'id' => $i->id,
            'fromName' => $i->from_name,
            'fromEmail' => $i->from_email,
            'replyTo' => $i->reply_to,
            'signature' => $i->signature,
            'signatureId' => $i->signature_id,
            'isDefault' => $i->is_default,
        ];
    }
}
