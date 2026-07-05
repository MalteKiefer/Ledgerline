<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryZoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_columns_are_saved_per_user(): void
    {
        $this->signIn();

        $this->postJson(route('gallery.columns'), ['columns' => 4])->assertOk();
        $this->assertSame(4, UserSetting::for(auth()->id())->gallery_columns);
    }

    public function test_gallery_columns_are_bounded(): void
    {
        $this->signIn();
        $this->post(route('gallery.columns'), ['columns' => 99])->assertSessionHasErrors('columns');
        $this->post(route('gallery.columns'), ['columns' => 1])->assertSessionHasErrors('columns');
    }
}
