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
        $this->merge([
            'calendar_week_numbers' => $this->boolean('calendar_week_numbers'),
            'calendar_birthdays_enabled' => $this->boolean('calendar_birthdays_enabled'),
            'calendar_anniversaries_enabled' => $this->boolean('calendar_anniversaries_enabled'),
        ]);
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
            'calendar_birthdays_enabled' => ['boolean'],
            'calendar_anniversaries_enabled' => ['boolean'],
        ];
    }
}
