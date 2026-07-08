<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Services\Gallery\DuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    /** A tiny valid JPEG the perceptual hasher can decode. */
    private function jpeg(int $shade): string
    {
        $img = imagecreatetruecolor(16, 16);
        imagefilledrectangle($img, 0, 0, 15, 15, imagecolorallocate($img, $shade, $shade, $shade));
        // A bright corner so the dHash has structure (not a flat all-zero hash).
        imagefilledrectangle($img, 0, 0, 4, 4, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagejpeg($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public function test_videos_are_hashed_from_their_poster_and_grouped(): void
    {
        Storage::fake('files');
        $poster = $this->jpeg(40);
        Storage::disk('files')->put('m/a.jpg', $poster);
        Storage::disk('files')->put('m/b.jpg', $poster); // identical poster = duplicate

        // Two videos, no phash yet, each with a poster in medium_path.
        $a = Photo::factory()->create(['status' => 'ready', 'media_type' => 'video', 'phash' => null, 'medium_path' => 'm/a.jpg']);
        $b = Photo::factory()->create(['status' => 'ready', 'media_type' => 'video', 'phash' => null, 'medium_path' => 'm/b.jpg']);

        app(DuplicateDetector::class)->run();

        $a->refresh();
        $b->refresh();
        $this->assertNotNull($a->phash, 'video A never got a perceptual hash');
        $this->assertNotNull($a->duplicate_group_id, 'video A was not flagged as a duplicate');
        $this->assertSame($a->duplicate_group_id, $b->duplicate_group_id, 'the two identical videos were not grouped');
    }
}
