<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /me's `usage` field — Files+Gallery combined against the one shared
 * workspace-wide quota (App\Support\StorageUsage). Regression coverage for the
 * gap fixed alongside this: FilesController/MailAttachmentController/
 * SharedWithMeController/ExtractArchive/DavStorage each used to enforce the
 * cap against Files bytes only, silently ignoring Gallery bytes already
 * consumed against the SAME shared cap.
 */
class MeUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_me_reports_zero_usage_and_null_quota_by_default(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/me', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('usage.used', 0)
            ->assertJsonPath('usage.quota', null);
    }

    public function test_me_usage_combines_files_and_gallery_bytes(): void
    {
        $user = User::factory()->create();
        $h = $this->bearer($user);

        // createWithContent, not create(size): the latter reports a size it
        // does not have, the stored file is empty, and the files half of this
        // test silently contributes nothing.
        $this->actingAs($user)->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('doc.pdf', str_repeat('x', 4096))])->assertCreated();
        $this->actingAs($user)->post(route('gallery.upload'), ['file' => UploadedFile::fake()->image('photo.jpg', 200, 150)])->assertCreated();

        $expected = (int) FileEntry::withTrashed()->sum('size') + (int) GalleryPhoto::withTrashed()->sum('size');
        $this->assertGreaterThan(0, $expected);

        $files = (int) FileEntry::withTrashed()->sum('size');
        $gallery = (int) GalleryPhoto::withTrashed()->sum('size');

        // The breakdown matters on its own: "how much is the gallery using" is
        // a question the total cannot answer, and a client that has to guess
        // gets it wrong.
        $this->getJson('/api/v1/me', $h)->assertOk()
            ->assertJsonPath('usage.used', $expected)
            ->assertJsonPath('usage.files', $files)
            ->assertJsonPath('usage.gallery', $gallery);

        $this->assertGreaterThan(0, $files);
        $this->assertGreaterThan(0, $gallery);
    }

    public function test_me_usage_reflects_the_shared_quota_cap(): void
    {
        config(['files.quota_mb' => 5]);
        $user = User::factory()->create();

        $this->getJson('/api/v1/me', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('usage.quota', 5 * 1024 * 1024);
    }

    public function test_me_usage_is_scoped_to_the_authenticated_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->create('mine.pdf', 20)])->assertCreated();

        $this->getJson('/api/v1/me', $this->bearer($b))->assertOk()->assertJsonPath('usage.used', 0);
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device')->plainTextToken];
    }
}
