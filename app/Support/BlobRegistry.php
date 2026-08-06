<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ContactBlob;
use App\Models\ExploreBlob;
use App\Models\FileBlob;
use App\Models\InvoiceBlob;
use App\Models\MailBlob;
use App\Models\NoteBlob;
use App\Models\PasswordBlob;
use App\Models\SharedFolderBlob;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry mapping module keys to their blob model and disk prefix.
 * Single source of truth for every zero-knowledge blob prefix — used by the
 * blob controllers, the orphan sweeps, and the backup mirror sources. Every
 * prefix here MUST have a backup source (see BlobRegistry::modules() ↔
 * BackupJob::SOURCES), otherwise its ciphertext is not captured by any backup.
 */
final class BlobRegistry
{
    /** @var array<string, array{model: class-string<Model>, prefix: string}> */
    private const MAP = [
        'files' => ['model' => FileBlob::class,    'prefix' => 'files'],
        'notes' => ['model' => NoteBlob::class,   'prefix' => 'notes'],
        'passwords' => ['model' => PasswordBlob::class, 'prefix' => 'passwords'],
        'invoices' => ['model' => InvoiceBlob::class, 'prefix' => 'invoices'],
        'mail' => ['model' => MailBlob::class,     'prefix' => 'mail'],
        'contacts' => ['model' => ContactBlob::class, 'prefix' => 'contacts'],
        'explore' => ['model' => ExploreBlob::class, 'prefix' => 'explore'],
        'shared-folders' => ['model' => SharedFolderBlob::class, 'prefix' => 'shared-folders'],
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
