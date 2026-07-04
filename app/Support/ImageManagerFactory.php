<?php

declare(strict_types=1);

namespace App\Support;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Single source of Intervention driver selection: Imagick when the extension is
 * loaded (HEIC/AVIF capable), else GD. Avoids re-expressing the probe everywhere.
 */
class ImageManagerFactory
{
    public function make(): ImageManager
    {
        return new ImageManager($this->hasImagick() ? new ImagickDriver : new GdDriver);
    }

    public function hasImagick(): bool
    {
        return extension_loaded('imagick');
    }
}
