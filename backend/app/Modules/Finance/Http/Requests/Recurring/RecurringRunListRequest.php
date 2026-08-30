<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Recurring;

use Illuminate\Foundation\Http\FormRequest;

final class RecurringRunListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable', 'string',
                'in:pending,creating_draft,draft_created,finalizing,finalized,sending,sent,failed',
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $filters = [];
        $status = $this->validated('status');
        if (is_string($status)) {
            $filters['status'] = $status;
        }

        return $filters;
    }
}
