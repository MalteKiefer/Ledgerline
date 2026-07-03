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
    case VECTOR = 'VECTOR';
    case VIDEO = 'VIDEO';
    case AUDIO = 'AUDIO';
    case PDF = 'PDF';
    case DOCUMENT = 'DOCUMENT';
    case EBOOK = 'EBOOK';
    case SPREADSHEET = 'SPREADSHEET';
    case PRESENTATION = 'PRESENTATION';
    case ARCHIVE = 'ARCHIVE';
    case DISK = 'DISK';
    case CODE = 'CODE';
    case TEXT = 'TEXT';
    case FONT = 'FONT';
    case OTHER = 'OTHER';

    /**
     * Human-readable, English label.
     */
    public function label(): string
    {
        return __('enums.file_type.'.$this->name);
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
     *
     * MIME-only (server has no filename here). The client's richer counterpart
     * is fileCategory() in resources/js/app.js, which also uses the file
     * extension — keep the two category sets in sync.
     */
    public static function fromMime(string $mime): self
    {
        $mime = strtolower($mime);

        return match (true) {
            $mime === 'image/svg+xml' => self::VECTOR,
            Str::startsWith($mime, 'image/') => self::IMAGE,
            Str::startsWith($mime, 'video/') => self::VIDEO,
            Str::startsWith($mime, 'audio/') => self::AUDIO,
            Str::startsWith($mime, 'font/') => self::FONT,
            $mime === 'application/pdf' => self::PDF,
            in_array($mime, ['application/epub+zip', 'application/x-mobipocket-ebook'], true) => self::EBOOK,
            in_array($mime, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet',
                'text/csv',
                'application/csv',
            ], true) => self::SPREADSHEET,
            in_array($mime, [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.oasis.opendocument.presentation',
            ], true) => self::PRESENTATION,
            in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.text',
                'application/rtf',
            ], true) => self::DOCUMENT,
            in_array($mime, [
                'application/json',
                'application/xml',
                'text/html',
                'text/css',
                'application/javascript',
                'text/javascript',
                'application/x-sh',
                'application/x-httpd-php',
                'application/x-python',
            ], true) => self::CODE,
            in_array($mime, ['text/plain', 'text/markdown'], true) => self::TEXT,
            in_array($mime, ['application/x-iso9660-image', 'application/x-apple-diskimage'], true) => self::DISK,
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
