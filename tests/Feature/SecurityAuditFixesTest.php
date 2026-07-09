<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
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

    public function test_a_blob_is_private_to_its_uploader(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Alice uploads a blob (recorded in file_blobs as hers).
        $blob = $this->actingAs($alice)->post(route('files.upload'), [
            'file' => UploadedFile::fake()->create('a.bin', 4),
        ])->assertCreated()->json('id');

        // Bob can neither download nor delete Alice's blob by guessing its UUID.
        $this->actingAs($bob)->get(route('files.raw', ['blob' => $blob]))->assertNotFound();
        $this->actingAs($bob)->deleteJson(route('files.blob.destroy', ['blob' => $blob]))->assertForbidden();
        Storage::disk(config('files.disk'))->assertExists('files/'.$blob);
    }

    public function test_outbound_host_guard_blocks_link_local_metadata(): void
    {
        $this->assertFalse(OutboundUrl::hostAllowed('169.254.169.254')); // cloud metadata
        $this->assertFalse(OutboundUrl::hostAllowed('::ffff:169.254.169.254'));
        $this->assertTrue(OutboundUrl::hostAllowed('8.8.8.8')); // public IP allowed
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
