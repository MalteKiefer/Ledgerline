<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Branch;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates input for updating a branch office.
 *
 * Mirrors StoreBranchRequest; the manager must belong to the branch's own
 * customer. Authorization is handled by BranchPolicy.
 */
class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(Countries::codes())],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where('customer_id', $branch->customer_id),
            ],
        ];
    }
}
