<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Services\Gallery\DuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function photo(array $attrs): Photo
    {
        return Photo::factory()->create(array_merge(['status' => 'ready', 'media_type' => 'image'], $attrs));
    }

    public function test_near_identical_photos_form_a_group(): void
    {
        $a = $this->photo(['phash' => 0b1010101010101010]);
        $b = $this->photo(['phash' => 0b1010101010101011]); // 1 bit off → within 6
        $c = $this->photo(['phash' => 0b0101010101010101]); // very different

        app(DuplicateDetector::class)->run();

        [$a, $b, $c] = [$a->fresh(), $b->fresh(), $c->fresh()];
        $this->assertNotNull($a->duplicate_group_id);
        $this->assertSame($a->duplicate_group_id, $b->duplicate_group_id);
        $this->assertNull($c->duplicate_group_id);
        $this->assertGreaterThan(0.9, (float) $a->dup_score);
    }

    public function test_different_media_types_do_not_group(): void
    {
        $img = $this->photo(['phash' => 42, 'media_type' => 'image']);
        $vid = $this->photo(['phash' => 42, 'media_type' => 'video']);

        app(DuplicateDetector::class)->run();

        $this->assertNull($img->fresh()->duplicate_group_id);
        $this->assertNull($vid->fresh()->duplicate_group_id);
    }

    public function test_dismissed_photos_are_excluded(): void
    {
        $a = $this->photo(['phash' => 100]);
        $b = $this->photo(['phash' => 100, 'dup_dismissed_at' => now()]);

        app(DuplicateDetector::class)->run();

        // Only one live member → no group.
        $this->assertNull($a->fresh()->duplicate_group_id);
        $this->assertNull($b->fresh()->duplicate_group_id);
    }

    public function test_detection_is_idempotent(): void
    {
        $a = $this->photo(['phash' => 7]);
        $b = $this->photo(['phash' => 7]);

        $detector = app(DuplicateDetector::class);
        $this->assertSame(1, $detector->run());
        $this->assertSame(1, $detector->run());

        $this->assertSame($a->fresh()->duplicate_group_id, $b->fresh()->duplicate_group_id);
        $this->assertNotNull($a->fresh()->duplicate_group_id);
    }
}
