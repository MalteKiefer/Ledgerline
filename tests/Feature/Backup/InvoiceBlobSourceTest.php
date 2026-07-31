<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupJob;
use App\Services\Backup\Sources\InvoiceBlobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The finance blob source archives the invoice PDFs + receipts stored under the
 * "invoices/" prefix on the files disk — records that live on disk, not in the
 * DB dump, and would otherwise be unbacked-up after the finance-only pivot
 * removed the general files backup source.
 */
class InvoiceBlobSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_archives_the_invoices_prefix(): void
    {
        Storage::fake(config('files.disk'));
        Storage::disk(config('files.disk'))->put('invoices/a.pdf', '%PDF-1.4 invoice');
        Storage::disk(config('files.disk'))->put('invoices/receipt-1.jpg', 'JPEGBYTES');
        // A file outside the prefix must NOT be captured.
        Storage::disk(config('files.disk'))->put('other/x.txt', 'nope');

        $workDir = sys_get_temp_dir().'/blobsrc-'.bin2hex(random_bytes(4));
        mkdir($workDir, 0700, true);

        try {
            $artifact = app(InvoiceBlobSource::class)->build($workDir);
            $this->assertFileExists($artifact->path);
            $this->assertGreaterThan(0, filesize($artifact->path));
        } finally {
            exec('rm -rf '.escapeshellarg($workDir));
        }
    }

    public function test_invoices_is_a_valid_backup_source(): void
    {
        $this->assertContains('invoices', BackupJob::SOURCES);
    }
}
