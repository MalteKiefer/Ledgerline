<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

final class QuoteListRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,declined,converted'],
            'effective_status' => ['nullable', 'string', 'in:draft,sent,accepted,declined,converted,expired'],
            'published_from' => ['nullable', 'date_format:Y-m-d'],
            'published_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:published_from'],
            'sort' => ['nullable', 'string', 'in:published_at'],
            'direction' => ['nullable', 'string', 'in:desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(int $ownerId): array
    {
        $filters = ['owner_id' => $ownerId];
        foreach (['q', 'status', 'effective_status', 'published_from', 'published_to'] as $key) {
            $value = $this->validated($key);
            if (is_string($value)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
