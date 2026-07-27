<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_blob_is_private_to_its_uploader(): void
    {
        Storage::fake(config('files.disk'));
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Alice uploads a blob (recorded in file_blobs as hers).
        $blob = $this->actingAs($alice)->post(route('files.upload'), [
            'file' => UploadedFile::fake()->create('a.bin', 4),
        ])->assertCreated()->json('id');

        // Bob cannot download Alice's blob; his delete is a uniform idempotent
        // no-op (no 403-vs-200 ownership oracle) and Alice's bytes survive.
        $this->actingAs($bob)->get(route('files.raw', ['blob' => $blob]))->assertNotFound();
        $this->actingAs($bob)->deleteJson(route('files.blob.destroy', ['blob' => $blob]))->assertOk();
        Storage::disk(config('files.disk'))->assertExists('files/'.$blob);
    }

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
