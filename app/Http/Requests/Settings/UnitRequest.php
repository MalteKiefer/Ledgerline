<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a unit type (code, multilingual labels, ZUGFeRD code).
 */
class UnitRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('units', 'code')->ignore($this->route('unit')),
            ],
            'name_de' => ['required', 'string', 'max:50'],
            'name_en' => ['required', 'string', 'max:50'],
            'zugferd_code' => ['required', 'string', 'max:8'],
        ];
    }
}
