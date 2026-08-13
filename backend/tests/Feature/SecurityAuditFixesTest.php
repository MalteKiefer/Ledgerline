<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_host_guard_blocks_link_local_metadata(): void
    {
        $this->assertFalse(OutboundUrl::hostAllowed('169.254.169.254')); // cloud metadata
        $this->assertFalse(OutboundUrl::hostAllowed('::ffff:169.254.169.254'));
        $this->assertTrue(OutboundUrl::hostAllowed('8.8.8.8')); // public IP allowed
    }

    public function test_global_settings_gate_is_role_based(): void
    {
        // Admin rights come from the first-party role, not user count or OIDC groups.
        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->managesGlobalSettings());

        $plain = User::factory()->create(); // role 'user'
        $this->assertFalse($plain->managesGlobalSettings());
    }
}
