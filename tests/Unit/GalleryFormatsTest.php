<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Gallery\GalleryFormats;
use PHPUnit\Framework\TestCase;

class GalleryFormatsTest extends TestCase
{
    /** A GalleryFormats with decode capability forced on/off, no real Imagick. */
    private function formats(bool $heic, bool $avif): GalleryFormats
    {
        return new class($heic, $avif) extends GalleryFormats
        {
            public function __construct(private bool $heicCap, private bool $avifCap) {}

            public function imagick(): bool
            {
                return true;
            }

            protected function imagickQuery(string $format): array
            {
                return match ($format) {
                    'HEIC', 'HEIF' => $this->heicCap ? [$format] : [],
                    'AVIF' => $this->avifCap ? [$format] : [],
                    default => [],
                };
            }
        };
    }

    public function test_base_image_and_video_formats_are_always_allowed(): void
    {
        $ext = $this->formats(heic: false, avif: false)->allowedExtensions();

        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov'] as $e) {
            $this->assertContains($e, $ext);
        }
        $this->assertNotContains('heic', $ext);
        $this->assertNotContains('avif', $ext);
    }

    public function test_heic_and_avif_appear_only_when_decodable(): void
    {
        $ext = $this->formats(heic: true, avif: true)->allowedExtensions();

        $this->assertContains('heic', $ext);
        $this->assertContains('heif', $ext);
        $this->assertContains('avif', $ext);
    }

    public function test_unsupported_image_detection_follows_capability(): void
    {
        $off = $this->formats(heic: false, avif: false);
        $this->assertTrue($off->isUnsupportedImage('heic', 'image/heic'));
        $this->assertTrue($off->isUnsupportedImage('', 'image/heif'));
        $this->assertTrue($off->isUnsupportedImage('avif', 'image/avif'));
        $this->assertFalse($off->isUnsupportedImage('jpg', 'image/jpeg'));

        $on = $this->formats(heic: true, avif: true);
        $this->assertFalse($on->isUnsupportedImage('heic', 'image/heic'));
        $this->assertFalse($on->isUnsupportedImage('avif', 'image/avif'));
    }

    public function test_csv_rule_is_comma_joined(): void
    {
        $csv = $this->formats(heic: false, avif: false)->allowedExtensionsCsv();

        $this->assertSame('jpg,jpeg,png,webp,gif,mp4,mov', $csv);
    }
}
