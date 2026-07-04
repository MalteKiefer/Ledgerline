<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\CalDavBackend;
use App\Dav\DavContext;
use App\Models\Calendar;
use App\Models\Todo;
use App\Services\Contacts\DavCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DavTodoBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDav(): CalDavBackend
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1);

        return app(CalDavBackend::class);
    }

    /** A to-do owned by the DAV principal (user 1); user_id is not fillable. */
    private function ownedTodo(array $attrs): Todo
    {
        $todo = Todo::create($attrs);
        $todo->forceFill(['user_id' => 1])->save();

        return $todo;
    }

    public function test_generate_creates_a_tasks_calendar(): void
    {
        app(DavCredentialService::class)->generate(1);

        $this->assertDatabaseHas('calendars', ['user_id' => 1, 'uri' => 'tasks', 'name' => 'Tasks']);
    }

    public function test_todos_appear_as_vtodo_objects(): void
    {
        $backend = $this->setUpDav();
        $tasks = Calendar::where('user_id', 1)->where('uri', 'tasks')->firstOrFail();
        $todo = $this->ownedTodo(['title' => 'Buy milk', 'priority' => 'high', 'due_at' => '2026-09-01 12:00:00']);

        $objects = $backend->getCalendarObjects($tasks->id);
        $this->assertCount(1, $objects);
        $this->assertSame('todo-'.$todo->id.'.ics', $objects[0]['uri']);

        $one = $backend->getCalendarObject($tasks->id, 'todo-'.$todo->id.'.ics');
        $this->assertStringContainsString('BEGIN:VTODO', $one['calendardata']);
        $this->assertStringContainsString('SUMMARY:Buy milk', $one['calendardata']);
        $this->assertStringContainsString('PRIORITY:1', $one['calendardata']);
        $this->assertStringContainsString('STATUS:NEEDS-ACTION', $one['calendardata']);
    }

    public function test_completing_a_vtodo_marks_the_todo_done(): void
    {
        $backend = $this->setUpDav();
        $tasks = Calendar::where('user_id', 1)->where('uri', 'tasks')->firstOrFail();
        $todo = $this->ownedTodo(['title' => 'Task', 'priority' => 'normal']);
        $uri = 'todo-'.$todo->id.'.ics';

        $completed = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTODO\r\nUID:todo-{$todo->id}\r\nSUMMARY:Task\r\nSTATUS:COMPLETED\r\nEND:VTODO\r\nEND:VCALENDAR\r\n";
        $etag = $backend->updateCalendarObject($tasks->id, $uri, $completed);

        $this->assertNotNull($etag);
        $this->assertTrue($todo->fresh()->done);
    }

    public function test_client_create_on_tasks_is_rejected(): void
    {
        // To-dos are the source of truth and are created inside the app; a
        // client-initiated VTODO create cannot be honoured at its chosen URI
        // (we expose todo-<id>.ics), so it is rejected rather than mis-stored.
        $backend = $this->setUpDav();
        $tasks = Calendar::where('user_id', 1)->where('uri', 'tasks')->firstOrFail();

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTODO\r\nUID:new-1\r\nSUMMARY:From client\r\nPRIORITY:9\r\nEND:VTODO\r\nEND:VCALENDAR\r\n";
        $this->assertNull($backend->createCalendarObject($tasks->id, 'new-1.ics', $ics));
        $this->assertSame(0, Todo::where('title', 'From client')->count());
    }

    public function test_deleting_a_vtodo_trashes_the_todo(): void
    {
        $backend = $this->setUpDav();
        $tasks = Calendar::where('user_id', 1)->where('uri', 'tasks')->firstOrFail();
        $todo = $this->ownedTodo(['title' => 'Bye', 'priority' => 'normal']);

        $backend->deleteCalendarObject($tasks->id, 'todo-'.$todo->id.'.ics');
        $this->assertSoftDeleted('todos', ['id' => $todo->id]);
    }

    public function test_a_todo_change_bumps_the_tasks_calendar_sync_token(): void
    {
        app(DavCredentialService::class)->generate(1);
        $tasks = Calendar::where('user_id', 1)->where('uri', 'tasks')->firstOrFail();
        $before = $tasks->synctoken;

        $this->ownedTodo(['title' => 'Sync me', 'priority' => 'normal']);

        $this->assertGreaterThan($before, $tasks->fresh()->synctoken);
        $this->assertDatabaseHas('calendar_changes', ['calendar_id' => $tasks->id]);
    }
}
