<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Photo;
use App\Services\Gallery\FilenameTemplate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FilenameTemplateTest extends TestCase
{
    private function photo(): Photo
    {
        $photo = new Photo(['name' => 'IMG_1234.JPG']);
        $photo->taken_at = Carbon::create(2026, 5, 1, 14, 30, 5);

        return $photo;
    }

    public function test_it_renders_placeholders_and_keeps_the_extension(): void
    {
        $name = (new FilenameTemplate)->render($this->photo(), '{{y}}-{{MM}}-{{dd}}_{{HH}}-{{mm}}-{{ss}}');

        $this->assertSame('2026-05-01_14-30-05.jpg', $name);
    }

    public function test_it_can_reuse_the_original_filename(): void
    {
        $name = (new FilenameTemplate)->render($this->photo(), '{{y}}_{{filename}}');

        $this->assertSame('2026_IMG_1234.jpg', $name);
    }

    public function test_an_empty_template_returns_null(): void
    {
        $this->assertNull((new FilenameTemplate)->render($this->photo(), ''));
        $this->assertNull((new FilenameTemplate)->render($this->photo(), null));
    }

    public function test_it_sanitises_unsafe_characters(): void
    {
        $name = (new FilenameTemplate)->render($this->photo(), 'trip: {{y}}/{{MM}}');

        $this->assertSame('trip-2026/05.jpg', $name);
    }
}
