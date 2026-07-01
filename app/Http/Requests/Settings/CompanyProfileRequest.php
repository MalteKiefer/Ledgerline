<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the global company profile.
 */
class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(Countries::codes())],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'register_court' => ['nullable', 'string', 'max:255'],
            'register_number' => ['nullable', 'string', 'max:100'],
            'managing_director' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'small_business' => ['boolean'],
            'default_language' => ['required', Rule::in(array_keys(config('finance.languages')))],
            'default_currency' => ['required', Rule::in(config('finance.currencies'))],
            'default_tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'tax_display' => ['required', Rule::in(['line', 'invoice'])],
            'paper_size' => ['required', Rule::in(config('finance.paper_sizes'))],
            'invoice_number_prefix' => ['required', 'string', 'max:20'],
            'invoice_number_next' => ['required', 'integer', 'min:1'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_footer_text' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * Normalise the checkbox to a real boolean.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['small_business' => $this->boolean('small_business')]);
    }
}
