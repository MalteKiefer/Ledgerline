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

    public function test_it_creates_a_task_with_a_sealed_blob(): void
    {
        $this->signIn();

        // Title/description/url/tags now live inside the sealed enc_todo blob.
        $this->postJson(route('todos.store'), [
            'enc_todo' => 'sealed-task-blob', 'priority' => 'normal',
        ])->assertOk()->assertJson(['enc_todo' => 'sealed-task-blob']);

        $this->assertSame(1, Todo::count());
        $todo = Todo::first();
        $this->assertSame('sealed-task-blob', $todo->enc_todo);
        $this->assertTrue($todo->is_encrypted);
        $this->assertNull($todo->title);
        $this->assertNull($todo->tags);
    }

    public function test_patch_toggles_done(): void
    {
        $this->signIn();
        $todo = Todo::create(['enc_todo' => 'sealed', 'is_encrypted' => true, 'priority' => 'normal']);

        $this->patchJson(route('todos.patch', $todo), ['done' => true])
            ->assertOk()->assertJson(['done' => true]);
        $this->assertTrue($todo->refresh()->done);
    }

    public function test_empty_trash(): void
    {
        $this->signIn();
        Todo::create(['enc_todo' => 'sealed', 'is_encrypted' => true, 'priority' => 'normal'])->delete();

        $this->deleteJson(route('todos.trash.empty'))->assertOk();
        $this->assertSame(0, Todo::withTrashed()->count());
    }

    public function test_deleting_a_list_keeps_its_tasks(): void
    {
        $this->signIn();
        $list = TodoList::create(['name' => 'sealed-list-name', 'is_encrypted' => true]);
        $todo = Todo::create(['enc_todo' => 'sealed', 'is_encrypted' => true, 'priority' => 'normal', 'todo_list_id' => $list->id]);

        $this->deleteJson(route('todos.lists.destroy', $list))->assertOk();

        $this->assertNull($todo->refresh()->todo_list_id);
        $this->assertSame(1, Todo::count());
    }
}
