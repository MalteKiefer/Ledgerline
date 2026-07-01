<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $taken = fake()->dateTimeBetween('-1 year', 'now');
        $dir = 'photos/'.$taken->format('Y/m');

        return [
            'uuid' => $uuid,
            'name' => fake()->word().'.jpg',
            'disk_path' => "{$dir}/{$uuid}.jpg",
            'thumb_path' => "{$dir}/thumb/{$uuid}.jpg",
            'medium_path' => "{$dir}/medium/{$uuid}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50000, 5000000),
            'width' => 1920,
            'height' => 1080,
            'checksum' => hash('sha256', $uuid),
            'taken_at' => $taken,
        ];
    }
}
