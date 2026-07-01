<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an expense. The amount is entered in major units (e.g. euros) and
 * converted to cents by the controller.
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['billable' => $this->boolean('billable')]);

        if (is_array($this->input('labels'))) {
            $this->merge([
                'labels' => collect($this->input('labels'))
                    ->map(fn ($l): string => is_string($l) ? trim($l) : '')
                    ->filter()
                    ->unique(fn (string $l): string => mb_strtolower($l))
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'category_custom' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', Rule::in(config('finance.currencies'))],
            'tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'paid_on' => ['nullable', 'date'],
            'billable' => ['boolean'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'labels' => ['array'],
            'labels.*' => ['string', 'max:50'],
        ];
    }
}
