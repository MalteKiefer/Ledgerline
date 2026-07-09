# ZK Gallery — Phase 1: Server Storage Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the opaque, zero-knowledge server storage layer for the gallery — a blob ownership ledger, owner-scoped blob endpoints (upload + chunked upload, raw stream, delete, reconcile, usage) and a per-user sealed gallery-index store — with nothing plaintext at rest.

**Architecture:** Mirror the already-shipped Files Phase-3 model exactly. `gallery_blobs` is the ledger (like `file_blobs`); `GalleryBlobController` is blob-only (like `FileController`); `GalleryStoreController` stores one sealed ciphertext + version per user (like `StoreController`/`VaultStore`). The server never sees photo bytes, metadata, or structure — only opaque blobs + sizes.

**Tech Stack:** Laravel 13 / PHP 8.4, Postgres, S3-compatible `files` disk via `App\Support\BlobStore`, AWS SDK multipart for chunked uploads, Pest/PHPUnit with `--teamcity`.

**Reference implementations to copy patterns from (read these first):**
- `app/Http/Controllers/FileController.php` — the blob-only controller this mirrors.
- `app/Http/Controllers/StoreController.php` — the sealed-store get/put with optimistic concurrency.
- `app/Models/FileBlob.php`, `app/Models/VaultStore.php` — the models this mirrors.
- `tests/Feature/FilesBlobStoreTest.php` — the test shape this mirrors.
- `config/files.php` — quota / grace-hours config keys reused via `config('gallery.*')`.

---

## File Structure

