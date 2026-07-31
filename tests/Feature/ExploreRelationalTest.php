<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploreCoupling;
use App\Models\ExploreSetting;
use App\Models\ExploreTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Plaintext-relational Explore (pivot): track CRUD + optimistic version,
 * plaintext-at-rest of the location point list, coupling upsert/delete,
 * settings upsert, raw track-file upload/download + owner-scope, the
 * trash→restore→force lifecycle (blob removed, couplings cascade), and per-user
 * isolation.
 */
class ExploreRelationalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function trackPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Morning hike',
            'source_format' => 'recorded',
            'points' => [['lat' => 52.520008, 'lng' => 13.404954, 'ele' => 34, 't' => 1700000000]],
            'stats' => ['distanceM' => 1234, 'durationS' => 600, 'ascentM' => 42],
        ], $overrides);
    }

    public function test_track_crud_and_optimistic_conflict(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('explore.tracks.store'), $this->trackPayload())
            ->assertCreated()->assertJsonPath('track.source_format', 'recorded')->json('track.id');
        $this->postJson(route('explore.tracks.store'), $this->trackPayload(['name' => 'Evening walk', 'source_format' => 'planned']))->assertCreated();

        // Bad source_format rejected by validation (not persisted).
        $this->postJson(route('explore.tracks.store'), $this->trackPayload(['source_format' => 'bogus']));
        $this->assertSame(2, ExploreTrack::query()->count());

        $this->getJson(route('explore.data'))->assertOk()->assertJsonCount(2, 'tracks');

        // Optimistic version: matching bumps, stale → 409.
        $this->putJson(route('explore.tracks.update', $id), ['name' => 'Renamed', 'version' => 0])
            ->assertOk()->assertJsonPath('track.name', 'Renamed')->assertJsonPath('track.version', 1);
        $this->putJson(route('explore.tracks.update', $id), ['name' => 'Nope', 'version' => 0])->assertStatus(409);
        $this->assertSame('Renamed', ExploreTrack::findOrFail($id)->name);

        // Soft-delete via the route.
        $this->deleteJson(route('explore.tracks.destroy', $id))->assertOk();
        $this->assertSame(1, ExploreTrack::query()->count());
        $this->assertSame(1, ExploreTrack::onlyTrashed()->count());
    }

    public function test_track_points_and_note_stored_plaintext_at_rest(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('explore.tracks.store'), $this->trackPayload(['note' => 'secret-trailhead']))
            ->assertCreated()->json('track.id');

        $raw = DB::table('explore_tracks')->where('id', $id)->first();
        $this->assertNotNull($raw);
        // Location + note are plaintext at rest (encryption removed in v1.516.0).
        $this->assertStringContainsString('52.520008', (string) $raw->points);
        $this->assertStringContainsString('secret-trailhead', (string) $raw->note);
        // Aggregate stats stay plaintext for listing/sorting.
        $this->assertStringContainsString('1234', (string) $raw->stats);

        // Round-trips through the array cast (plaintext).
        $track = ExploreTrack::findOrFail($id);
        $this->assertEquals(52.520008, $track->points[0]['lat']);
        $this->assertSame('secret-trailhead', $track->note);
    }

    public function test_coupling_upsert_and_delete(): void
    {
        $this->actingAs(User::factory()->create());

        $a = $this->postJson(route('explore.tracks.store'), $this->trackPayload())->json('track.id');
        $b = $this->postJson(route('explore.tracks.store'), $this->trackPayload(['name' => 'Other']))->json('track.id');

        $this->postJson(route('explore.couplings.set'), ['photo_id' => 'photo-1', 'explore_track_id' => $a, 'lat' => 52.5, 'lng' => 13.4, 'source' => 'exif'])
            ->assertOk()->assertJsonPath('coupling.explore_track_id', $a);
        // Re-setting the same photo replaces the coupling (one per photo per user).
        $this->postJson(route('explore.couplings.set'), ['photo_id' => 'photo-1', 'explore_track_id' => $b, 'source' => 'manual'])
            ->assertOk()->assertJsonPath('coupling.explore_track_id', $b);
        $this->assertSame(1, ExploreCoupling::query()->count());
        $this->assertSame($b, ExploreCoupling::query()->firstOrFail()->explore_track_id);

        $this->getJson(route('explore.data'))->assertOk()->assertJsonCount(1, 'couplings');

        $this->deleteJson(route('explore.couplings.destroy'), ['photo_id' => 'photo-1'])->assertOk();
        $this->assertSame(0, ExploreCoupling::query()->count());
    }

    public function test_settings_upsert(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson(route('explore.settings.save'), ['coupling_time_tolerance_s' => 7200, 'coupling_distance_tolerance_m' => 250])
            ->assertOk()->assertJsonPath('settings.coupling_time_tolerance_s', 7200);
        // Second save updates the same row (one per user).
        $this->putJson(route('explore.settings.save'), ['coupling_time_tolerance_s' => 1800, 'coupling_distance_tolerance_m' => 50])
            ->assertOk()->assertJsonPath('settings.coupling_time_tolerance_s', 1800);

        $this->assertSame(1, ExploreSetting::query()->count());
        $this->getJson(route('explore.data'))->assertOk()->assertJsonPath('settings.coupling_distance_tolerance_m', 50);
    }

    public function test_track_file_upload_download_and_owner_scope(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $id = $this->postJson(route('explore.tracks.store'), $this->trackPayload())->json('track.id');

        $path = $this->postJson(route('explore.tracks.file.upload', $id), [
            'file' => UploadedFile::fake()->createWithContent('hike.gpx', '<gpx>points</gpx>'),
        ])->assertOk()->json('track.blob_path');

        $this->assertIsString($path);
        $this->assertStringStartsWith('explore/', $path);
        Storage::disk(config('files.disk'))->assertExists($path);

        $this->get(route('explore.tracks.file', $id))->assertOk();

        // Non-track extensions are rejected.
        $this->postJson(route('explore.tracks.file.upload', $id), [
            'file' => UploadedFile::fake()->createWithContent('evil.exe', 'MZ'),
        ])->assertStatus(422);

        // Another user cannot download this track's file (owner-scoped binding → 404).
        $this->actingAs(User::factory()->create());
        $this->get(route('explore.tracks.file', $id))->assertNotFound();
    }

    public function test_trash_restore_and_force_delete(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('explore.tracks.store'), $this->trackPayload())->json('track.id');
        $path = $this->postJson(route('explore.tracks.file.upload', $id), [
            'file' => UploadedFile::fake()->createWithContent('hike.gpx', '<gpx/>'),
        ])->json('track.blob_path');
        $this->postJson(route('explore.couplings.set'), ['photo_id' => 'p1', 'explore_track_id' => $id])->assertOk();

        // Route soft-delete removes the couplings but keeps the bytes.
        $this->deleteJson(route('explore.tracks.destroy', $id))->assertOk();
        $this->getJson(route('explore.trash'))->assertOk()->assertJsonCount(1, 'tracks');
        $this->assertSame(0, ExploreCoupling::query()->count());
        Storage::disk(config('files.disk'))->assertExists($path);

        // Restore brings it back to the active listing.
        $this->postJson(route('explore.tracks.restore', $id))->assertOk();
        $this->getJson(route('explore.trash'))->assertOk()->assertJsonCount(0, 'tracks');
        $this->getJson(route('explore.data'))->assertOk()->assertJsonCount(1, 'tracks');

        // Re-couple, then soft-delete directly (keeping the coupling) to prove the
        // force-delete FK cascade removes it along with the blob.
        $this->postJson(route('explore.couplings.set'), ['photo_id' => 'p1', 'explore_track_id' => $id])->assertOk();
        $couplingId = ExploreCoupling::query()->firstOrFail()->id;
        ExploreTrack::findOrFail($id)->delete();
        $this->assertDatabaseHas('explore_couplings', ['id' => $couplingId]);

        $this->deleteJson(route('explore.tracks.force', $id))->assertOk();
        Storage::disk(config('files.disk'))->assertMissing($path);
        $this->assertDatabaseMissing('explore_tracks', ['id' => $id]);
        $this->assertDatabaseMissing('explore_couplings', ['id' => $couplingId]);
    }

    public function test_explore_data_is_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = $this->actingAs($a)->postJson(route('explore.tracks.store'), $this->trackPayload())->json('track.id');

        $this->actingAs($b)->getJson(route('explore.data'))->assertOk()->assertJsonCount(0, 'tracks');
        // b's request is owner-scoped → the row is invisible → 404.
        $this->actingAs($b)->deleteJson(route('explore.tracks.destroy', $id))->assertNotFound();
    }
}
