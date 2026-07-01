<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Photo;
use Tests\TestCase;

class PhotoTechLineTest extends TestCase
{
    public function test_duration_is_formatted_for_humans(): void
    {
        $this->assertSame('0:42', (new Photo(['duration' => 42]))->durationForHumans());
        $this->assertSame('1:02:05', (new Photo(['duration' => 3725]))->durationForHumans());
        $this->assertNull((new Photo(['duration' => 0]))->durationForHumans());
    }

    public function test_video_getters_read_frame_rate_and_codec(): void
    {
        $photo = new Photo([
            'media_type' => 'video',
            'metadata' => ['streams' => [['codec_type' => 'video', 'r_frame_rate' => '60/1', 'codec_name' => 'hevc']]],
        ]);

        $this->assertSame('60 fps', $photo->fps());
        $this->assertSame('HEVC', $photo->codec());
    }

    public function test_image_getters_read_exposure_details(): void
    {
        $photo = new Photo([
            'media_type' => 'image',
            'metadata' => ['EXIF' => [
                'FocalLengthIn35mmFilm' => 24,
                'FNumber' => '168/100',
                'ExposureTime' => '2180/1000000',
                'ISOSpeedRatings' => 25,
            ]],
        ]);

        $this->assertSame('24 mm', $photo->focalLength());
        $this->assertSame('f/1.7', $photo->aperture());
        $this->assertSame('1/459 s', $photo->shutter());
        $this->assertSame('ISO 25', $photo->iso());
    }

    public function test_place_lines_break_down_the_address(): void
    {
        $photo = new Photo(['place_details' => [
            'road' => 'B 303',
            'hamlet' => 'Neubauer Forst-Süd',
            'county' => 'Landkreis Wunsiedel im Fichtelgebirge',
            'state' => 'Bayern',
            'postcode' => '95709',
            'country' => 'Deutschland',
        ]]);

        $this->assertSame([
            'B 303',
            '95709 Neubauer Forst-Süd',
            'Landkreis Wunsiedel im Fichtelgebirge',
            'Bayern',
            'Deutschland',
        ], $photo->placeLines());
    }

    public function test_place_lines_fall_back_to_splitting_the_display_name(): void
    {
        $photo = new Photo(['place' => 'B 303, Bayern, Deutschland']);

        $this->assertSame(['B 303', 'Bayern', 'Deutschland'], $photo->placeLines());
    }

    public function test_getters_are_null_without_metadata(): void
    {
        $photo = new Photo(['media_type' => 'image']);

        $this->assertNull($photo->aperture());
        $this->assertNull($photo->fps());
    }
}
