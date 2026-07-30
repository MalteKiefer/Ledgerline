<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry mapping module keys to their bytes-owning model and disk prefix.
 * Both entries now point at the plaintext-relational cores (gallery→GalleryPhoto,
 * files→FileEntry; bytes at gallery/<uuid> and files/<uuid>). They serve only the
 * legacy public-share consumption path in PublicShareController, which is
 * deferred to the final crypto-core removal release (share creation was removed
 * in the pivot and the viewer will be rebuilt then) — the zero-knowledge blob
 * ledgers (GalleryBlob / FileBlob) they used to reference are gone.
 */
final class BlobRegistry
{
    /** @var array<string, array{model: class-string<Model>, prefix: string}> */
    private const MAP = [
        'gallery' => ['model' => GalleryPhoto::class, 'prefix' => 'gallery'],
        'files' => ['model' => FileEntry::class,     'prefix' => 'files'],
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
