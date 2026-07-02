<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a folder (name and optional parent).
 */
class StoreFolderRequest extends FormRequest
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
            // Either a plaintext name or, for a zero-knowledge vault, an encrypted one.
            'name' => ['required_without:enc_name', 'nullable', 'string', 'max:255'],
            'enc_name' => ['required_without:name', 'nullable', 'string', 'max:1024'],
            'parent_id' => ['nullable', 'integer', Rule::exists('folders', 'id')],
        ];
    }
}
