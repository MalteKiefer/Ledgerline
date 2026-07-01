<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a file upload from the team-wide files overview, where the target
 * customer or project is chosen in the form (as "customer:<id>" or
 * "project:<id>"). The target is resolved and team-authorised in the controller.
 */
class StoreTeamFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
            'attachable' => ['required', 'string', 'regex:/^(customer|project):\d+$/'],
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
