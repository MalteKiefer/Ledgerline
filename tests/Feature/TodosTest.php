<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodosTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('todos.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_and_data_load(): void
    {
        $this->signIn();
        $this->get(route('todos.index'))->assertOk();
        $this->getJson(route('todos.data'))->assertOk()->assertJson(['lists' => [], 'tasks' => []]);
    }

    public function test_it_creates_a_task_with_tags(): void
    {
        $this->signIn();

        $this->postJson(route('todos.store'), [
            'title' => 'Buy milk', 'priority' => 'normal', 'tags' => ['shopping', 'home'],
        ])->assertOk()->assertJson(['title' => 'Buy milk', 'tags' => ['shopping', 'home']]);

        $this->assertSame(1, Todo::count());
    }

    public function test_patch_toggles_done(): void
    {
        $this->signIn();
        $todo = Todo::create(['title' => 'X', 'priority' => 'normal']);

        $this->patchJson(route('todos.patch', $todo), ['done' => true])
            ->assertOk()->assertJson(['done' => true]);
        $this->assertTrue($todo->refresh()->done);
    }

    public function test_empty_trash(): void
    {
        $this->signIn();
        Todo::create(['title' => 'T', 'priority' => 'normal', 'trashed_at' => now()]);

        $this->deleteJson(route('todos.trash.empty'))->assertOk();
        $this->assertSame(0, Todo::count());
    }

    public function test_deleting_a_list_keeps_its_tasks(): void
    {
        $this->signIn();
        $list = TodoList::create(['name' => 'Work']);
        $todo = Todo::create(['title' => 'Task', 'priority' => 'normal', 'todo_list_id' => $list->id]);

        $this->deleteJson(route('todos.lists.destroy', $list))->assertOk();

        $this->assertNull($todo->refresh()->todo_list_id);
        $this->assertSame(1, Todo::count());
    }

    public function test_todos_appear_in_global_search(): void
    {
        $this->signIn();
        Todo::create(['title' => 'Unique searchable todo', 'priority' => 'normal']);

        $this->getJson(route('search.suggest', ['q' => 'searchable']))
            ->assertOk()->assertSee('Unique searchable todo');
    }
}
