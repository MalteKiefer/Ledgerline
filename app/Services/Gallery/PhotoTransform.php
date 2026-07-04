<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\Photo;
use Intervention\Image\Direction;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Applies a photo's stored non-destructive edits (clockwise rotation + horizontal
 * flip) to a decoded image. Shared by rendition generation and edited export.
 */
class PhotoTransform
{
    public function applyEdits(ImageInterface $image, Photo $photo): ImageInterface
    {
        $rotation = ((int) $photo->rotation) % 360;
        if ($rotation !== 0) {
            // Intervention rotates counter-clockwise; negate for clockwise.
            $image->rotate(360 - $rotation);
        }
        if ($photo->flipped) {
            $image->flip(Direction::HORIZONTAL);
        }

        return $image;
    }
}
