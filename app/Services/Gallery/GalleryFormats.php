<?php

declare(strict_types=1);

namespace App\Services\Gallery;

/**
 * Single source of truth for which media formats the gallery accepts. The base
 * image/video set is always allowed; HEIC/HEIF and AVIF are only allowed when the
 * running PHP has an Imagick build that can actually decode them (libheif), so a
 * dev box on GD alone degrades gracefully instead of storing files it cannot
 * render.
 */
class GalleryFormats
{
    /** @var array<int, string> */
    private const BASE_IMAGE = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** @var array<int, string> */
    private const VIDEO = ['mp4', 'mov'];

    private ?bool $heic = null;

    private ?bool $avif = null;

    public function imagick(): bool
    {
        return extension_loaded('imagick');
    }

    public function heicSupported(): bool
    {
        return $this->heic ??= $this->imagickSupports('HEIC') || $this->imagickSupports('HEIF');
    }

    public function avifSupported(): bool
    {
        return $this->avif ??= $this->imagickSupports('AVIF');
    }

    /**
     * Extensions accepted for upload right now, given the runtime's capabilities.
     *
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        $ext = self::BASE_IMAGE;

        if ($this->heicSupported()) {
            $ext = array_merge($ext, ['heic', 'heif']);
        }

        if ($this->avifSupported()) {
            $ext[] = 'avif';
        }

        return array_merge($ext, self::VIDEO);
    }

    /**
     * The comma list for Laravel's `mimes:` rule (it matches on extension).
     */
    public function allowedExtensionsCsv(): string
    {
        return implode(',', $this->allowedExtensions());
    }

    /**
     * A HEIC/HEIF/AVIF file the current runtime cannot decode — caller should
     * skip it (report, not error) rather than store an unrenderable original.
     */
    public function isUnsupportedImage(string $extension, string $mime): bool
    {
        $extension = strtolower($extension);
        $mime = strtolower($mime);

        $heicLike = in_array($extension, ['heic', 'heif'], true)
            || str_contains($mime, 'heic') || str_contains($mime, 'heif');
        if ($heicLike) {
            return ! $this->heicSupported();
        }

        $avifLike = $extension === 'avif' || str_contains($mime, 'avif');
        if ($avifLike) {
            return ! $this->avifSupported();
        }

        return false;
    }

    private function imagickSupports(string $format): bool
    {
        if (! $this->imagick()) {
            return false;
        }

        try {
            return $this->imagickQuery($format) !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Wrap Imagick::queryFormats() so tests can override capability detection.
     *
     * @return array<int, string>
     */
    protected function imagickQuery(string $format): array
    {
        /** @var array<int, string> $formats */
        $formats = \Imagick::queryFormats($format);

        return $formats;
    }
}
