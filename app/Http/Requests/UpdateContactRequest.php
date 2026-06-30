<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContactFunction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates input for updating a contact person.
 *
 * Mirrors StoreContactRequest: any number of labelled emails and phones, with
 * blank repeater rows stripped before validation. Authorization is handled by
 * ContactPolicy in the controller.
 */
class UpdateContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove blank email/phone rows before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'emails' => $this->cleanRows($this->input('emails'), 'email'),
            'phones' => $this->cleanRows($this->input('phones'), 'phone'),
        ]);
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
            'function' => ['required', Rule::enum(ContactFunction::class)],
            'emails' => ['array'],
            'emails.*.label' => ['required', 'string', 'max:50'],
            'emails.*.email' => ['required', 'email', 'max:255'],
            'phones' => ['array'],
            'phones.*.label' => ['required', 'string', 'max:50'],
            'phones.*.phone' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * Drop rows whose value field is blank.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cleanRows(mixed $rows, string $valueKey): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            static fn ($row): bool => is_array($row) && filled($row[$valueKey] ?? null),
        ));
    }
}