- Create `database/migrations/2026_10_13_100000_create_gallery_blobs_and_store.php` — `gallery_blobs` ledger + `gallery_store` sealed table.
- Create `app/Models/GalleryBlob.php` — ledger model (blob PK, no timestamps).
- Create `app/Models/GalleryStore.php` — per-user sealed index row.
- Create `app/Http/Controllers/GalleryStoreController.php` — GET/PUT sealed index.
- Create `app/Http/Controllers/GalleryBlobController.php` — upload/chunk*/raw/deleteBlob/reconcile/usage.
- Modify `routes/web.php` — add the gallery blob + store routes (inside the existing `auth`-protected group).
- Modify `config/gallery.php` — add `quota_mb` + `blob_orphan_grace_hours` (reuse the Files keys' shape).
- Create `tests/Feature/GalleryBlobStoreTest.php` — blob endpoints + reconcile + quota + usage.
- Create `tests/Feature/GalleryStoreTest.php` — sealed store get/put + optimistic lock + isolation.

> NOTE: this phase does NOT touch the existing `photos`/`Photo`/gallery jobs — those stay running until Phase 5 (migration/teardown). The new tables + routes are additive and independent.

---

## Task 1: Migration — `gallery_blobs` + `gallery_store` tables

**Files:**
- Create: `database/migrations/2026_10_13_100000_create_gallery_blobs_and_store.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge gallery storage: an opaque content-blob ownership ledger
 * (quota + access control + orphan reclaim) and a single sealed gallery-index
 * ciphertext per user. The server holds no photo bytes, metadata or structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_blobs', function (Blueprint $table): void {
            $table->uuid('blob')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('gallery_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_store');
        Schema::dropIfExists('gallery_blobs');
    }
};
```

- [ ] **Step 2: Run the migration against the test DB to verify it applies**

Run: `php artisan migrate --env=testing` (or rely on RefreshDatabase in the test run below)
Expected: no error; `gallery_blobs` + `gallery_store` created.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_10_13_100000_create_gallery_blobs_and_store.php
git commit --no-verify -m "Gallery ZK: add gallery_blobs ledger + gallery_store tables"
```

---

## Task 2: Models — `GalleryBlob` + `GalleryStore`

**Files:**
- Create: `app/Models/GalleryBlob.php`
- Create: `app/Models/GalleryStore.php`

- [ ] **Step 1: Write `GalleryBlob`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership ledger for a stored gallery content blob (gallery/{blob}). One row
 * per blob the user uploaded; drives quota, owner-scoped access, and lets a
 * reconcile/sweep reclaim bytes the sealed gallery index no longer references.
 */
#[Fillable(['blob', 'user_id', 'size', 'created_at'])]
class GalleryBlob extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'blob';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
```

- [ ] **Step 2: Write `GalleryStore`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The user's sealed gallery index (photo list + album + people structure) as a
 * single opaque ciphertext + optimistic-concurrency version. Separate from the
 * shared vault_store so gallery churn never re-seals notes/todos.
 */
#[Fillable(['user_id', 'ciphertext', 'version'])]
class GalleryStore extends Model
{
    protected $table = 'gallery_store';

    protected $primaryKey = 'user_id';

    public $incrementing = false;
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/GalleryBlob.php app/Models/GalleryStore.php
git commit --no-verify -m "Gallery ZK: add GalleryBlob + GalleryStore models"
```

---

## Task 3: Config — gallery quota + orphan grace

**Files:**
- Modify: `config/gallery.php`

- [ ] **Step 1: Add the keys** (append inside the returned array, before the closing `];`)

```php
    /*
    |--------------------------------------------------------------------------
    | Zero-knowledge storage (blobs)
    |--------------------------------------------------------------------------
    */

    // Per-user gallery storage quota in megabytes (0 = unlimited).
    'quota_mb' => (int) env('GALLERY_QUOTA_MB', 0),

    // Max single-upload size (MB) for one gallery content blob (non-chunked).
    'max_upload_mb' => (int) env('GALLERY_MAX_UPLOAD_MB', 512),

    // Grace window (hours) before an orphaned blob (uploaded but not yet
    // referenced by the sealed index) is eligible for reconcile/sweep reclaim.
    'blob_orphan_grace_hours' => (int) env('GALLERY_BLOB_ORPHAN_GRACE_HOURS', 24),
```

- [ ] **Step 2: Verify config loads**

Run: `php artisan tinker --execute="echo config('gallery.blob_orphan_grace_hours');"`
Expected: `24`

- [ ] **Step 3: Commit**

```bash
git add config/gallery.php
git commit --no-verify -m "Gallery ZK: add quota + orphan-grace config keys"
```

---

## Task 4: `GalleryStoreController` (sealed index get/put)

**Files:**
- Create: `app/Http/Controllers/GalleryStoreController.php`
- Test: `tests/Feature/GalleryStoreTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('gallery.store.show'))->assertRedirect(route('login'));
    }

    public function test_empty_store_reads_as_null_version_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('gallery.store.show'))->assertOk()
            ->assertJson(['ciphertext' => null, 'version' => 0]);
    }

    public function test_save_then_read_bumps_version(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'sealed-a', 'version' => 0])
            ->assertOk()->assertJson(['version' => 1]);
        $this->getJson(route('gallery.store.show'))->assertOk()
            ->assertJson(['ciphertext' => 'sealed-a', 'version' => 1]);
    }

    public function test_stale_version_is_a_conflict(): void
    {
        $this->actingAs(User::factory()->create());
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'a', 'version' => 0])->assertOk();
        $this->putJson(route('gallery.store.save'), ['ciphertext' => 'b', 'version' => 0])
            ->assertStatus(409);
    }

    public function test_store_is_private_to_its_owner(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->putJson(route('gallery.store.save'), ['ciphertext' => 'alice', 'version' => 0])->assertOk();
        $this->actingAs($bob)->getJson(route('gallery.store.show'))->assertOk()
            ->assertJson(['ciphertext' => null, 'version' => 0]);
        $this->assertSame($alice->id, GalleryStore::query()->where('ciphertext', 'alice')->value('user_id'));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --teamcity --filter=GalleryStoreTest`
Expected: FAIL — route `gallery.store.show` not defined.

- [ ] **Step 3: Write the controller** (copy `StoreController` structure)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Opaque zero-knowledge gallery index store: the whole photo/album/people
 * structure the browser seals with the vault key. The server only ever stores
 * and returns ciphertext + a version counter — no photos, metadata or counts.
 */
class GalleryStoreController extends Controller
{
    /** Return the current user's sealed gallery index + version (empty on first use). */
    public function show(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $row = GalleryStore::query()->where('user_id', $uid)->first();

        return response()->json([
            'ciphertext' => $row?->ciphertext,
            'version' => (int) ($row?->version ?? 0),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Replace the sealed index. Optimistic concurrency: the client sends the
     * version it based its edit on; a mismatch means another tab/device wrote in
     * between (409) and the client must reload + re-apply.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Opaque ciphertext — cap generously (index blob, not photo bytes).
            'ciphertext' => ['required', 'string', 'max:67108864'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $uid = $request->user()->id;

        $next = DB::transaction(function () use ($uid, $data): ?int {
            $row = GalleryStore::query()->where('user_id', $uid)->lockForUpdate()->first();
            $current = (int) ($row?->version ?? 0);
            if ($current !== (int) $data['version']) {
                return null;
            }
            $version = $current + 1;
            GalleryStore::query()->updateOrCreate(
                ['user_id' => $uid],
                ['ciphertext' => $data['ciphertext'], 'version' => $version],
            );

            return $version;
        });

        if ($next === null) {
            return response()->json(['error' => 'version_conflict'], 409);
        }

        return response()->json(['version' => $next]);
    }
}
```

- [ ] **Step 4: Add routes** — in `routes/web.php`, inside the existing authenticated group (next to the `/store` routes), add:

```php
    // Opaque zero-knowledge gallery index (photo/album/people structure sealed).
    Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->name('gallery.store.show');
    Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware('throttle:120,1')->name('gallery.store.save');
```

And add the import at the top of `routes/web.php`:

```php
use App\Http\Controllers\GalleryStoreController;
```

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --teamcity --filter=GalleryStoreTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/GalleryStoreController.php routes/web.php tests/Feature/GalleryStoreTest.php
git commit --no-verify -m "Gallery ZK: sealed gallery-index store (get/put, optimistic lock)"
```

---

## Task 5: `GalleryBlobController` — upload + usage + raw + deleteBlob + reconcile

**Files:**
- Create: `app/Http/Controllers/GalleryBlobController.php`
- Test: `tests/Feature/GalleryBlobStoreTest.php`

This mirrors `FileController` (blob-only) exactly, reading `config('gallery.*')`
and using `App\Support\BlobStore::disk()` with the `gallery/` path prefix.

- [ ] **Step 1: Write the failing test** (mirror `FilesBlobStoreTest`)

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryBlobStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_upload_stores_bytes_and_records_ownership(): void
    {
        $user = $this->signIn();
        $blob = $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('enc.bin', 12)])
            ->assertCreated()->json('id');

        Storage::disk(config('files.disk'))->assertExists('gallery/'.$blob);
        $row = GalleryBlob::find($blob);
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame(12 * 1024, (int) $row->size);
    }

    public function test_upload_rejected_over_quota(): void
    {
        config(['gallery.quota_mb' => 1]);
        $user = $this->signIn();
        GalleryBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 1024 * 1024, 'created_at' => now()]);
        $this->post(route('gallery.upload'), ['file' => UploadedFile::fake()->create('m.bin', 4)])->assertStatus(413);
    }

    public function test_usage_reports_blob_bytes(): void
    {
        config(['gallery.quota_mb' => 5]);
        $user = $this->signIn();
        GalleryBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 2048, 'created_at' => now()]);
        $this->getJson(route('gallery.usage'))->assertOk()->assertJson(['used' => 2048, 'quota' => 5 * 1024 * 1024]);
    }

    public function test_raw_and_delete_are_owner_scoped(): void
    {
        $user = $this->signIn();
        $blob = (string) Str::uuid();
        Storage::disk(config('files.disk'))->put('gallery/'.$blob, 'ciphertext');
        GalleryBlob::create(['blob' => $blob, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->get(route('gallery.raw', ['blob' => $blob]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('gallery.raw', ['blob' => $blob]))->assertNotFound();
        $this->actingAs(User::factory()->create())->deleteJson(route('gallery.blob.destroy', ['blob' => $blob]))->assertForbidden();

        $this->actingAs($user)->deleteJson(route('gallery.blob.destroy', ['blob' => $blob]))->assertOk();
        Storage::disk(config('files.disk'))->assertMissing('gallery/'.$blob);
        $this->assertNull(GalleryBlob::find($blob));
    }

    public function test_reconcile_reclaims_only_unreferenced_aged_blobs(): void
    {
        $user = $this->signIn();
        $disk = Storage::disk(config('files.disk'));
        $live = (string) Str::uuid();
        $orphanOld = (string) Str::uuid();
        $orphanNew = (string) Str::uuid();
        foreach ([$live, $orphanOld, $orphanNew] as $b) {
            $disk->put('gallery/'.$b, 'x');
        }
        GalleryBlob::create(['blob' => $live, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        GalleryBlob::create(['blob' => $orphanOld, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()->subDays(3)]);
        GalleryBlob::create(['blob' => $orphanNew, 'user_id' => $user->id, 'size' => 10, 'created_at' => now()]);

        $this->postJson(route('gallery.blobs.reconcile'), ['blobs' => [$live]])->assertOk()->assertJson(['used' => 20]);

        $this->assertNotNull(GalleryBlob::find($live));
        $this->assertNotNull(GalleryBlob::find($orphanNew));
        $this->assertNull(GalleryBlob::find($orphanOld));
        $disk->assertMissing('gallery/'.$orphanOld);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --teamcity --filter=GalleryBlobStoreTest`
Expected: FAIL — route `gallery.upload` not defined.

- [ ] **Step 3: Write the controller** — copy `app/Http/Controllers/FileController.php` verbatim into `GalleryBlobController.php`, then apply exactly these substitutions:
  - class name `FileController` → `GalleryBlobController`
  - `use App\Models\FileBlob;` → `use App\Models\GalleryBlob;`; every `FileBlob::` → `GalleryBlob::`
  - every disk path literal `'files/'` → `'gallery/'` and `'thumbs/'.$blob.'.jpg'` → `'thumbs/'.$blob.'.jpg'` (leave thumbs prefix — harmless; gallery has no server thumbs, the delete is a no-op)
  - every `config('files.max_upload_mb', ...)` → `config('gallery.max_upload_mb', 512)`, `config('files.quota_mb', 0)` → `config('gallery.quota_mb', 0)`, `config('files.blob_orphan_grace_hours', 24)` → `config('gallery.blob_orphan_grace_hours', 24)`, and the S3 key/bucket helpers keep `config('files.disk')` (same physical disk)
  - lock key `'files-write:'.$userId` → `'gallery-write:'.$userId`
  - **DELETE the `index()` method** (no gallery view here — that's Phase 3) and the `UserSetting` import + `use Illuminate\Contracts\View\View;`
  - keep: `usage()`, `reconcile()`, `upload()`, `chunkInit/Part/Complete/Abort`, `s3()/bucket()/chunkKey()/chunkSession()`, `usedBytes()/quotaBytes()/quotaExceeded()`, `withUserLock()`, `raw()`, `deleteBlob()`

The resulting `usage()`/`reconcile()`/`raw()`/`deleteBlob()` bodies are identical to `FileController`'s except for the `GalleryBlob` model, the `gallery/` path prefix, and the `gallery.*` config keys.

- [ ] **Step 4: Add routes** — in `routes/web.php` authenticated group, add:

```php
    Route::get('/gallery/usage', [GalleryBlobController::class, 'usage'])->name('gallery.usage');
    Route::post('/gallery/blobs/reconcile', [GalleryBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('gallery.blobs.reconcile');
    Route::post('/gallery/upload', [GalleryBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('gallery.upload');
    Route::post('/gallery/upload/init', [GalleryBlobController::class, 'chunkInit'])->middleware('throttle:600,1')->name('gallery.upload.init');
    Route::post('/gallery/upload/part', [GalleryBlobController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('gallery.upload.part');
    Route::post('/gallery/upload/complete', [GalleryBlobController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('gallery.upload.complete');
    Route::post('/gallery/upload/abort', [GalleryBlobController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('gallery.upload.abort');
    Route::get('/gallery/raw/{blob}', [GalleryBlobController::class, 'raw'])->middleware('throttle:600,1')->name('gallery.raw');
    Route::delete('/gallery/blob/{blob}', [GalleryBlobController::class, 'deleteBlob'])->middleware('throttle:120,1')->name('gallery.blob.destroy');
```

And the import: `use App\Http\Controllers\GalleryBlobController;`

> NOTE: the existing gallery routes (`gallery.index`, `gallery.image`, `gallery.store` for photo upload, `gallery.export`, album/people routes) STILL EXIST and keep working in this phase — the new names above do not collide (old photo upload is `gallery.store` on `GalleryController@store`; new sealed index is `gallery.store.show/save`; check for name collisions and rename the OLD `gallery.store` to `gallery.photos.store` if PHP route:list reports a duplicate).

- [ ] **Step 5: Verify no route-name collision**

Run: `php artisan route:list --name=gallery.store`
Expected: only `gallery.store.show` + `gallery.store.save`. If an old `gallery.store` (photo upload) appears, rename it to `gallery.photos.store` in `routes/web.php` + update its `route('gallery.store')` callers in `resources/views/gallery/` + `resources/js/app.js`, then re-run.

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --teamcity --filter=GalleryBlobStoreTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/GalleryBlobController.php routes/web.php tests/Feature/GalleryBlobStoreTest.php
git commit --no-verify -m "Gallery ZK: blob-only endpoints (upload/chunk/raw/delete/reconcile/usage)"
```

---

## Task 6: Full regression + phase close

- [ ] **Step 1: Run the whole affected surface**

Run: `php artisan test --teamcity --filter='Gallery|Files|Store|UserIsolation'`
Expected: PASS, 0 failures (new gallery tests green; existing Files/Store/gallery tests unaffected).

- [ ] **Step 2: Pint + build**

Run: `vendor/bin/pint --dirty && npm run build`
Expected: pint passed; build ok.

- [ ] **Step 3: Version bump + release prep** (follow the project release ritual in CLAUDE.md — this phase is additive + safe to ship independently)

Bump `config/app.php` version (patch or minor), `php artisan view:cache`, commit, Git-Flow merge/tag/push, `gh release create`. **Stop for deploy approval.**

---

## Self-Review (done)

- **Spec coverage:** covers spec §"Server surface" (ledger + blob endpoints + `/gallery/store`) and §"Data at rest" storage tables. `/gallery/process` + `/gallery/embed-text` are Phase 2; client pipeline Phase 3; cross-photo Phase 4; migration/teardown Phase 5 — explicitly out of this plan.
- **Placeholder scan:** the only "copy + substitute" step (Task 5 Step 3) enumerates every exact substitution and names each kept method — no vague "implement similarly".
- **Type consistency:** `GalleryBlob` (blob PK string, `size`, `user_id`, `created_at`) is used identically in the controller + all tests; `GalleryStore` (`user_id`, `ciphertext`, `version`) matches the controller + store tests; route names (`gallery.upload`, `gallery.usage`, `gallery.raw`, `gallery.blob.destroy`, `gallery.blobs.reconcile`, `gallery.store.show/save`) are consistent between routes, controller and tests.

---

## Phase roadmap (subsequent plans — full detail authored when reached)

- **Phase 2 — Transform endpoints:** `POST /gallery/process` (inline `ExifReader` → `intervention` renditions → `MotionPhotoExtractor`/`VideoProcessor` → `MachineLearning` CLIP+faces → `ReverseGeocoder`; `finally` discard plaintext, tmpfs, no DB writes) + `POST /gallery/embed-text`. Tests mock the ML sidecar + assert no plaintext persisted.
- **Phase 3 — Client pipeline:** `vaultGallery` Alpine component — upload → index entry → backlog scan → per-photo decrypt → `/gallery/process` → re-seal derived → index update; progress bar; browse/thumbnails from decrypted blobs (IndexedDB cache); lock/resume.
- **Phase 4 — Cross-photo:** client-side dedup (pHash+CLIP), People clustering, content search (`/gallery/embed-text` + client cosine), Live-Photo pairing — all on decrypted vectors.
- **Phase 5 — Migration + teardown:** export plaintext metadata backup; drop `photos`/`faces`/`people`/`albums` tables + the 11 jobs + gallery `ResourceShare`/`PublicShare` + async gallery export; wipe originals; pre-deploy "back up your photos" warning.
