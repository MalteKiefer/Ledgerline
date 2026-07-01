<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a time entry. Duration is entered in hours and the optional rate in
 * major units; the controller converts to minutes and cents and resolves the
 * effective rate.
 */
class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['billable' => $this->boolean('billable', true)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'hours' => ['required', 'numeric', 'min:0', 'max:10000'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', Rule::in(config('finance.currencies'))],
            'billable' => ['boolean'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ];
    }
}
