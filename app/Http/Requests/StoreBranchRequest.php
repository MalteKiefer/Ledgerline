<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates input for creating a branch office.
 *
 * The country must be a valid ISO code and the optional manager must be one of
 * the owning customer's contacts. Authorization is handled by BranchPolicy.
 */
class StoreBranchRequest extends FormRequest
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
        /** @var Customer $customer */
        $customer = $this->route('customer');

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
                Rule::exists('contacts', 'id')->where('customer_id', $customer->id),
            ],
        ];
    }
}
