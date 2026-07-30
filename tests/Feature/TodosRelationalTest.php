<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodosRelationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_crud_and_toggle(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('todos.store'), ['title' => 'Deploy', 'priority' => 'high', 'tags' => ['work']])
            ->assertCreated()->assertJsonPath('todo.priority', 'high')->json('todo.id');

        $this->getJson(route('todos.list'))->assertOk()->assertJsonCount(1, 'todos');

        $this->postJson(route('todos.toggle', $id), ['field' => 'done', 'value' => true])->assertOk()->assertJsonPath('todo.done', true);
        $this->putJson(route('todos.update', $id), ['title' => 'Deploy v2', 'version' => 0])->assertOk()->assertJsonPath('todo.title', 'Deploy v2');
        $this->putJson(route('todos.update', $id), ['title' => 'stale', 'version' => 0])->assertStatus(409);
    }

    public function test_lists_and_fk_null_on_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $listId = $this->postJson(route('todos.lists.store'), ['name' => 'Haus'])->assertCreated()->json('list.id');
        $taskId = $this->postJson(route('todos.store'), ['title' => 'Keller', 'todo_list_id' => $listId])->assertCreated()->json('todo.id');

        $this->deleteJson(route('todos.lists.destroy', $listId))->assertOk();
        // Task survives, unfiled.
        $this->assertNull(Todo::find($taskId)->todo_list_id);
        $this->assertNull(TodoList::find($listId));
    }

    public function test_trash_restore_force(): void
    {
        $this->actingAs(User::factory()->create());
        $id = $this->postJson(route('todos.store'), ['title' => 'X'])->json('todo.id');
        $this->deleteJson(route('todos.destroy', $id))->assertOk();
        $this->getJson(route('todos.list'))->assertJsonCount(0, 'todos');
        $this->getJson(route('todos.trash'))->assertJsonCount(1, 'todos');
        $this->postJson(route('todos.restore', $id))->assertOk();
        $this->getJson(route('todos.list'))->assertJsonCount(1, 'todos');
        $this->deleteJson(route('todos.destroy', $id))->assertOk();
        $this->deleteJson(route('todos.force', $id))->assertOk();
        $this->assertNull(Todo::withTrashed()->find($id));
    }

    public function test_url_sanitised_and_list_must_be_owned(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $foreignList = $this->actingAs($b)->postJson(route('todos.lists.store'), ['name' => 'B'])->json('list.id');

        $this->actingAs($a);
        // javascript: URL is dropped.
        $tid = $this->postJson(route('todos.store'), ['title' => 'x', 'url' => 'javascript:alert(1)'])->assertCreated()->json('todo.id');
        $this->assertNull(Todo::find($tid)->url);
        // Cannot attach to another user's list (validation exists-where user) — the row
        // is rejected, so no todo ends up pointing at the foreign list.
        $this->postJson(route('todos.store'), ['title' => 'y', 'todo_list_id' => $foreignList]);
        $this->assertSame(0, Todo::withoutGlobalScopes()->where('todo_list_id', $foreignList)->count());
    }

    public function test_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->postJson(route('todos.store'), ['title' => 'secret'])->assertCreated();
        $this->actingAs($b)->getJson(route('todos.list'))->assertJsonCount(0, 'todos');
    }
}
