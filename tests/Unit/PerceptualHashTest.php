<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Gallery\PerceptualHash;
use PHPUnit\Framework\TestCase;

class PerceptualHashTest extends TestCase
{
    private function jpeg(callable $paint): string
    {
        $img = imagecreatetruecolor(64, 48);
        $paint($img);
        $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
        imagejpeg($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function test_hamming_counts_differing_bits(): void
    {
        $h = new PerceptualHash;

        $this->assertSame(0, $h->hamming(0, 0));
        $this->assertSame(1, $h->hamming(0, 1));
        $this->assertSame(3, $h->hamming(0b1011, 0b0000));
        $this->assertSame(0, $h->hamming(-1, -1));
        $this->assertSame(64, $h->hamming(0, -1)); // all 64 bits differ
    }

    public function test_identical_images_hash_the_same(): void
    {
        $h = new PerceptualHash;
        $a = $this->jpeg(fn ($img) => imagefilledrectangle($img, 0, 0, 63, 47, imagecolorallocate($img, 30, 90, 160)));
        // Same content, re-encoded independently.
        $b = $this->jpeg(fn ($img) => imagefilledrectangle($img, 0, 0, 63, 47, imagecolorallocate($img, 30, 90, 160)));

        $ha = $h->hash($a);
        $hb = $h->hash($b);
        @unlink($a);
        @unlink($b);

        $this->assertNotNull($ha);
        $this->assertNotNull($hb);
        $this->assertLessThanOrEqual(2, $h->hamming($ha, $hb));
    }

    public function test_different_images_hash_differently(): void
    {
        $h = new PerceptualHash;
        // Opposing horizontal gradients: dHash compares left vs right neighbours,
        // so a left→right ramp and its mirror produce near-opposite hashes.
        $a = $this->jpeg(function ($img): void {
            for ($x = 0; $x < 64; $x++) {
                $v = (int) round($x / 63 * 255);
                imagefilledrectangle($img, $x, 0, $x, 47, imagecolorallocate($img, $v, $v, $v));
            }
        });
        $b = $this->jpeg(function ($img): void {
            for ($x = 0; $x < 64; $x++) {
                $v = 255 - (int) round($x / 63 * 255);
                imagefilledrectangle($img, $x, 0, $x, 47, imagecolorallocate($img, $v, $v, $v));
            }
        });

        $ha = $h->hash($a);
        $hb = $h->hash($b);
        @unlink($a);
        @unlink($b);

        $this->assertGreaterThan(8, $h->hamming($ha, $hb));
    }
}
