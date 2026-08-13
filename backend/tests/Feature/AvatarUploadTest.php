<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_user_can_upload_an_avatar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 800, 600)])
            ->assertOk()
            ->assertJson(['ok' => true, 'has_avatar' => true]);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/', (string) $user->avatar);
        $this->assertTrue(BlobStore::disk()->exists($user->avatar));
    }

    public function test_upload_rejects_a_non_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile/account')
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_upload_rejects_an_image_over_10mb(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile/account')
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_user_can_remove_their_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png')])->assertOk();
        $path = $user->fresh()->avatar;

        $this->actingAs($user)->delete('/profile/avatar')->assertOk()->assertJson(['has_avatar' => false]);
        $this->assertNull($user->fresh()->avatar);
        $this->assertFalse(BlobStore::disk()->exists($path));
    }
}
