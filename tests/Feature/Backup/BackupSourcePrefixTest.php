<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Http\Controllers\ContactBlobController;
use App\Http\Controllers\ExploreBlobController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InvoiceBlobController;
use App\Http\Controllers\MailBlobController;
use App\Http\Controllers\NoteBlobController;
use App\Http\Controllers\PasswordBlobController;
use App\Http\Controllers\SharedFolderBlobController;
use App\Models\BackupJob;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\MirrorableSource;
use App\Services\Backup\Sources\ModuleBlobSource;
use App\Support\BlobRegistry;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A backup mirror uploads the disk prefix a source names; blobs are written to
 * the prefix the blob controller's module() returns. If those drift, the mirror
 * scans an empty prefix and silently backs up nothing — this locks them together.
 */
class BackupSourcePrefixTest extends TestCase
{
    private function protectedString(object $o, string $method): string
    {
        $m = new ReflectionMethod($o, $method);

        return (string) $m->invoke($o);
    }

    public function test_source_prefixes_match_the_blob_controller_modules(): void
    {
        $this->assertSame(
            $this->protectedString(new FileController, 'module'),
            $this->protectedString(new FilesSource, 'prefix'),
            'FilesSource must mirror the same disk prefix FileController writes to.',
        );

        // Guard the concrete values too, so a rename of both in lockstep still trips.
        $this->assertSame('files', $this->protectedString(new FileController, 'module'));
        $this->assertSame('contacts', $this->protectedString(new ContactBlobController, 'module'));
    }

    /**
     * Every registered blob prefix MUST be backed up, otherwise a database-only
     * restore points at ciphertext that no source ever captured — total loss of
     * that module's content (the C1 finding). Locks BlobRegistry ↔ backup sources
     * ↔ blob-controller prefixes together for all eight modules.
     */
    public function test_every_blob_module_has_a_backup_source_with_a_matching_prefix(): void
    {
        $controllers = [
            'files' => FileController::class,
            'notes' => NoteBlobController::class,
            'passwords' => PasswordBlobController::class,
            'invoices' => InvoiceBlobController::class,
            'mail' => MailBlobController::class,
            'contacts' => ContactBlobController::class,
            'explore' => ExploreBlobController::class,
            'shared-folders' => SharedFolderBlobController::class,
        ];

        $resolve = new ReflectionMethod(BackupManager::class, 'source');

        foreach (BlobRegistry::modules() as $module) {
            // Selectable as a backup job source.
            $this->assertContains($module, BackupJob::SOURCES, "Module '{$module}' is not a backup source — its blobs would never be backed up.");

            // A blob controller exists whose module() prefix matches the registry.
            $this->assertArrayHasKey($module, $controllers, "No blob controller mapped for module '{$module}'.");
            $this->assertSame(
                $this->protectedString(new $controllers[$module], 'module'),
                BlobRegistry::prefix($module),
                "Backup prefix for '{$module}' must equal the blob controller's module().",
            );

            // BackupManager resolves it to a mirrorable source on the same prefix.
            $src = $resolve->invoke(app(BackupManager::class), $module);
            $this->assertInstanceOf(MirrorableSource::class, $src);
            $this->assertSame(BlobRegistry::prefix($module), $src->diskPrefix());
        }
    }

    public function test_module_blob_source_rejects_an_unknown_module(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ModuleBlobSource('not-a-real-module');
    }
}
