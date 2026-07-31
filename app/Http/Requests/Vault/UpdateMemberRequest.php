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
 * Validates and authorizes updating an existing membership row in a shared
 * password-Tresor.
 *
 * Guards:
 *   1. Actor must hold the `manage` ability on the vault.
 *   2. Requested role rank must not exceed the actor's own vault role rank.
 *   3. Actor may not target their own membership (self-promotion blocked).
 */
class UpdateMemberRequest extends FormRequest
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
        return [
            'role' => ['required', 'string', Rule::in(VaultRole::values())],
        ];
    }

    /**
     * Apply role-rank and self-target guards after base validation passes.
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

            /** @var SharedVaultMember $targetMember */
            $targetMember = $this->route('member');

            // Guard: block self-targeting.
            if ((int) $targetMember->user_id === (int) $user->id) {
                $v->errors()->add('role', 'You cannot change your own vault membership role.');

                return;
            }

            // Guard: requested rank must not exceed actor's own rank.
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
