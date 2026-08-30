<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentListRequest extends FormRequest
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
            'unallocated' => ['nullable', 'boolean'],
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
        foreach (['q', 'from', 'to'] as $key) {
            $value = $this->validated($key);
            if (is_string($value)) {
                $filters[$key] = $value;
            }
        }
        if ($this->filled('unallocated')) {
            $filters['unallocated'] = $this->boolean('unallocated');
        }

        return $filters;
    }
}
