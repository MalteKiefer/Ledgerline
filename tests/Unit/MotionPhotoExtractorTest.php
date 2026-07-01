<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Gallery\MotionPhotoExtractor;
use PHPUnit\Framework\TestCase;

class MotionPhotoExtractorTest extends TestCase
{
    private string $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    private function write(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'motion');
        file_put_contents($path, $bytes);

        return $path;
    }

    public function test_it_extracts_the_clip_from_the_container_length(): void
    {
        $length = strlen($this->mp4);
        $xmp = '<x:xmpmeta><rdf:Description GCamera:MotionPhoto="1">'
            .'<Container:Item Item:Semantic="Primary" Item:Length="0"/>'
            .'<Container:Item Item:Semantic="MotionPhoto" Item:Length="'.$length.'"/>'
            .'</rdf:Description></x:xmpmeta>';
        $path = $this->write("\xFF\xD8".$xmp."\xFF\xD9".$this->mp4);

        $this->assertSame($this->mp4, (new MotionPhotoExtractor)->extract($path));
        @unlink($path);
    }

    public function test_it_falls_back_to_scanning_for_the_ftyp_box(): void
    {
        $path = $this->write("\xFF\xD8 plain jpeg body \xFF\xD9".$this->mp4);

        $this->assertSame($this->mp4, (new MotionPhotoExtractor)->extract($path));
        @unlink($path);
    }

    public function test_a_plain_jpeg_yields_no_clip(): void
    {
        $path = $this->write("\xFF\xD8 just an ordinary jpeg \xFF\xD9");

        $this->assertNull((new MotionPhotoExtractor)->extract($path));
        @unlink($path);
    }
}
