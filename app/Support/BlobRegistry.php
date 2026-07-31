<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FileBlob;
use App\Models\GalleryBlob;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry mapping module keys to their blob model and disk prefix.
 * Single source of truth for gallery→GalleryBlob and files→FileBlob.
 */
final class BlobRegistry
{
    /** @var array<string, array{model: class-string<Model>, prefix: string}> */
    private const MAP = [
        'gallery' => ['model' => GalleryBlob::class, 'prefix' => 'gallery'],
        'files' => ['model' => FileBlob::class,    'prefix' => 'files'],
    ];

    /** @return class-string<Model> */
    public static function model(string $module): string
    {
        return self::MAP[$module]['model']
            ?? throw new \InvalidArgumentException("Unknown blob module: {$module}");
    }

    public static function prefix(string $module): string
    {
        return self::MAP[$module]['prefix']
            ?? throw new \InvalidArgumentException("Unknown blob module: {$module}");
    }

    /**
     * All registered module keys.
     *
     * @return list<string>
     */
    public static function modules(): array
    {
        return array_keys(self::MAP);
    }

    private function __construct() {}
}
