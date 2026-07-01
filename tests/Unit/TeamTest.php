<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Team;
use PHPUnit\Framework\TestCase;

class TeamTest extends TestCase
{
    public function test_humanise_turns_slugs_into_readable_names(): void
    {
        $this->assertSame('Kiefer Networks', Team::humanise('kiefer_networks'));
        $this->assertSame('Mail Admins', Team::humanise('mail-admins'));
        $this->assertSame('Admin', Team::humanise('admin'));
    }

    public function test_humanise_is_idempotent(): void
    {
        $this->assertSame('Kiefer Networks', Team::humanise('Kiefer Networks'));
    }

    public function test_display_name_accessor_humanises_stored_slug(): void
    {
        $team = new Team(['name' => 'immich_admins']);

        $this->assertSame('Immich Admins', $team->displayName);
    }
}
