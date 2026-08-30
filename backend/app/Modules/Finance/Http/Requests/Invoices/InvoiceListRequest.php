<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

final class InvoiceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:draft,finalized,sent,partially_paid,paid,cancelled'],
            'kind' => ['nullable', 'string', 'in:invoice,credit_note'],
            'overdue' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $filters = [];
        foreach (['q', 'status', 'kind', 'from', 'to'] as $key) {
            $value = $this->validated($key);
            if (is_string($value)) {
                $filters[$key] = $value;
            }
        }
        if ($this->filled('overdue')) {
            $filters['overdue'] = $this->boolean('overdue');
        }

        return $filters;
    }
}
