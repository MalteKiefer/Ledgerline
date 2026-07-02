<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FileType;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneralFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_upload_creates_a_file_without_an_owner_in_a_folder(): void
    {
        Storage::fake('files');
        $this->signIn();
        $folder = Folder::create(['name' => 'Company']);

        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->create('letterhead.pdf', 12, 'application/pdf')],
            'folder_id' => $folder->id,
        ])->assertRedirect(route('files.index', ['folder' => $folder->id]));

        $file = File::firstWhere('name', 'letterhead.pdf');
        $this->assertSame($folder->id, $file->folder_id);
    }

    public function test_an_encrypted_upload_stores_only_opaque_blobs(): void
    {
        Storage::fake('files');
        $this->signIn();
        $folder = Folder::create(['name' => 'Vault']);

        $this->post(route('files.store.general'), [
            'files' => [UploadedFile::fake()->createWithContent('blob', 'ciphertext-bytes')],
            'folder_id' => $folder->id,
            'encrypted' => '1',
            'enc_metadata' => ['{"c":"metacipher","n":"metanonce"}'],
            'enc_file_key' => ['{"c":"keycipher","n":"keynonce"}'],
        ])->assertRedirect(route('files.index', ['folder' => $folder->id]));

        $file = File::where('folder_id', $folder->id)->sole();
        $this->assertTrue($file->is_encrypted);
        $this->assertSame(FileType::ENCRYPTED, $file->type);
        $this->assertSame('', $file->name);
        $this->assertNull($file->checksum);
        $this->assertNull($file->extracted_text);
        $this->assertSame('{"c":"metacipher","n":"metanonce"}', $file->enc_metadata);
        $this->assertSame('{"c":"keycipher","n":"keynonce"}', $file->enc_file_key);
        // The stored bytes are the ciphertext, untouched by the server.
        $this->assertSame('ciphertext-bytes', Storage::disk('files')->get($file->disk_path));
    }

    public function test_encrypted_uploads_land_in_their_resolved_folders(): void
    {
        Storage::fake('files');
        $this->signIn();
        // The browser creates the encrypted folder tree first, then sends each
        // file with the folder id it resolved to.
        $trip = Folder::create(['name' => '', 'enc_name' => '{"c":"trip","n":"n"}']);
        $day1 = Folder::create(['name' => '', 'enc_name' => '{"c":"day1","n":"n"}', 'parent_id' => $trip->id]);

        $this->post(route('files.store.general'), [
            'files' => [
                UploadedFile::fake()->createWithContent('blob', 'a'),
                UploadedFile::fake()->createWithContent('blob', 'b'),
            ],
            'encrypted' => '1',
            'enc_metadata' => ['{"c":"a","n":"n"}', '{"c":"b","n":"n"}'],
            'enc_file_key' => ['{"c":"ka","n":"n"}', '{"c":"kb","n":"n"}'],
            'folder_ids' => [$day1->id, $trip->id],
        ])->assertRedirect();

        $files = File::where('is_encrypted', true)->get();
        $this->assertSame(2, $files->count());
        $this->assertSame($day1->id, $files[0]->folder_id);
        $this->assertSame($trip->id, $files[1]->folder_id);
    }

    public function test_an_existing_plaintext_file_can_be_encrypted_in_place(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'name' => 'notes.txt', 'title' => 'Notes', 'type' => FileType::DOCUMENT,
            'is_encrypted' => false, 'disk_path' => 'files/notes.txt', 'mime_type' => 'text/plain',
            'checksum' => hash('sha256', 'plain'), 'extracted_text' => 'plain', 'size' => 5,
        ]);
        Storage::disk('files')->put('files/notes.txt', 'plain');

        $this->put(route('files.encrypt', $file), [
            'file' => UploadedFile::fake()->createWithContent('blob', 'sealed-bytes'),
            'enc_metadata' => '{"c":"m","n":"n"}',
            'enc_file_key' => '{"c":"k","n":"n"}',
        ])->assertRedirect();

        $file->refresh();
        $this->assertTrue($file->is_encrypted);
        $this->assertSame(FileType::ENCRYPTED, $file->type);
        $this->assertSame('', $file->name);
        $this->assertNull($file->title);
        $this->assertNull($file->checksum);
        $this->assertNull($file->extracted_text);
        $this->assertSame('{"c":"m","n":"n"}', $file->enc_metadata);
        $this->assertSame('sealed-bytes', Storage::disk('files')->get('files/notes.txt'));

        // Re-encrypting an already-encrypted file is refused.
        $this->put(route('files.encrypt', $file), [
            'file' => UploadedFile::fake()->createWithContent('blob', 'x'),
            'enc_metadata' => '{}', 'enc_file_key' => '{}',
        ])->assertStatus(409);
    }

    public function test_an_encrypted_file_is_not_read_server_side_for_editing(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'name' => '', 'type' => FileType::ENCRYPTED, 'is_encrypted' => true,
            'disk_path' => 'files/enc.bin', 'mime_type' => 'application/octet-stream',
            'enc_metadata' => '{"c":"m","n":"n"}', 'enc_file_key' => '{"c":"k","n":"n"}',
        ]);
        Storage::disk('files')->put('files/enc.bin', 'ciphertext');

        $this->get(route('files.edit', $file))
            ->assertOk()
            ->assertSee(__('files.decrypting'))
            ->assertSee('encCodeEditor', false)
            ->assertDontSee('ciphertext');
    }

    public function test_an_encrypted_files_content_update_stores_new_ciphertext(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'name' => '', 'type' => FileType::ENCRYPTED, 'is_encrypted' => true,
            'disk_path' => 'files/enc.bin', 'mime_type' => 'application/octet-stream', 'size' => 3,
            'enc_metadata' => '{"c":"old","n":"n"}', 'enc_file_key' => '{"c":"oldk","n":"n"}',
        ]);
        Storage::disk('files')->put('files/enc.bin', 'old');

        $this->put(route('files.content', $file), [
            'encrypted' => '1',
            'file' => UploadedFile::fake()->createWithContent('blob', 'new-ciphertext'),
            'enc_metadata' => '{"c":"new","n":"n2"}',
            'enc_file_key' => '{"c":"newk","n":"n2"}',
        ])->assertRedirect(route('files.edit', $file));

        $file->refresh();
        $this->assertSame('new-ciphertext', Storage::disk('files')->get('files/enc.bin'));
        $this->assertSame('{"c":"new","n":"n2"}', $file->enc_metadata);
        $this->assertSame('{"c":"newk","n":"n2"}', $file->enc_file_key);
        $this->assertSame(14, $file->size);
        $this->assertTrue($file->is_encrypted);
    }

    public function test_download_returns_encrypted_bytes_untouched(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'name' => '', 'type' => FileType::ENCRYPTED, 'is_encrypted' => true,
            'disk_path' => 'files/enc.bin', 'mime_type' => 'application/octet-stream',
        ]);
        Storage::disk('files')->put('files/enc.bin', 'raw-ciphertext-bytes');

        $res = $this->get(route('files.download', $file));
        $res->assertOk();
        $this->assertSame('raw-ciphertext-bytes', $res->streamedContent());
    }

    public function test_a_dropped_folder_recreates_its_subfolders(): void
    {
        Storage::fake('files');
        $this->signIn();

        $this->post(route('files.store.general'), [
            'files' => [
                UploadedFile::fake()->create('a.txt', 5),
                UploadedFile::fake()->create('b.txt', 5),
            ],
            'paths' => ['Trip/Day1/a.txt', 'Trip/b.txt'],
        ])->assertRedirect();

        $trip = Folder::where('name', 'Trip')->whereNull('parent_id')->first();
        $this->assertNotNull($trip);
        $day1 = Folder::where('name', 'Day1')->where('parent_id', $trip->id)->first();
        $this->assertNotNull($day1);

        $this->assertSame($day1->id, File::firstWhere('name', 'a.txt')->folder_id);
        $this->assertSame($trip->id, File::firstWhere('name', 'b.txt')->folder_id);
    }

    public function test_same_name_uploads_respect_the_conflict_strategy(): void
    {
        Storage::fake('files');
        $this->signIn();
        $folder = Folder::create(['name' => 'Docs']);
        $route = route('files.store.general');
        $upload = fn () => ['files' => [UploadedFile::fake()->create('a.txt', 5)], 'folder_id' => $folder->id];

        $this->post($route, $upload());
        $this->assertSame(1, File::where('folder_id', $folder->id)->count());

        // Overwrite keeps a single file with the original name.
        $this->post($route, $upload() + ['on_conflict' => 'overwrite']);
        $this->assertSame(1, File::where('folder_id', $folder->id)->where('name', 'a.txt')->count());

        // Rename keeps both.
        $this->post($route, $upload() + ['on_conflict' => 'rename']);
        $this->assertSame(1, File::where('folder_id', $folder->id)->where('name', 'a_2.txt')->count());
        $this->assertSame(2, File::where('folder_id', $folder->id)->count());

        // Skip adds nothing.
        $this->post($route, $upload() + ['on_conflict' => 'skip']);
        $this->assertSame(2, File::where('folder_id', $folder->id)->count());
    }

    public function test_a_zip_archive_is_extracted_into_a_folder(): void
    {
        Storage::fake('files');
        $this->signIn();

        $zipPath = tempnam(sys_get_temp_dir(), 'z').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('docs/readme.txt', 'hello');
        $zip->close();
        Storage::disk('files')->put('files/archive.zip', file_get_contents($zipPath));
        @unlink($zipPath);

        $file = File::factory()->create([
            'name' => 'archive.zip',
            'type' => FileType::ARCHIVE,
            'disk_path' => 'files/archive.zip',
        ]);

        $this->post(route('files.extract', $file))->assertRedirect();

        $base = Folder::where('name', 'archive')->whereNull('parent_id')->first();
        $this->assertNotNull($base);
        $docs = Folder::where('name', 'docs')->where('parent_id', $base->id)->first();
        $this->assertNotNull($docs);
        $this->assertSame($docs->id, File::firstWhere('name', 'readme.txt')->folder_id);
    }

    public function test_extraction_rejects_zip_slip_entries(): void
    {
        Storage::fake('files');
        $this->signIn();

        $zipPath = tempnam(sys_get_temp_dir(), 'z').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('../evil.txt', 'pwned');
        $zip->close();
        Storage::disk('files')->put('files/bad.zip', file_get_contents($zipPath));
        @unlink($zipPath);

        $file = File::factory()->create([
            'name' => 'bad.zip', 'type' => FileType::ARCHIVE,
            'disk_path' => 'files/bad.zip',
        ]);

        $this->post(route('files.extract', $file))->assertRedirect();

        // Nothing extracted; no file/folder created from the malicious entry.
        $this->assertSame(0, File::where('name', 'evil.txt')->count());
        $this->assertNull(Folder::where('name', 'bad')->first());
    }

    public function test_a_text_file_can_be_edited_and_saved(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'disk_path' => 'files/note.txt', 'size' => 5, 'mime_type' => 'text/plain',
            'type' => FileType::DOCUMENT,
        ]);
        Storage::disk('files')->put('files/note.txt', 'hello');

        $this->get(route('files.edit', $file))->assertOk()->assertSee('hello');

        $this->put(route('files.content', $file), ['content' => 'new text'])->assertRedirect();

        $this->assertSame('new text', Storage::disk('files')->get('files/note.txt'));
        $file->refresh();
        $this->assertSame(8, $file->size);
        $this->assertSame(hash('sha256', 'new text'), $file->checksum);
    }

    public function test_a_binary_file_is_not_text_editable(): void
    {
        Storage::fake('files');
        $this->signIn();
        $file = File::factory()->create([
            'disk_path' => 'files/blob.bin', 'size' => 4, 'mime_type' => 'application/octet-stream',
            'type' => FileType::OTHER,
        ]);
        Storage::disk('files')->put('files/blob.bin', "\x00\x01\xff\xfe");

        $this->get(route('files.edit', $file))
            ->assertOk()
            ->assertSee(__('files.not_text_editable'));
    }

    public function test_conflicts_endpoint_reports_existing_paths(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Docs']);
        File::factory()->create(['name' => 'a.txt', 'folder_id' => $folder->id]);

        $this->postJson(route('files.conflicts'), [
            'paths' => ['a.txt', 'b.txt'],
            'folder_id' => $folder->id,
        ])->assertOk()->assertJson(['conflicts' => ['a.txt']]);
    }

    public function test_a_file_can_be_moved_into_a_folder(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Contracts']);
        $file = File::factory()->create();

        $this->put(route('files.update', $file), ['folder_id' => $folder->id])
            ->assertRedirect(route('files.show', $file));

        $this->assertSame($folder->id, $file->fresh()->folder_id);
    }

    public function test_browser_shows_a_folders_subfolders_and_files(): void
    {
        $this->signIn();
        $folder = Folder::create(['name' => 'Contracts']);
        Folder::create(['name' => 'Twenty-Six', 'parent_id' => $folder->id]);
        File::factory()->create(['name' => 'signed.pdf', 'folder_id' => $folder->id]);
        File::factory()->create(['name' => 'rootfile.pdf']);

        $this->get(route('files.index', ['folder' => $folder->id]))
            ->assertOk()
            ->assertSee('Twenty-Six')
            ->assertSee('signed.pdf')
            ->assertDontSee('rootfile.pdf');
    }

    public function test_root_lists_unfiled_files(): void
    {
        $this->signIn();
        File::factory()->create(['name' => 'rootfile.pdf']);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee('rootfile.pdf');
    }
}
