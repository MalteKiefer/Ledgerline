<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Http\Requests\Concerns\ResolvesDefaultRate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates input for updating a project.
 *
 * Mirrors StoreProjectRequest (without changing the owning customer); the
 * unique reference check ignores the project being updated. Authorization is
 * handled by ProjectPolicy in the controller.
 */
class UpdateProjectRequest extends FormRequest
{
    use ResolvesDefaultRate;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim, drop empty and de-duplicate tag values before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeDefaultRate();

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('projects', 'reference')->ignore($this->route('project')),
            ],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'priority' => ['required', Rule::enum(ProjectPriority::class)],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'default_rate_cents' => ['nullable', 'integer', 'min:0'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
