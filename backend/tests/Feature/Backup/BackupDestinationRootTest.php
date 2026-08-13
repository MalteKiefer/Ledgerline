<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\BackupDestinationFactory;
use League\Flysystem\Filesystem;
use Mockery;
use Tests\TestCase;

class BackupDestinationRootTest extends TestCase
{
    public function test_ensure_root_creates_the_target_folder_for_directory_drivers(): void
    {
        $fs = Mockery::mock(Filesystem::class);
        // The empty path resolves to the configured root prefix, which the
        // adapter then mkdir's recursively.
        $fs->shouldReceive('createDirectory')->once()->with('');

        (new BackupDestinationFactory)->ensureRoot($fs, 'sftp', ['path' => '/nested/target']);
    }

    public function test_ensure_root_is_a_noop_for_object_stores(): void
    {
        $fs = Mockery::mock(Filesystem::class);
        $fs->shouldNotReceive('createDirectory');

        // S3/B2 create keys on write; nothing to pre-create.
        (new BackupDestinationFactory)->ensureRoot($fs, 's3', ['path' => 'bucket/sub']);
    }

    public function test_ensure_root_skips_when_no_path_is_configured(): void
    {
        $fs = Mockery::mock(Filesystem::class);
        $fs->shouldNotReceive('createDirectory');

        // Empty path = the login/base directory, assumed to already exist.
        (new BackupDestinationFactory)->ensureRoot($fs, 'sftp', ['path' => '']);
    }
}
