<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use Throwable;

/**
 * Crops a detected face out of an image into a small square JPEG thumbnail.
 * Boxes arrive normalised (0..1); a little padding is added around the face.
 */
class FaceCropper
{
    private const SIZE = 256;

    private const PAD = 0.15;

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $box  normalised x1,y1,x2,y2
     */
    public function crop(string $path, array $box): ?string
    {
        if (! extension_loaded('imagick')) {
            return null;
        }

        try {
            $img = new \Imagick($path);
            $w = $img->getImageWidth();
            $h = $img->getImageHeight();

            [$x1, $y1, $x2, $y2] = $box;
            $bw = ($x2 - $x1) * $w;
            $bh = ($y2 - $y1) * $h;
            $padX = $bw * self::PAD;
            $padY = $bh * self::PAD;

            $cropX = (int) max(0, $x1 * $w - $padX);
            $cropY = (int) max(0, $y1 * $h - $padY);
            $cropW = (int) min($w - $cropX, $bw + 2 * $padX);
            $cropH = (int) min($h - $cropY, $bh + 2 * $padY);
            if ($cropW < 1 || $cropH < 1) {
                $img->clear();

                return null;
            }

            $img->cropImage($cropW, $cropH, $cropX, $cropY);
            $img->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            // Square cover crop.
            $img->cropThumbnailImage(self::SIZE, self::SIZE);
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality(82);
            $bytes = $img->getImagesBlob();
            $img->clear();

            return $bytes;
        } catch (Throwable) {
            return null;
        }
    }
}
