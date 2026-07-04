<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Gallery\MachineLearning;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MachineLearningTest extends TestCase
{
    public function test_disabled_when_config_is_off(): void
    {
        config(['gallery.ml_enabled' => false]);

        $this->assertFalse(app(MachineLearning::class)->enabled());
        $this->assertNull(app(MachineLearning::class)->embed(__FILE__));
    }

    public function test_embed_parses_the_immich_clip_string_response(): void
    {
        config(['gallery.ml_enabled' => true, 'gallery.ml_url' => 'http://ml:3003', 'gallery.ml_clip_model' => 'ViT-B-32__openai']);
        Http::fake([
            'ml:3003/predict' => Http::response(['clip' => json_encode([0.1, -0.2, 0.3])]),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'JPEGBYTES');

        $vector = app(MachineLearning::class)->embed($tmp);
        @unlink($tmp);

        $this->assertSame([0.1, -0.2, 0.3], $vector);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/predict'));
    }

    public function test_embed_handles_a_plain_array_response(): void
    {
        config(['gallery.ml_enabled' => true, 'gallery.ml_url' => 'http://ml:3003']);
        Http::fake(['ml:3003/predict' => Http::response(['clip' => [1.0, 2.0]])]);

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'x');
        $vector = app(MachineLearning::class)->embed($tmp);
        @unlink($tmp);

        $this->assertSame([1.0, 2.0], $vector);
    }
}
