<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PairLivePhotos;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LivePhotoPairingTest extends TestCase
{
    use RefreshDatabase;

    private function still(int $userId, string $contentId): Photo
    {
        return Photo::factory()->create([
            'media_type' => 'image',
            'mime_type' => 'image/heic',
            'content_id' => $contentId,
            'uploaded_by' => $userId,
            'motion_path' => null,
            'disk_path' => 'photos/2026/07/still.heic',
        ]);
    }

    private function movie(int $userId, string $contentId): Photo
    {
        $video = Photo::factory()->create([
            'media_type' => 'video',
            'mime_type' => 'video/quicktime',
            'content_id' => $contentId,
            'uploaded_by' => $userId,
            'duration' => 3,
            'disk_path' => 'photos/2026/07/clip.mov',
        ]);
        Storage::disk('files')->put($video->disk_path, 'mov-bytes');

        return $video;
    }

    public function test_it_pairs_the_still_and_movie_into_one_motion_photo(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $still = $this->still($user->id, 'LIVE-1');
        $video = $this->movie($user->id, 'LIVE-1');

        (new PairLivePhotos($still->id))->handle();

        $still->refresh();
        $this->assertNotNull($still->motion_path);
        $this->assertSame(3, $still->duration);
        Storage::disk('files')->assertExists($still->motion_path);
        $this->assertSoftDeleted('photos', ['id' => $video->id]);
        $this->assertTrue($still->hasMotion());
    }

    public function test_pairing_is_order_independent(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        // The movie is processed last and triggers the pairing.
        $still = $this->still($user->id, 'LIVE-2');
        $video = $this->movie($user->id, 'LIVE-2');

        (new PairLivePhotos($video->id))->handle();

        $still->refresh();
        $this->assertNotNull($still->motion_path);
        $this->assertSoftDeleted('photos', ['id' => $video->id]);
    }

    public function test_pairing_is_idempotent_and_does_not_pair_twice(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $still = $this->still($user->id, 'LIVE-3');
        $video = $this->movie($user->id, 'LIVE-3');

        (new PairLivePhotos($still->id))->handle();
        (new PairLivePhotos($video->id))->handle(); // second run must be a no-op

        $this->assertSame(1, Photo::count()); // just the still remains
        $this->assertSoftDeleted('photos', ['id' => $video->id]);
    }

    public function test_it_does_not_clobber_an_embedded_motion_clip(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $still = $this->still($user->id, 'LIVE-4');
        $still->forceFill(['motion_path' => 'photos/2026/07/motion/embedded.mp4'])->save();
        $video = $this->movie($user->id, 'LIVE-4');

        (new PairLivePhotos($still->id))->handle();

        $still->refresh();
        $this->assertSame('photos/2026/07/motion/embedded.mp4', $still->motion_path);
        // The standalone movie is left alone when the still already has motion.
        $this->assertNotSoftDeleted('photos', ['id' => $video->id]);
    }

    public function test_a_standalone_video_without_a_content_id_is_untouched(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $video = Photo::factory()->create([
            'media_type' => 'video',
            'content_id' => null,
            'uploaded_by' => $user->id,
        ]);

        (new PairLivePhotos($video->id))->handle();

        $this->assertNotSoftDeleted('photos', ['id' => $video->id]);
    }

    public function test_it_waits_when_only_one_half_is_present(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $still = $this->still($user->id, 'LIVE-5');

        (new PairLivePhotos($still->id))->handle();

        $still->refresh();
        $this->assertNull($still->motion_path); // nothing to pair with yet
    }
}
