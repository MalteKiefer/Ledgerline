<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\VaultRole;
use Tests\TestCase;

final class VaultRoleTest extends TestCase
{
    public function test_rank_ordering(): void
    {
        $this->assertSame(1, VaultRole::Viewer->rank());
        $this->assertSame(2, VaultRole::Editor->rank());
        $this->assertSame(3, VaultRole::Manager->rank());
    }

    public function test_manager_rank_is_highest(): void
    {
        $this->assertGreaterThan(VaultRole::Editor->rank(), VaultRole::Manager->rank());
        $this->assertGreaterThan(VaultRole::Viewer->rank(), VaultRole::Editor->rank());
    }

    public function test_at_least_same_role(): void
    {
        $this->assertTrue(VaultRole::Manager->atLeast(VaultRole::Manager));
        $this->assertTrue(VaultRole::Editor->atLeast(VaultRole::Editor));
        $this->assertTrue(VaultRole::Viewer->atLeast(VaultRole::Viewer));
    }

    public function test_at_least_lower_role(): void
    {
        $this->assertTrue(VaultRole::Manager->atLeast(VaultRole::Editor));
        $this->assertTrue(VaultRole::Manager->atLeast(VaultRole::Viewer));
        $this->assertTrue(VaultRole::Editor->atLeast(VaultRole::Viewer));
    }

    public function test_at_least_fails_for_higher_role(): void
    {
        $this->assertFalse(VaultRole::Viewer->atLeast(VaultRole::Manager));
        $this->assertFalse(VaultRole::Viewer->atLeast(VaultRole::Editor));
        $this->assertFalse(VaultRole::Editor->atLeast(VaultRole::Manager));
    }

    public function test_values_array(): void
    {
        $this->assertSame(['viewer', 'editor', 'manager'], VaultRole::values());
    }

    public function test_backing_values(): void
    {
        $this->assertSame('viewer', VaultRole::Viewer->value);
        $this->assertSame('editor', VaultRole::Editor->value);
        $this->assertSame('manager', VaultRole::Manager->value);
    }
}
