<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use App\Enums\TaxMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the reviewed data for an imported historical invoice.
 */
class StoreImportedInvoiceRequest extends FormRequest
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
            'file_id' => ['required', 'integer', Rule::exists('files', 'id')],
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['nullable', 'required_if:customer_mode,existing', 'integer', Rule::exists('customers', 'id')],
            'new_customer_name' => ['nullable', 'required_if:customer_mode,new', 'string', 'max:255'],
            'new_customer_street' => ['nullable', 'string', 'max:255'],
            'new_customer_postal_code' => ['nullable', 'string', 'max:20'],
            'new_customer_city' => ['nullable', 'string', 'max:255'],
            'new_customer_vat_id' => ['nullable', 'string', 'max:50'],

            'number' => ['required', 'string', 'max:50', Rule::unique('invoices', 'number')],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'currency' => ['required', Rule::in(config('finance.currencies'))],
            'tax_mode' => ['required', Rule::enum(TaxMode::class)],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
