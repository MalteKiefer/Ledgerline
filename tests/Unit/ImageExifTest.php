<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Files\ImageExif;
use PHPUnit\Framework\TestCase;

class ImageExifTest extends TestCase
{
    public function test_it_converts_gps_exif_to_decimal_degrees(): void
    {
        $data = ['GPS' => [
            'GPSLatitude' => ['48/1', '8/1', '30/1'],
            'GPSLatitudeRef' => 'N',
            'GPSLongitude' => ['11/1', '30/1', '0/1'],
            'GPSLongitudeRef' => 'E',
        ]];

        $gps = new ImageExif()->gpsFromExifArray($data);

        $this->assertNotNull($gps);
        $this->assertEqualsWithDelta(48.141667, $gps[0], 0.0001);
        $this->assertEqualsWithDelta(11.5, $gps[1], 0.0001);
    }

    public function test_south_and_west_are_negative(): void
    {
        $data = ['GPS' => [
            'GPSLatitude' => ['33/1', '52/1', '0/1'],
            'GPSLatitudeRef' => 'S',
            'GPSLongitude' => ['70/1', '40/1', '0/1'],
            'GPSLongitudeRef' => 'W',
        ]];

        $gps = new ImageExif()->gpsFromExifArray($data);

        $this->assertLessThan(0, $gps[0]);
        $this->assertLessThan(0, $gps[1]);
    }

    public function test_it_returns_null_without_gps(): void
    {
        $this->assertNull(new ImageExif()->gpsFromExifArray(['IFD0' => ['Make' => 'Canon']]));
    }
}
