<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * A coarse, user-facing category for an uploaded file, derived from its MIME
 * type. Stored as the backing value; the label is for display and filtering.
 */
enum FileType: string
{
    case IMAGE = 'IMAGE';
    case PDF = 'PDF';
    case DOCUMENT = 'DOCUMENT';
    case SPREADSHEET = 'SPREADSHEET';
    case ARCHIVE = 'ARCHIVE';
    case OTHER = 'OTHER';

    /**
     * Human-readable, English label.
     */
    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::PDF => 'PDF',
            self::DOCUMENT => 'Document',
            self::SPREADSHEET => 'Spreadsheet',
            self::ARCHIVE => 'Archive',
            self::OTHER => 'Other',
        };
    }

    /**
     * Whether this category's text content can be extracted for search when the
     * file is not encrypted (plain-text and CSV/JSON here; richer formats are a
     * future extension).
     */
    public function isTextExtractable(string $mime): bool
    {
        return Str::startsWith($mime, 'text/')
            || in_array($mime, ['application/json', 'application/csv'], true);
    }

    /**
     * Detect the category from a MIME type.
     */
    public static function fromMime(string $mime): self
    {
        $mime = strtolower($mime);

        return match (true) {
            Str::startsWith($mime, 'image/') => self::IMAGE,
            $mime === 'application/pdf' => self::PDF,
            in_array($mime, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet',
                'text/csv',
                'application/csv',
            ], true) => self::SPREADSHEET,
            in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.text',
                'application/rtf',
                'text/plain',
                'text/markdown',
            ], true) => self::DOCUMENT,
            in_array($mime, [
                'application/zip',
                'application/x-tar',
                'application/gzip',
                'application/x-7z-compressed',
                'application/x-rar-compressed',
                'application/vnd.rar',
            ], true) => self::ARCHIVE,
            default => self::OTHER,
        };
    }

    /**
     * All cases as value/label pairs for filters.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
