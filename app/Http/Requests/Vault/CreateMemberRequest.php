<?php

declare(strict_types=1);

namespace App\Http\Requests\Vault;

use App\Models\SharedVault;
use App\Models\SharedVaultMember;
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
    /** Role-rank table — higher number = more permissive. */
    private const ROLE_RANK = [
        'viewer' => 1,
        'editor' => 2,
        'manager' => 3,
    ];

    public function authorize(): bool
    {
        /** @var SharedVault $vault */
        $vault = $this->route('vault');

        return $this->user()->can('manage', $vault);
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
                'exists:users,id',
                Rule::unique('shared_vault_members', 'user_id')
                    ->where('vault_id', $vault->id),
            ],
            'role' => ['required', 'string', 'in:viewer,editor,manager'],
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

            if (! isset(self::ROLE_RANK[$requested])) {
                // Already caught by the `in:` rule — bail early.
                return;
            }

            /** @var SharedVault $vault */
            $vault = $this->route('vault');

            $actorMembership = SharedVaultMember::where('vault_id', $vault->id)
                ->where('user_id', $this->user()->id)
                ->where('status', 'active')
                ->first();

            $actorRank = isset($actorMembership->role) ? (self::ROLE_RANK[$actorMembership->role] ?? 0) : 0;
            $requestedRank = self::ROLE_RANK[$requested];

            if ($requestedRank > $actorRank) {
                $v->errors()->add('role', 'The selected role exceeds your own vault role.');
            }
        });
    }
}
