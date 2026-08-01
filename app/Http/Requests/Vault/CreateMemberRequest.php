<?php

declare(strict_types=1);

namespace App\Http\Requests\Vault;

use App\Enums\VaultRole;
use App\Models\SharedVault;
use App\Models\SharedVaultMember;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates and authorizes adding a member to a shared password-Tresor.
 *
 * The actor must hold the `manage` ability on the route-bound vault. An
 * additional after-validation guard ensures the requested role rank does not
 * exceed the actor's own vault role rank (a manager may not grant above their
 * own level; an editor cannot reach this path at all since `manage` is denied).
 */
class CreateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /** @var SharedVault $vault */
        $vault = $this->route('vault');

        return $user->can('manage', $vault);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var SharedVault $vault */
        $vault = $this->route('vault');

        return [
            'user_id' => [
                'required',
                'integer',
                // Enumeration-resistance parity with resolveRecipient: the target must be
                // a user who has PUBLISHED identity keys (x25519 + ML-KEM). A bare
                // `exists:users,id` was a user-existence oracle (201 vs 422 over the whole
                // users table); requiring keys means a probe of any id that isn't a
                // vault-provisioned user fails identically to a non-existent id. You cannot
                // wrap a vault key to a keyless user anyway, so this is also a correctness rule.
                Rule::exists('users', 'id')
                    ->whereNotNull('x25519_public_key')
                    ->whereNotNull('mlkem_public_key'),
                Rule::unique('shared_vault_members', 'user_id')
                    ->where('vault_id', $vault->id),
            ],
            'role' => ['required', 'string', Rule::in(VaultRole::values())],
            'wrapped_vault_key' => ['required', 'string'],
            'recipient_fingerprint' => ['nullable', 'string'],
        ];
    }

    /**
     * Reject if the requested role rank exceeds the actor's own vault role.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $requested = $this->input('role');
            $requestedRole = is_string($requested) ? VaultRole::tryFrom($requested) : null;

            if ($requestedRole === null) {
                // Already caught by the Rule::in rule — bail early.
                return;
            }

            /** @var SharedVault $vault */
            $vault = $this->route('vault');

            $user = $this->user();
            abort_unless($user instanceof User, 403);

            $actorMembership = SharedVaultMember::where('vault_id', $vault->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            $actorRole = $actorMembership?->role instanceof VaultRole
                ? $actorMembership->role
                : VaultRole::tryFrom((string) ($actorMembership?->role ?? ''));

            if ($actorRole === null || ! $actorRole->atLeast($requestedRole)) {
                $v->errors()->add('role', 'The selected role exceeds your own vault role.');
            }
        });
    }
}
