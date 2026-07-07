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
     * Accept a comma as the decimal separator for the location grid, so a value
     * like "2,5" entered on a German keyboard is treated as 2.5.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('gallery_geocode_grid_km')) {
            $this->merge([
                'gallery_geocode_grid_km' => str_replace(',', '.', (string) $this->input('gallery_geocode_grid_km')),
            ]);
        }
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
            'gallery_max_upload_mb' => ['required', 'integer', 'min:1', 'max:5120'],
            'gallery_video_frame' => ['required', 'integer', 'min:0', 'max:600'],
            'gallery_geocode_grid_km' => ['required', 'numeric', 'min:0', 'max:100'],

            // ML + face recognition (nullable = use the built-in default).
            'gallery_ml_enabled' => ['nullable', 'boolean'],
            'gallery_ml_url' => ['nullable', 'string', 'max:255'],
            'gallery_ml_clip_model' => ['nullable', 'string', 'max:255'],
            'gallery_face_enabled' => ['nullable', 'boolean'],
            'gallery_face_model' => ['nullable', 'string', 'max:255'],
            'gallery_ffmpeg_path' => ['nullable', 'string', 'max:1024'],
            'gallery_exiftool_path' => ['nullable', 'string', 'max:1024'],
            'gallery_duplicate_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'gallery_phash_max_distance' => ['nullable', 'integer', 'min:0', 'max:64'],
            'gallery_face_min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'gallery_face_min_size' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'gallery_face_cluster_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'gallery_face_min_per_person' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'gallery_geocode_interval_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
        ];
    }
}
