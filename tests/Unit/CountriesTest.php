<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Countries;
use PHPUnit\Framework\TestCase;

class CountriesTest extends TestCase
{
    public function test_options_are_not_empty_and_well_shaped(): void
    {
        $options = Countries::options();

        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('value', $options[0]);
        $this->assertArrayHasKey('label', $options[0]);
        $this->assertArrayHasKey('flag', $options[0]);
    }

    public function test_name_resolves_known_codes_and_rejects_unknown(): void
    {
        $this->assertSame('Germany', Countries::name('DE'));
        $this->assertNull(Countries::name('ZZ'));
        $this->assertNull(Countries::name(null));
        $this->assertNull(Countries::name(''));
    }

    public function test_flag_is_the_regional_indicator_emoji(): void
    {
        $this->assertSame('🇩🇪', Countries::flag('DE'));
        $this->assertSame('', Countries::flag('ZZ'));
    }

    public function test_exists_validates_codes(): void
    {
        $this->assertTrue(Countries::exists('US'));
        $this->assertFalse(Countries::exists('ZZ'));
    }
}
