<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FileType;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).'.pdf',
            'disk_path' => 'files/'.Str::uuid(),
            'mime_type' => 'application/pdf',
            'type' => FileType::PDF,
            'size' => fake()->numberBetween(1000, 5_000_000),
            'checksum' => hash('sha256', (string) Str::uuid()),
            'is_encrypted' => false,
        ];
    }
}
