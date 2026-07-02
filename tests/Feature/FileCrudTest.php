<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FileType;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_upload(): void
    {
        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('login'));
    }

    public function test_upload_extracts_text_from_plain_text_files(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->createWithContent('notes.txt', 'the secret keyword is qzxwv')],
        ]);

        $file = File::firstWhere('name', 'notes.txt');
        $this->assertStringContainsString('qzxwv', (string) $file->extracted_text);
    }

    public function test_upload_does_not_extract_binary_files(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->create('bundle.zip', 10, 'application/zip')],
        ]);

        $this->assertNull(File::firstWhere('name', 'bundle.zip')->extracted_text);
    }

    public function test_download_streams_a_file(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create(['disk_path' => 'files/doc.pdf']);
        Storage::disk('files')->put('files/doc.pdf', 'bytes');

        $this->get(route('files.download', $file))->assertOk();
    }

    public function test_destroy_deletes_file_and_bytes(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create(['disk_path' => 'files/doc.pdf']);
        Storage::disk('files')->put('files/doc.pdf', 'bytes');

        $this->delete(route('files.destroy', $file))->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('files')->assertMissing('files/doc.pdf');
    }

    public function test_svg_is_forced_to_download_with_hardening_headers(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'disk_path' => 'files/x.svg',
            'mime_type' => 'image/svg+xml',
            'type' => FileType::IMAGE,
        ]);
        Storage::disk('files')->put('files/x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $response = $this->get(route('files.download', $file))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_png_is_served_inline(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'disk_path' => 'files/x.png',
            'mime_type' => 'image/png',
            'type' => FileType::IMAGE,
        ]);
        Storage::disk('files')->put('files/x.png', 'bytes');

        $response = $this->get(route('files.download', $file))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_search_finds_files_by_name_and_content(): void
    {
        Storage::fake('files');
        $this->signIn();
        File::factory()->create([
            'name' => 'Onboarding.txt',
            'extracted_text' => 'welcome qzxwvcontent handbook',
        ]);

        $this->get(route('search', ['q' => 'qzxwvcontent']))
            ->assertOk()
            ->assertSee('Files')
            ->assertSee('Onboarding.txt');
    }
}
