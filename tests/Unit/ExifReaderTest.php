<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Gallery\ExifReader;
use PHPUnit\Framework\TestCase;

class ExifReaderTest extends TestCase
{
    /** A representative exiftool -json -n -G dump for an iPhone HEIC Live Photo. */
    private function tags(): array
    {
        return [
            'EXIF:Make' => 'Apple',
            'EXIF:Model' => 'iPhone 15 Pro',
            'EXIF:DateTimeOriginal' => '2024:06:30 14:22:05',
            'Composite:GPSLatitude' => 49.9427,
            'Composite:GPSLongitude' => 11.5761,
            'MakerNotes:ContentIdentifier' => 'A1B2C3D4-0000-1111-2222-EEEEFFFF0000',
        ];
    }

    public function test_normalizes_date_gps_and_camera(): void
    {
        $out = (new ExifReader)->normalize($this->tags());

        $this->assertNotNull($out['taken_at']);
        $this->assertSame('2024-06-30 14:22:05', $out['taken_at']->format('Y-m-d H:i:s'));
        $this->assertSame(49.9427, $out['lat']);
        $this->assertSame(11.5761, $out['lon']);
        $this->assertSame('Apple iPhone 15 Pro', $out['camera']);
        $this->assertSame('A1B2C3D4-0000-1111-2222-EEEEFFFF0000', $out['content_id']);
        $this->assertIsArray($out['raw']);
    }

    public function test_handles_sub_second_and_zoned_dates(): void
    {
        $out = (new ExifReader)->normalize([
            'SubSecDateTimeOriginal' => '2024:06:30 14:22:05.123+02:00',
        ]);

        $this->assertSame('2024-06-30 14:22:05', $out['taken_at']->format('Y-m-d H:i:s'));
    }

    public function test_empty_dump_yields_nulls(): void
    {
        $out = (new ExifReader)->normalize([]);

        $this->assertNull($out['taken_at']);
        $this->assertNull($out['lat']);
        $this->assertNull($out['camera']);
        $this->assertNull($out['content_id']);
        $this->assertNull($out['raw']);
    }

    public function test_finds_tags_by_group_suffix_when_prefix_differs(): void
    {
        // Older/newer exiftool may prefix ContentIdentifier under a different group.
        $out = (new ExifReader)->normalize([
            'QuickTime:ContentIdentifier' => 'ZZZ-9999',
            'QuickTime:CreateDate' => '2022:01:02 03:04:05',
        ]);

        $this->assertSame('ZZZ-9999', $out['content_id']);
        $this->assertSame('2022-01-02 03:04:05', $out['taken_at']->format('Y-m-d H:i:s'));
    }
}
