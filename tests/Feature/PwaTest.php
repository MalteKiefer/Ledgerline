<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_carries_the_pwa_meta_tags(): void
    {
        $this->signIn();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('theme-color', false)
            ->assertSee('apple-touch-icon', false);
    }

    public function test_the_pwa_assets_exist_and_the_manifest_is_valid(): void
    {
        foreach (['manifest.webmanifest', 'sw.js', 'offline.html', 'icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('Ledgerline', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertCount(3, $manifest['icons']);

        // The service worker must precache the offline fallback it serves.
        $sw = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('/offline.html', $sw);
    }
}
