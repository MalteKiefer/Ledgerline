<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Mail\EmbeddedImages;
use Tests\TestCase;

final class EmbeddedImagesTest extends TestCase
{
    /** A minimal stand-in for a webklex Attachment (embed() reads id/mime/content). */
    private function att(string $id, string $mime, string $content): object
    {
        return new class($id, $mime, $content)
        {
            public function __construct(public string $id, private string $mime, private string $content) {}

            public function getMimeType(): string
            {
                return $this->mime;
            }

            public function getContent(): string
            {
                return $this->content;
            }
        };
    }

    public function test_referenced_cids_are_extracted_lowercased(): void
    {
        $html = '<img src="cid:ii_mr4scga80"> x <img src="cid:<Logo2>"> y <img src="https://ex.com/a.gif">';
        $this->assertSame(['ii_mr4scga80', 'logo2'], EmbeddedImages::referencedCids($html));
        $this->assertSame([], EmbeddedImages::referencedCids('<p>no images</p>'));
        $this->assertSame([], EmbeddedImages::referencedCids(null));
    }

    public function test_embed_replaces_cid_with_a_data_uri(): void
    {
        $html = '<img src="cid:ii_mr4scga80" alt="image.png"> and <img src="cid:<ii_mr4scga80>">';
        $out = EmbeddedImages::embed($html, $this->att('ii_mr4scga80', 'image/png', 'PNGBYTES'));

        $this->assertStringNotContainsString('cid:', $out);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode('PNGBYTES'), $out);
    }

    public function test_embed_falls_back_to_image_png_for_a_generic_mime(): void
    {
        $out = EmbeddedImages::embed('<img src="cid:x1">', $this->att('x1', 'application/octet-stream', 'BYTES'));
        $this->assertStringContainsString('data:image/png;base64,', $out);
    }
}
