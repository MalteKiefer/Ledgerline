<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilesAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_and_clears_global_file_limits(): void
    {
        // Global file limits now live on their own admin route, separate from the
        // per-user version-keep count (which stays on settings.files.update).
        $this->actingAs(User::factory()->create());
        $this->put(route('settings.files.limits.update'), [
            'files_quota_mb' => 1234,
            'files_max_upload_mb' => 700,
        ])->assertRedirect();
        $s = AppSettings::current();
        $this->assertSame(1234, $s->files_quota_mb);
        $this->assertSame(700, $s->files_max_upload_mb);

        // Empty clears the override back to the default.
        $this->put(route('settings.files.limits.update'), ['files_quota_mb' => null]);
        $this->assertNull(AppSettings::current()->fresh()->files_quota_mb);
    }

    public function test_limits_page_renders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('settings.files.limits'))->assertOk()->assertSee(__('settings.files_quota'));
    }

    public function test_personal_files_page_has_no_admin_limits(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('settings.files.edit'))
            ->assertOk()
            ->assertSee(__('settings.files_max_versions'))
            ->assertDontSee(__('settings.files_quota'));
    }
}
