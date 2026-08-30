<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\Sources\DiskArchiveSource;
use App\Services\Backup\Sources\InvoiceBlobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Task 16: the finance-v2 module's immutable invoice/quote revision PDFs
 * live under "finance/revisions/" on the files disk — a different root than
 * the legacy "invoices/" prefix InvoiceBlobSourceTest already covers.
 * Without archiving both prefixes, every invoice finalized through the new
 * module would be GoBD-relevant and completely unbacked-up.
 */
class FinanceInvoiceBlobSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_archives_both_the_legacy_and_finance_v2_revision_prefixes_in_one_tar(): void
    {
        Storage::fake(config('files.disk'));
        Storage::disk(config('files.disk'))->put('invoices/legacy.pdf', '%PDF-1.4 legacy invoice');
        Storage::disk(config('files.disk'))->put('finance/revisions/ab/'.hash('sha256', 'x').'.pdf', '%PDF-1.4 finance-v2 revision');
        Storage::disk(config('files.disk'))->put('other/unrelated.txt', 'must not be archived');

        $workDir = sys_get_temp_dir().'/finance-blobsrc-'.bin2hex(random_bytes(4));
        mkdir($workDir, 0700, true);

        try {
            $artifact = app(InvoiceBlobSource::class)->build($workDir);
            $this->assertFileExists($artifact->path);

            $listing = new Process(['tar', '-tzf', $artifact->path]);
            $listing->mustRun();
            $members = $listing->getOutput();

            $this->assertStringContainsString('invoices/legacy.pdf', $members);
            $this->assertStringContainsString('finance/revisions/', $members);
            $this->assertStringNotContainsString('unrelated.txt', $members);
        } finally {
            exec('rm -rf '.escapeshellarg($workDir));
        }
    }

    public function test_a_source_with_no_additional_prefixes_is_unaffected(): void
    {
        // The extension point defaults to an empty list, so every other
        // DiskArchiveSource subclass keeps archiving exactly its one prefix.
        $source = new class extends DiskArchiveSource
        {
            protected function prefix(): string
            {
                return 'only-prefix';
            }

            protected function name(): string
            {
                return 'only-prefix-source';
            }
        };

        Storage::fake(config('files.disk'));
        Storage::disk(config('files.disk'))->put('only-prefix/file.txt', 'kept');
        Storage::disk(config('files.disk'))->put('elsewhere/file.txt', 'not archived');

        $workDir = sys_get_temp_dir().'/single-prefix-'.bin2hex(random_bytes(4));
        mkdir($workDir, 0700, true);

        try {
            $artifact = $source->build($workDir);
            $listing = new Process(['tar', '-tzf', $artifact->path]);
            $listing->mustRun();
            $members = $listing->getOutput();

            $this->assertStringContainsString('only-prefix/file.txt', $members);
            $this->assertStringNotContainsString('elsewhere/file.txt', $members);
        } finally {
            exec('rm -rf '.escapeshellarg($workDir));
        }
    }
}
