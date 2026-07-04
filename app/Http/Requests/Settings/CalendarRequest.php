<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the calendar display/behaviour settings (week start, week numbers,
 * default event duration).
 */
class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Unchecked checkboxes are absent from the payload; normalise to false.
        $this->merge(['calendar_week_numbers' => $this->boolean('calendar_week_numbers')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'calendar_week_start' => ['required', Rule::in(['monday', 'sunday'])],
            'calendar_week_numbers' => ['boolean'],
            'calendar_default_event_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ];
    }
}
