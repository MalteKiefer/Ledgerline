<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\User;
use App\Services\Files\FileTextIndex;
use App\Support\BinaryProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileContentSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /** Stage bytes on the file disk and create an owned FileEntry pointing at them. */
    private function makeFile(User $user, string $name, string $mime, string $contents): FileEntry
    {
        $path = 'files/'.md5($name.$contents);
        Storage::disk(config('files.disk'))->put($path, $contents);

        $file = new FileEntry;
        $file->forceFill([
            'user_id' => $user->id,
            'name' => $name,
            'mime' => $mime,
            'size' => strlen($contents),
            'storage_path' => $path,
        ]);
        $file->save();

        return $file;
    }

    public function test_extract_reads_plain_text(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user, 'notes.txt', 'text/plain', 'The quick brown fox jumps.');

        $text = (new FileTextIndex)->extract($file);

        $this->assertNotNull($text);
        $this->assertStringContainsString('quick brown fox', (string) $text);
    }

    public function test_extract_reads_markdown_and_json(): void
    {
        $user = User::factory()->create();

        $md = $this->makeFile($user, 'doc.md', 'text/markdown', "# Title\n\nsome **bold** content here");
        $this->assertStringContainsString('some', (string) (new FileTextIndex)->extract($md));

        $json = $this->makeFile($user, 'data.json', 'application/json', '{"invoice":"acme-2026","total":42}');
        $this->assertStringContainsString('acme-2026', (string) (new FileTextIndex)->extract($json));
    }

    public function test_extract_returns_null_for_unindexable_mime(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user, 'clip.mp4', 'video/mp4', 'not really a video');

        $this->assertNull((new FileTextIndex)->extract($file));
    }

    public function test_extract_returns_null_when_bytes_missing(): void
    {
        $user = User::factory()->create();
        $file = new FileEntry;
        $file->forceFill([
            'user_id' => $user->id,
            'name' => 'ghost.txt',
            'mime' => 'text/plain',
            'size' => 5,
            'storage_path' => 'files/does-not-exist',
        ]);
        $file->save();

        $this->assertNull((new FileTextIndex)->extract($file));
    }

    public function test_observer_indexes_plain_text_on_create(): void
    {
        // Queue is sync in tests, so the observer's dispatched indexing job runs
        // inline during create; search_text is populated by the time we refresh.
        $user = User::factory()->create();
        $file = $this->makeFile($user, 'readme.txt', 'text/plain', 'searchable haystack needle inside');

        $file->refresh();

        $this->assertNotNull($file->indexed_at);
        $this->assertNotNull($file->search_text);
        $this->assertStringContainsString('needle', (string) $file->search_text);
    }

    public function test_observer_reindexes_when_bytes_change(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user, 'log.txt', 'text/plain', 'first revision alpha');
        $file->refresh();
        $this->assertStringContainsString('alpha', (string) $file->search_text);

        // New bytes at a new path → storage_path + sha256 change → re-index.
        $newPath = 'files/'.md5('v2');
        Storage::disk(config('files.disk'))->put($newPath, 'second revision omega');
        $file->forceFill([
            'storage_path' => $newPath,
            'sha256' => hash('sha256', 'second revision omega'),
        ])->save();

        $file->refresh();
        $this->assertStringContainsString('omega', (string) $file->search_text);
    }

    public function test_pdf_extraction_reads_text_layer_when_poppler_present(): void
    {
        if (! BinaryProcess::available('pdftotext')) {
            $this->markTestSkipped('pdftotext (poppler-utils) not installed.');
        }

        $user = User::factory()->create();
        $file = $this->makeFile($user, 'invoice.pdf', 'application/pdf', $this->minimalPdf('LEDGERLINE_MARKER'));

        $text = (new FileTextIndex)->extract($file);

        $this->assertNotNull($text);
        $this->assertStringContainsString('LEDGERLINE_MARKER', (string) $text);
    }

    /** A minimal, well-formed single-page PDF with one text-showing operator. */
    private function minimalPdf(string $word): string
    {
        $stream = "BT /F1 18 Tf 20 40 Td ({$word}) Tj ET";
        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 100] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[$i] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $count = count($objs) + 1;
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 ".$count."\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xrefPos."\n%%EOF";

        return $pdf;
    }
}
