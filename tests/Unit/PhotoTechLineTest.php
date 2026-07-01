<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Photo;
use Tests\TestCase;

class PhotoTechLineTest extends TestCase
{
    public function test_duration_is_formatted_for_humans(): void
    {
        $photo = new Photo(['duration' => 42]);
        $this->assertSame('0:42', $photo->durationForHumans());

        $photo = new Photo(['duration' => 3725]);
        $this->assertSame('1:02:05', $photo->durationForHumans());

        $this->assertNull((new Photo(['duration' => 0]))->durationForHumans());
    }

    public function test_video_tech_line_shows_frame_rate_and_codec(): void
    {
        $photo = new Photo([
            'media_type' => 'video',
            'metadata' => ['streams' => [['codec_type' => 'video', 'r_frame_rate' => '60/1', 'codec_name' => 'hevc']]],
        ]);

        $this->assertSame('60 fps · HEVC', $photo->techLine());
    }

    public function test_image_tech_line_shows_exposure_details(): void
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

        $this->assertSame('24mm · f/1.7 · 1/459s · ISO 25', $photo->techLine());
    }
}
