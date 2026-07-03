<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Tags;
use Tests\TestCase;

final class TagsTest extends TestCase
{
    public function test_normalize_reindexes_and_casts_to_strings(): void
    {
        $this->assertSame([], Tags::normalize(null));
        $this->assertSame([], Tags::normalize('not-an-array'));
        $this->assertSame(['a', 'b'], Tags::normalize([2 => 'a', 5 => 'b']));
        $this->assertSame(['1', '2'], Tags::normalize([1, 2]));
    }

    public function test_rules_cover_the_array_and_its_items(): void
    {
        $rules = Tags::rules();

        $this->assertSame(['array'], $rules['tags']);
        $this->assertContains('string', $rules['tags.*']);
        $this->assertContains('max:'.Tags::MAX_LENGTH, $rules['tags.*']);
    }
}
