<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a file upload and its optional tags.
 *
 * Authorization is handled by FilePolicy / the team scope on the owning record
 * in the controller.
 */
class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim, drop empty and de-duplicate tag values before validation.
     */
    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_array($tags)) {
            $this->merge([
                'tags' => collect($tags)
                    ->map(fn ($tag): string => is_string($tag) ? trim($tag) : '')
                    ->filter()
                    ->unique(fn (string $tag): string => mb_strtolower($tag))
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
        $maxKilobytes = (int) config('files.max_upload_mb', 2048) * 1024;

        return [
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
