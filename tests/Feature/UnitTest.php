<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_units_are_seeded_by_the_migration(): void
    {
        $this->assertGreaterThanOrEqual(7, Unit::count());
        $this->assertNotNull(Unit::firstWhere('code', 'h'));
    }

    public function test_labels_are_localised_and_the_zugferd_code_resolves(): void
    {
        $this->assertSame('Stunde', Unit::labelFor('h', 'de'));
        $this->assertSame('Hour', Unit::labelFor('h', 'en'));
        $this->assertSame('h-unknown', Unit::labelFor('h-unknown', 'en')); // falls back to the code
        $this->assertSame('HUR', Unit::zugferdCodeFor('h'));
        $this->assertSame('C62', Unit::zugferdCodeFor('nope'));
    }

    public function test_guests_cannot_manage_units(): void
    {
        $this->get(route('settings.units.index'))->assertRedirect(route('login'));
    }

    public function test_a_unit_can_be_created(): void
    {
        $this->signIn();

        $this->post(route('settings.units.store'), [
            'code' => 'wd', 'name_de' => 'Werktag', 'name_en' => 'Working day', 'zugferd_code' => 'DAY',
        ])->assertRedirect(route('settings.units.index'));

        $this->assertDatabaseHas('units', ['code' => 'wd', 'name_en' => 'Working day']);
    }

    public function test_create_rejects_a_duplicate_code(): void
    {
        $this->signIn();

        $this->post(route('settings.units.store'), ['code' => 'h', 'name_de' => 'x', 'name_en' => 'y', 'zugferd_code' => 'HUR'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_unit_can_be_updated_and_deleted(): void
    {
        $this->signIn();
        $unit = Unit::create(['code' => 'tmp', 'name_de' => 'Alt', 'name_en' => 'Old', 'zugferd_code' => 'C62']);

        $this->put(route('settings.units.update', $unit), ['code' => 'tmp', 'name_de' => 'Neu', 'name_en' => 'New', 'zugferd_code' => 'C62'])
            ->assertRedirect();
        $this->assertSame('New', $unit->fresh()->name_en);

        $this->delete(route('settings.units.destroy', $unit))->assertRedirect();
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }
}
