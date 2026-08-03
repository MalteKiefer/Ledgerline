<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\MailBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mail archive blob read endpoint (Phase 1, read-only): owner-scoped, immutable
 * sealed RFC822 bytes at mail/{blob}. There is no client upload route yet — the
 * server-side ingestor writes blobs directly via Support\BlobStore.
 */
class MailBlobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device')->plainTextToken];
    }

    private function storeBlob(User $owner, string $bytes = 'sealed-bytes'): string
    {
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('mail/'.$blob, $bytes);
        MailBlob::create(['blob' => $blob, 'user_id' => $owner->id, 'size' => strlen($bytes), 'created_at' => now()]);

        return $blob;
    }

    public function test_owner_can_read_the_sealed_blob_with_immutable_headers(): void
    {
        $user = $this->signIn();
        $blob = $this->storeBlob($user, 'sealed-bytes');

        $response = $this->get(route('mail.raw', $blob))->assertOk();

        $this->assertSame('sealed-bytes', $response->streamedContent());
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('immutable', (string) $cacheControl);
    }

    public function test_a_different_user_gets_404(): void
    {
        $owner = $this->signIn();
        $blob = $this->storeBlob($owner);

        $this->signIn();
        $this->get(route('mail.raw', $blob))->assertNotFound();
    }

    public function test_api_route_reads_the_blob_with_a_device_token(): void
    {
        $user = User::factory()->create();
        $blob = $this->storeBlob($user);

        $this->withHeaders($this->bearer($user))
            ->get(route('api.mail.raw', $blob))
            ->assertOk();
    }

    public function test_api_route_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $blob = $this->storeBlob($owner);

        $this->withHeaders($this->bearer($other))
            ->get(route('api.mail.raw', $blob))
            ->assertNotFound();
    }
}
