<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\EmbedPhoto;
use App\Models\Photo;
use App\Services\Gallery\MachineLearning;
use App\Services\Gallery\PerceptualHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmbedPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function photoWithMedium(): Photo
    {
        Storage::fake('files');
        $img = imagecreatetruecolor(32, 24);
        imagefilledrectangle($img, 0, 0, 31, 23, imagecolorallocate($img, 40, 80, 160));
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $photo = Photo::factory()->create(['status' => 'ready', 'medium_path' => 'photos/medium/x.jpg', 'phash' => null, 'embedded_at' => null]);
        Storage::disk('files')->put('photos/medium/x.jpg', $jpeg);

        return $photo;
    }

    public function test_backfills_the_perceptual_hash_even_without_ml(): void
    {
        config(['gallery.ml_enabled' => false]);
        $photo = $this->photoWithMedium();

        (new EmbedPhoto($photo->id))->handle(app(MachineLearning::class), app(PerceptualHash::class));

        $this->assertNotNull($photo->fresh()->phash);
        $this->assertNull($photo->fresh()->embedded_at); // ML off → no embedding
    }

    public function test_records_embedding_when_ml_is_enabled(): void
    {
        config(['gallery.ml_enabled' => true, 'gallery.ml_url' => 'http://ml:3003']);
        Http::fake(['ml:3003/predict' => Http::response(['clip' => json_encode([0.1, 0.2, 0.3])])]);
        $photo = $this->photoWithMedium();

        (new EmbedPhoto($photo->id))->handle(app(MachineLearning::class), app(PerceptualHash::class));

        $fresh = $photo->fresh();
        $this->assertNotNull($fresh->phash);
        $this->assertNotNull($fresh->embedded_at);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/predict'));
    }
}
