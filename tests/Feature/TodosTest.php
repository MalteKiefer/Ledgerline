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

    public function test_the_page_loads_without_a_vault(): void
    {
        $this->signIn();
        $this->get(route('todos.index'))->assertOk();
    }

    public function test_it_creates_a_task_with_tags(): void
    {
        $this->signIn();

        $this->post(route('todos.store'), [
            'title' => 'Buy milk', 'priority' => 'normal', 'tags' => 'shopping, home',
        ])->assertRedirect(route('todos.index'));

        $todo = Todo::firstWhere('title', 'Buy milk');
        $this->assertSame(['shopping', 'home'], $todo->tags);
    }

    public function test_trashing_and_emptying_trash(): void
    {
        $this->signIn();
        $todo = Todo::create(['title' => 'Temp', 'priority' => 'normal']);

        $this->post(route('todos.trash', $todo))->assertRedirect();
        $this->assertNotNull($todo->refresh()->trashed_at);

        $this->delete(route('todos.trash.empty'))->assertRedirect(route('todos.index'));
        $this->assertSame(0, Todo::count());
    }

    public function test_deleting_a_list_keeps_its_tasks(): void
    {
        $this->signIn();
        $list = TodoList::create(['name' => 'Work']);
        $todo = Todo::create(['title' => 'Task', 'priority' => 'normal', 'todo_list_id' => $list->id]);

        $this->delete(route('todos.lists.destroy', $list))->assertRedirect(route('todos.index'));

        $this->assertNull($todo->refresh()->todo_list_id);
        $this->assertSame(1, Todo::count());
    }

    public function test_todos_appear_in_global_search(): void
    {
        $this->signIn();
        Todo::create(['title' => 'Unique searchable todo', 'priority' => 'normal']);

        $this->getJson(route('search.suggest', ['q' => 'searchable']))
            ->assertOk()
            ->assertSee('Unique searchable todo');
    }
}
