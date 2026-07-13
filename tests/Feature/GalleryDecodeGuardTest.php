<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GalleryDecodeGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_rejects_an_image_over_the_megapixel_cap_before_decoding(): void
    {
        config(['gallery.max_megapixels' => 1]); // 1 MP cap for the test

        $user = User::factory()->create();

        // 2000x1000 = 2 MP, over the cap → fast-fail 422 before any decode.
        $this->actingAs($user)
            ->postJson('/gallery/process', ['file' => UploadedFile::fake()->image('bomb.jpg', 2000, 1000)])
            ->assertStatus(422);
    }
}
