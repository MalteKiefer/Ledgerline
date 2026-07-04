<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use Throwable;

/**
 * 64-bit difference hash (dHash) for near-identical image detection. The image is
 * reduced to a 9×8 grayscale grid and each pixel compared with its right
 * neighbour, giving 64 bits that survive resizing, recompression and format
 * changes. Uses Imagick when available (also decodes HEIC), else GD.
 */
class PerceptualHash
{
    /** Returns the hash as a 64-bit integer, or null if the image can't be read. */
    public function hash(string $path): ?int
    {
        $grid = $this->grayGrid($path);
        if ($grid === null) {
            return null;
        }

        $hash = 0;
        $bit = 0;
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                $left = $grid[$row * 9 + $col];
                $right = $grid[$row * 9 + $col + 1];
                if ($left > $right) {
                    $hash |= (1 << $bit);
                }
                $bit++;
            }
        }

        return $hash;
    }

    /** Hamming distance between two 64-bit hashes (0 = identical). */
    public function hamming(int $a, int $b): int
    {
        $x = $a ^ $b;

        return $this->popcount32($x & 0xFFFFFFFF) + $this->popcount32(($x >> 32) & 0xFFFFFFFF);
    }

    /**
     * A 9×8 grid of grayscale intensities (0–255), row-major.
     *
     * @return array<int, int>|null
     */
    private function grayGrid(string $path): ?array
    {
        if (extension_loaded('imagick')) {
            try {
                $img = new \Imagick($path);
                $img->setImageColorspace(\Imagick::COLORSPACE_GRAY);
                $img->resizeImage(9, 8, \Imagick::FILTER_LANCZOS, 1);
                /** @var array<int, int> $pixels */
                $pixels = $img->exportImagePixels(0, 0, 9, 8, 'I', \Imagick::PIXEL_CHAR);
                $img->clear();

                return count($pixels) === 72 ? array_map('intval', $pixels) : null;
            } catch (Throwable) {
                // fall through to GD
            }
        }

        try {
            $src = @imagecreatefromstring((string) file_get_contents($path));
            if ($src === false) {
                return null;
            }
            $small = imagecreatetruecolor(9, 8);
            imagecopyresampled($small, $src, 0, 0, 0, 0, 9, 8, imagesx($src), imagesy($src));
            imagedestroy($src);

            $grid = [];
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 9; $x++) {
                    $rgb = imagecolorat($small, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Rec. 601 luma.
                    $grid[] = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                }
            }
            imagedestroy($small);

            return $grid;
        } catch (Throwable) {
            return null;
        }
    }

    private function popcount32(int $v): int
    {
        $v = $v - (($v >> 1) & 0x55555555);
        $v = ($v & 0x33333333) + (($v >> 2) & 0x33333333);
        $v = ($v + ($v >> 4)) & 0x0F0F0F0F;

        return (int) (($v * 0x01010101) >> 24) & 0x3F;
    }
}
