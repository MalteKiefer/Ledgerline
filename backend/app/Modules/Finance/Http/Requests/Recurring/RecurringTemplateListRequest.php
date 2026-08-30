<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Recurring;

use Illuminate\Foundation\Http\FormRequest;

final class RecurringTemplateListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:active,paused,completed'],
            'mode' => ['nullable', 'string', 'in:draft,auto_send'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $filters = [];
        foreach (['status', 'mode'] as $key) {
            $value = $this->validated($key);
            if (is_string($value)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
