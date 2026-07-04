<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Mail\EmbeddedImages;
use Tests\TestCase;

final class EmbeddedImagesTest extends TestCase
{
    /** A minimal stand-in for a webklex Attachment (inline() only reads id/mime/content). */
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

    public function test_it_inlines_cid_images_as_data_uris(): void
    {
        $html = '<p>Hi</p><img src="cid:logo123"> and <img src="cid:<logo123>">';
        $out = EmbeddedImages::inline($html, [$this->att('logo123', 'image/png', 'PNGBYTES')]);

        $expected = 'data:image/png;base64,'.base64_encode('PNGBYTES');
        $this->assertStringNotContainsString('cid:', $out);
        $this->assertStringContainsString($expected, $out);
    }

    public function test_it_leaves_html_without_cids_and_remote_images_untouched(): void
    {
        $html = '<img src="https://example.com/tracker.gif">';
        $this->assertSame($html, EmbeddedImages::inline($html, [$this->att('x', 'image/gif', 'GIF')]));
        $this->assertNull(EmbeddedImages::inline(null, []));
    }

    public function test_it_skips_non_image_and_oversized_parts(): void
    {
        $html = '<img src="cid:doc1">';
        // Non-image cid is not inlined (stays a broken cid, never a data URI).
        $out = EmbeddedImages::inline($html, [$this->att('doc1', 'application/pdf', 'PDF')]);
        $this->assertSame($html, $out);
    }
}
