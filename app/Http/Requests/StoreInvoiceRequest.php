<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaxMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an invoice draft and its lines. Monetary inputs are in major units
 * and converted to cents by the controller.
 */
class StoreInvoiceRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'language' => ['required', Rule::in(array_keys(config('finance.languages')))],
            'currency' => ['required', Rule::in(config('finance.currencies'))],
            'tax_mode' => ['required', Rule::enum(TaxMode::class)],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'intro_text' => ['nullable', 'string', 'max:2000'],
            'closing_text' => ['nullable', 'string', 'max:2000'],
            'lines' => ['array'],
            'lines.*.description' => ['required_with:lines.*.unit_price', 'nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'lines.*.tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'import' => ['array'],
            'import.*' => ['string', 'max:40'],
        ];
    }
}
