<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ClusterFace;
use App\Jobs\DetectFaces;
use App\Models\Face;
use App\Models\Photo;
use App\Services\Gallery\FaceCropper;
use App\Services\Gallery\MachineLearning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceDetectionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFaces(array $faces): void
    {
        Http::fake(['ml:3003/predict' => Http::response([
            'facial-recognition' => $faces,
            'imageWidth' => 200,
            'imageHeight' => 200,
        ])]);
    }

    public function test_detect_faces_parses_and_normalises_boxes(): void
    {
        config(['gallery.face_enabled' => true, 'gallery.ml_url' => 'http://ml:3003']);
        $this->fakeFaces([
            ['boundingBox' => ['x1' => 50, 'y1' => 60, 'x2' => 150, 'y2' => 160], 'score' => 0.95, 'embedding' => json_encode([0.1, 0.2])],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'f');
        file_put_contents($tmp, 'x');
        $faces = app(MachineLearning::class)->detectFaces($tmp);
        @unlink($tmp);

        $this->assertCount(1, $faces);
        $this->assertSame(0.95, $faces[0]['score']);
        $this->assertEqualsWithDelta(0.25, $faces[0]['box'][0], 0.001); // 50/200
        $this->assertEqualsWithDelta(0.80, $faces[0]['box'][3], 0.001); // 160/200
        $this->assertSame([0.1, 0.2], $faces[0]['embedding']);
    }

    public function test_disabled_returns_no_faces(): void
    {
        config(['gallery.face_enabled' => false]);
        $this->assertSame([], app(MachineLearning::class)->detectFaces(__FILE__));
    }

    public function test_job_stores_only_faces_passing_score_and_size_filters(): void
    {
        config(['gallery.face_enabled' => true, 'gallery.ml_url' => 'http://ml:3003', 'gallery.face_min_score' => 0.7, 'gallery.face_min_size' => 32]);
        Queue::fake();
        Storage::fake('files');

        // A 200×200 medium image.
        $img = imagecreatetruecolor(200, 200);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $photo = Photo::factory()->create(['status' => 'ready', 'medium_path' => 'photos/medium/p.jpg']);
        Storage::disk('files')->put('photos/medium/p.jpg', $jpeg);

        $this->fakeFaces([
            ['boundingBox' => ['x1' => 40, 'y1' => 40, 'x2' => 140, 'y2' => 140], 'score' => 0.95, 'embedding' => json_encode([0.1])], // ok
            ['boundingBox' => ['x1' => 10, 'y1' => 10, 'x2' => 120, 'y2' => 120], 'score' => 0.40, 'embedding' => json_encode([0.2])], // low score
            ['boundingBox' => ['x1' => 10, 'y1' => 10, 'x2' => 20, 'y2' => 20], 'score' => 0.99, 'embedding' => json_encode([0.3])],  // too small (10px)
        ]);

        (new DetectFaces($photo->id))->handle(app(MachineLearning::class), app(FaceCropper::class));

        $this->assertSame(1, Face::where('photo_id', $photo->id)->count());
        $face = Face::where('photo_id', $photo->id)->first();
        $this->assertEqualsWithDelta(0.20, $face->box_x1, 0.001); // 40/200
        Queue::assertPushed(ClusterFace::class);
    }
}
