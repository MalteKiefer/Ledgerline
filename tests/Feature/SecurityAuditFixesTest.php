<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_export_cannot_include_another_users_photo(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        // Ownership (uploaded_by) is assigned from the authenticated user.
        $this->actingAs($alice);
        $alicePhoto = Photo::factory()->create();

        // Bob owns nothing → exporting Alice's photo id yields no owned photos → 422.
        $this->actingAs($bob)->postJson(route('gallery.export'), ['photo_ids' => [$alicePhoto->id]])
            ->assertStatus(422);
    }

    public function test_global_settings_gate_fails_closed_on_multi_user_without_admin_group(): void
    {
        Config::set('services.pocketid.admin_group', null);

        $solo = User::factory()->create();
        $this->assertTrue($solo->managesGlobalSettings()); // single user: allowed

        User::factory()->create(); // now multi-user
        $this->assertFalse($solo->fresh()->managesGlobalSettings()); // fails closed
    }
}
