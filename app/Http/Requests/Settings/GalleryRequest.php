<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the gallery settings (trip thresholds, filename template, map zoom).
 */
class GalleryRequest extends FormRequest
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
            'gallery_trip_gap_days' => ['required', 'integer', 'min:1', 'max:60'],
            'gallery_trip_radius_km' => ['required', 'integer', 'min:1', 'max:5000'],
            'gallery_filename_template' => ['nullable', 'string', 'max:255'],
            'gallery_map_zoom' => ['required', 'integer', 'min:1', 'max:19'],
        ];
    }
}
