<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesDefaultRate;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates input for updating a customer.
 *
 * Authorization is enforced by CustomerPolicy via authorizeResource() in the
 * controller, so this request only needs to validate the payload. The rules
 * mirror StoreCustomerRequest.
 */
class UpdateCustomerRequest extends FormRequest
{
    use ResolvesDefaultRate;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeDefaultRate();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(Countries::codes())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'default_rate_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
