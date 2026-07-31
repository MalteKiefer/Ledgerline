<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_gallery(): void
    {
        $this->get(route('gallery.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_the_zero_knowledge_shell(): void
    {
        $this->signIn();

        // The zero-knowledge gallery ships only the empty client shell: the
        // browser holds all keys and renders from the sealed index + blobs, so
        // the server never sees or emits any photo data.
        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('vaultGallery(', false);
    }
}
