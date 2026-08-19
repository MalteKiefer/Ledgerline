<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MailAccount;
use App\Models\MailSignature;
use App\Support\Mail\MailHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MailSignatureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        return response()->json(['signatures' => MailSignature::query()->ownedBy($uid)->with('accounts:id')->orderBy('name')->get()->map(fn (MailSignature $signature): array => $this->present($signature))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $data = $this->validated($request, $uid);
        $signature = new MailSignature(['name' => $data['name'], 'html' => $data['html']]);
        $signature->save();
        $this->assign($signature, $data['account_ids'], $data['default_account_ids'], $uid);
        AuditLog::record('mail.signature_created', $signature);

        return response()->json(['signature' => $this->present($signature->fresh(['accounts:id']) ?? $signature)], 201);
    }

    public function update(Request $request, MailSignature $signature): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $this->authorizeOwner($signature, $uid);
        $data = $this->validated($request, $uid);
        $signature->update(['name' => $data['name'], 'html' => $data['html']]);
        $this->assign($signature, $data['account_ids'], $data['default_account_ids'], $uid);
        AuditLog::record('mail.signature_updated', $signature);

        return response()->json(['signature' => $this->present($signature->fresh(['accounts:id']) ?? $signature)]);
    }

    public function destroy(Request $request, MailSignature $signature): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $this->authorizeOwner($signature, $uid);
        $signature->delete();
        AuditLog::record('mail.signature_deleted', $signature);

        return response()->json([], 204);
    }

    /** @return array{name:string,html:?string,account_ids:list<int>,default_account_ids:list<int>} */
    private function validated(Request $request, int $uid): array
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'html' => ['nullable', 'string', 'max:20000'],
            'account_ids' => ['array'],
            'account_ids.*' => ['integer', Rule::exists('mail_accounts', 'id')->where('user_id', $uid)],
            'default_account_ids' => ['array'],
            'default_account_ids.*' => ['integer', Rule::exists('mail_accounts', 'id')->where('user_id', $uid)],
        ])->validate();

        $accounts = array_values(array_unique(array_map('intval', (array) $request->input('account_ids', []))));
        $defaults = array_values(array_unique(array_map('intval', (array) $request->input('default_account_ids', []))));
        Validator::make(['defaults' => $defaults], ['defaults.*' => [Rule::in($accounts)]])->validate();

        return [
            'name' => trim($request->string('name')->value()),
            'html' => (new MailHtmlSanitizer)->sanitize($request->input('html'), true),
            'account_ids' => $accounts,
            'default_account_ids' => $defaults,
        ];
    }

    /** @param list<int> $accounts @param list<int> $defaults */
    private function assign(MailSignature $signature, array $accounts, array $defaults, int $uid): void
    {
        DB::transaction(function () use ($signature, $accounts, $defaults, $uid): void {
            $signature->accounts()->sync(array_fill_keys($accounts, ['is_default' => false]));
            foreach ($defaults as $accountId) {
                MailAccount::query()->ownedBy($uid)->whereKey($accountId)->lockForUpdate()->firstOrFail();
                DB::table('mail_account_signatures')->where('mail_account_id', $accountId)->update(['is_default' => false]);
                DB::table('mail_account_signatures')->where(['mail_account_id' => $accountId, 'mail_signature_id' => $signature->id])->update(['is_default' => true]);
            }
        });
    }

    /** @return array{id:int,name:string,html:?string,account_ids:list<int>,default_account_ids:list<int>} */
    private function present(MailSignature $signature): array
    {
        $accounts = $signature->accounts;

        return [
            'id' => $signature->id,
            'name' => $signature->name,
            'html' => $signature->html,
            'account_ids' => $accounts->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'default_account_ids' => $accounts->filter(fn (MailAccount $account): bool => (bool) $account->pivot?->is_default)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
        ];
    }

    private function authorizeOwner(MailSignature $signature, int $uid): void
    {
        abort_if((int) $signature->user_id !== $uid, 404);
    }
}
