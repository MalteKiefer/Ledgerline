<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_weekly_todo_spawns_next_occurrence(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('todos.store'), [
            'title' => 'Water plants',
            'recurrence' => 'weekly',
            'due' => '2026-08-01T09:00:00',
        ])->assertCreated()->assertJsonPath('todo.recurrence', 'weekly')->json('todo.id');

        $this->postJson(route('todos.toggle', $id), ['field' => 'done', 'value' => true])->assertOk();

        // Original stays as done history (recurrence cleared); a fresh occurrence appears.
        $this->assertSame(2, Todo::count());
        $next = Todo::where('done', false)->where('recurrence', 'weekly')->first();
        $this->assertNotNull($next);
        $this->assertNotSame($id, $next->id);
        $this->assertSame('2026-08-08', $next->due?->toDateString());
        $this->assertFalse($next->done);
        $this->assertFalse($next->marked);
        $this->assertSame('Water plants', $next->title);
    }

    public function test_completing_non_recurring_spawns_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('todos.store'), ['title' => 'One-off', 'due' => '2026-08-01T09:00:00'])->assertCreated()->json('todo.id');

        $this->postJson(route('todos.toggle', $id), ['field' => 'done', 'value' => true])->assertOk();

        $this->assertSame(1, Todo::count());
    }

    public function test_toggling_done_undone_done_does_not_double_spawn(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('todos.store'), ['title' => 'Recur', 'recurrence' => 'daily', 'due' => '2026-08-01T09:00:00'])->assertCreated()->json('todo.id');

        $this->postJson(route('todos.toggle', $id), ['field' => 'done', 'value' => true])->assertOk(); // spawn (2)
        $this->postJson(route('todos.toggle', $id), ['field' => 'done', 'value' => false])->assertOk(); // undone
        $this->postJson(route('todos.toggle', $id), ['field' => 'done', 'value' => true])->assertOk(); // no spawn

        $this->assertSame(2, Todo::count());
    }

    public function test_completing_via_update_spawns_once(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('todos.store'), ['title' => 'Monthly report', 'recurrence' => 'monthly', 'due' => '2026-08-01T09:00:00'])->assertCreated()->json('todo.id');

        $this->putJson(route('todos.update', $id), ['title' => 'Monthly report', 'recurrence' => 'monthly', 'due' => '2026-08-01T09:00:00', 'done' => true, 'version' => 0])->assertOk();

        $this->assertSame(2, Todo::count());
        $next = Todo::where('done', false)->where('recurrence', 'monthly')->first();
        $this->assertNotNull($next);
        $this->assertSame('2026-09-01', $next->due?->toDateString());
    }
}
