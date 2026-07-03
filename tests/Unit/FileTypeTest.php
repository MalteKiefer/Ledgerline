<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\FileType;
use Tests\TestCase;

class FileTypeTest extends TestCase
{
    public function test_it_detects_categories_from_mime(): void
    {
        $this->assertSame(FileType::IMAGE, FileType::fromMime('image/png'));
        $this->assertSame(FileType::VECTOR, FileType::fromMime('image/svg+xml'));
        $this->assertSame(FileType::VIDEO, FileType::fromMime('video/mp4'));
        $this->assertSame(FileType::AUDIO, FileType::fromMime('audio/mpeg'));
        $this->assertSame(FileType::PDF, FileType::fromMime('application/pdf'));
        $this->assertSame(FileType::SPREADSHEET, FileType::fromMime('text/csv'));
        $this->assertSame(FileType::PRESENTATION, FileType::fromMime('application/vnd.ms-powerpoint'));
        $this->assertSame(FileType::TEXT, FileType::fromMime('text/plain'));
        $this->assertSame(FileType::CODE, FileType::fromMime('application/json'));
        $this->assertSame(FileType::FONT, FileType::fromMime('font/woff2'));
        $this->assertSame(FileType::EBOOK, FileType::fromMime('application/epub+zip'));
        $this->assertSame(FileType::ARCHIVE, FileType::fromMime('application/zip'));
        $this->assertSame(FileType::OTHER, FileType::fromMime('application/octet-stream'));
    }

    public function test_text_extractable_detection(): void
    {
        $this->assertTrue(FileType::DOCUMENT->isTextExtractable('text/plain'));
        $this->assertTrue(FileType::SPREADSHEET->isTextExtractable('text/csv'));
        $this->assertFalse(FileType::PDF->isTextExtractable('application/pdf'));
        $this->assertFalse(FileType::ARCHIVE->isTextExtractable('application/zip'));
    }

    public function test_labels_and_options(): void
    {
        foreach (FileType::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }

        $this->assertCount(count(FileType::cases()), FileType::options());
    }
}
