<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the editable file metadata (title, description, note, folder).
 * Authorization is handled by FilePolicy in the controller.
 */
class UpdateFileRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('tags'))) {
            $this->merge([
                'tags' => collect($this->input('tags'))
                    ->map(fn ($t): string => is_string($t) ? trim($t) : '')
                    ->filter()
                    ->unique(fn (string $t): string => mb_strtolower($t))
                    ->values()
                    ->all(),
            ]);
        }
    }
}
