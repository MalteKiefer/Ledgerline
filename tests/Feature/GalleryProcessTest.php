<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The zero-knowledge transform endpoint: plaintext in → derived data out, with
 * the plaintext discarded and nothing persisted. ML is disabled here, so the
 * embedding/faces come back empty while the (GD) renditions still generate.
 */
class GalleryProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No ML sidecar in tests: embed()/detectFaces() short-circuit to null/[].
        config(['gallery.ml_enabled' => false, 'gallery.face_enabled' => false]);
    }

    public function test_guests_are_redirected(): void
    {
        $this->post(route('gallery.process'))->assertRedirect(route('login'));
        $this->post(route('gallery.embed-text'))->assertRedirect(route('login'));
    }

    public function test_process_returns_derived_data_and_leaves_no_plaintext(): void
    {
        $this->signIn();

        $before = glob(sys_get_temp_dir().'/gproc*') ?: [];

        $res = $this->post(route('gallery.process'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertOk()->assertJsonStructure([
            'media_type', 'width', 'height', 'duration', 'content_id',
            'exif' => ['taken_at', 'lat', 'lon', 'camera'],
            'place', 'embedding', 'phash', 'faces', 'thumb', 'medium', 'motion',
        ]);

        $res->assertJson(['media_type' => 'image', 'embedding' => null, 'faces' => []]);
        // GD renders the thumbnail even without ML/exiftool.
        $this->assertNotNull($res->json('thumb'));
        $this->assertNotFalse(base64_decode((string) $res->json('thumb'), true));

        // No plaintext temp file is left behind.
        $after = glob(sys_get_temp_dir().'/gproc*') ?: [];
        $this->assertSame($before, $after);
    }

    public function test_embed_text_returns_null_without_ml(): void
    {
        $this->signIn();
        $this->postJson(route('gallery.embed-text'), ['q' => 'a red car'])
            ->assertOk()->assertJson(['embedding' => null]);
    }
}
