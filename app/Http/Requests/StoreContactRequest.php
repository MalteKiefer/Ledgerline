<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContactFunction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates input for creating a contact person.
 *
 * The function must be one of the fixed ContactFunction enum cases; free text
 * is rejected. Authorization is handled by ContactPolicy in the controller.
 */
class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'function' => ['required', Rule::enum(ContactFunction::class)],
        ];
    }
}
