<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarTodo;
use App\Models\User;
use App\Services\Calendar\CalendarTodoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarTodoTest extends TestCase
{
    use RefreshDatabase;

    private function taskList(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId,
            'name' => 'Reminders',
            'uri' => 'tasks-'.$userId.'-'.Str::lower(Str::random(4)),
            'color' => '#6750a4',
            'component' => Calendar::COMPONENT_TODO,
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);
    }

    private function eventCalendar(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId,
            'name' => 'Personal',
            'uri' => 'cal-'.$userId.'-'.Str::lower(Str::random(4)),
            'color' => '#6750a4',
            'component' => Calendar::COMPONENT_EVENT,
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);
    }

    public function test_store_creates_a_task_and_bumps_the_sync_token(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);

        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Buy milk',
            'priority' => 1,
            'due' => '2026-08-10T17:00:00Z',
            'categories' => ['errand', 'home'],
        ])->assertStatus(201);

        $todo = CalendarTodo::firstOrFail();
        $this->assertSame('Buy milk', $todo->summary);
        $this->assertSame(1, $todo->priority);
        $this->assertSame('NEEDS-ACTION', $todo->status);
        $this->assertSame(['errand', 'home'], $todo->categories);
        $this->assertStringContainsString('BEGIN:VTODO', $todo->ics);
        $this->assertStringContainsString('SUMMARY:Buy milk', $todo->ics);
        $this->assertSame(2, $calendar->fresh()->synctoken);
        $this->assertDatabaseHas('calendar_todo_changes', ['operation' => 1]);
    }

    public function test_store_into_an_event_calendar_is_rejected(): void
    {
        $user = $this->signIn();
        $events = $this->eventCalendar($user->id);

        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $events->id, 'summary' => 'x',
        ])->assertStatus(422);
    }

    public function test_store_into_a_foreign_calendar_is_rejected(): void
    {
        $this->signIn();
        $foreign = $this->taskList(User::factory()->create()->id);

        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $foreign->id, 'summary' => 'x',
        ])->assertNotFound();
    }

    public function test_index_lists_filters_and_expands(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);

        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'Open', 'due' => '2026-08-05T09:00:00Z'])->assertStatus(201);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'Done', 'status' => 'COMPLETED'])->assertStatus(201);
        // A foreign task must never appear.
        $foreign = $this->taskList(User::factory()->create()->id);
        CalendarTodo::create([
            'calendar_id' => $foreign->id, 'user_id' => $foreign->user_id, 'uri' => 'x.ics',
            'etag' => 'e', 'ics' => 'BEGIN:VCALENDAR', 'summary' => 'Foreign',
        ]);

        $all = $this->getJson(route('calendar.todos'))->assertOk()->json('todos');
        $this->assertCount(2, $all);

        $open = $this->getJson(route('calendar.todos', ['status' => 'open']))->assertOk()->json('todos');
        $this->assertCount(1, $open);
        $this->assertSame('Open', $open[0]['summary']);
        $this->assertSame('#6750a4', $open[0]['color']);

        $due = $this->getJson(route('calendar.todos', ['due_before' => '2026-08-06T00:00:00Z']))->assertOk()->json('todos');
        $this->assertCount(1, $due);

        $expanded = $this->getJson(route('calendar.todos', ['expand' => 1]))->assertOk()->json('todos');
        $this->assertArrayHasKey('next_due', $expanded[0]);
    }

    public function test_show_returns_parsed_editor_data_and_update_bumps_sequence(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'A', 'due' => '2026-08-03T09:00:00Z'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $show = $this->getJson(route('calendar.todos.show', $todo))->assertOk()->json();
        $this->assertSame('A', $show['summary']);
        $this->assertNotEmpty($show['uid']);
        $this->assertSame($todo->etag, $show['etag']);
        $uid = $show['uid'];

        $this->putJson(route('calendar.todos.update', $todo), ['summary' => 'A2', 'due' => '2026-08-03T09:00:00Z'])
            ->assertOk()->assertJsonPath('ok', true);

        $todo->refresh();
        $this->assertSame('A2', $todo->summary);
        $this->assertSame(1, $todo->sequence);
        $this->assertSame($uid, app(CalendarTodoService::class)->parse($todo->ics)['uid']);
    }

    public function test_task_reminder_is_stored_and_exposed_in_the_list(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Ping', 'alarm_minutes_before' => 30,
        ])->assertStatus(201);

        // present() surfaces the alarm on the list row so the editor can prefill it.
        $todos = $this->getJson(route('calendar.todos'))->assertOk()->json('todos');
        $this->assertSame(30, $todos[0]['alarm_minutes_before']);
        $this->assertStringContainsString('TRIGGER:-PT30M', CalendarTodo::firstOrFail()->ics);
    }

    public function test_update_preserves_unmodelled_ics_properties_and_replaces_valarm(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'Orig', 'due' => '2026-08-03T09:00:00Z'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        // Inject a property the editor does not model (as a CalDAV client would).
        $todo->ics = str_replace('END:VTODO', "X-CUSTOM-FIELD:keepme\r\nEND:VTODO", $todo->ics);
        $todo->saveQuietly();

        $this->putJson(route('calendar.todos.update', $todo), [
            'summary' => 'New', 'due' => '2026-08-03T09:00:00Z', 'alarm_minutes_before' => 30,
        ])->assertOk();

        $todo->refresh();
        $this->assertStringContainsString('SUMMARY:New', $todo->ics);        // modelled field updated
        $this->assertStringContainsString('X-CUSTOM-FIELD:keepme', $todo->ics); // unmodelled preserved
        $this->assertStringContainsString('TRIGGER:-PT30M', $todo->ics);     // VALARM applied from editor
        $this->assertStringNotContainsString('SUMMARY:Orig', $todo->ics);
    }

    public function test_update_rejects_a_stale_etag_with_409(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'A'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $this->putJson(route('calendar.todos.update', $todo), ['summary' => 'B', 'etag' => 'stale'])
            ->assertStatus(409)->assertJsonPath('error', 'etag_conflict')->assertJsonPath('etag', $todo->etag);

        $this->putJson(route('calendar.todos.update', $todo), ['summary' => 'B', 'etag' => $todo->etag])->assertOk();
    }

    public function test_destroy_logs_a_tombstone(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'A'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $this->postJson(route('calendar.todos.complete', $todo)); // touch sync token first
        $this->deleteJson(route('calendar.todos.destroy', $todo))->assertOk();
        $this->assertDatabaseCount('calendar_todos', 0);
        $this->assertDatabaseHas('calendar_todo_changes', ['operation' => 3]);
    }

    public function test_complete_a_non_recurring_task_marks_it_completed(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'A', 'due' => '2026-08-03T09:00:00Z'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $this->postJson(route('calendar.todos.complete', $todo))
            ->assertOk()->assertJsonPath('rolled', false)->assertJsonPath('todo.status', 'COMPLETED');

        $todo->refresh();
        $this->assertSame('COMPLETED', $todo->status);
        $this->assertSame(100, $todo->percent_complete);
        $this->assertNotNull($todo->completed_at);
        $this->assertStringContainsString('STATUS:COMPLETED', $todo->ics);
    }

    public function test_complete_a_recurring_task_rolls_it_forward(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Weekly', 'due' => '2026-08-03T09:00:00Z',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        ])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $this->postJson(route('calendar.todos.complete', $todo))
            ->assertOk()->assertJsonPath('rolled', true)->assertJsonPath('todo.status', 'NEEDS-ACTION');

        $todo->refresh();
        // DUE advanced to the following Monday; the series stays active.
        $this->assertSame('2026-08-10T09:00:00Z', $todo->due?->toIso8601ZuluString());
        $this->assertSame('NEEDS-ACTION', $todo->status);
        $this->assertStringContainsString('RRULE', $todo->ics);
    }

    public function test_uncomplete_reopens_a_task(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'A', 'status' => 'COMPLETED'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $this->postJson(route('calendar.todos.uncomplete', $todo))
            ->assertOk()->assertJsonPath('todo.status', 'NEEDS-ACTION');
        $this->assertSame('NEEDS-ACTION', $todo->fresh()->status);
    }

    public function test_tasks_are_owner_scoped(): void
    {
        $owner = $this->signIn();
        $calendar = $this->taskList($owner->id);
        $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'Mine'])->assertStatus(201);
        $todo = CalendarTodo::firstOrFail();

        $this->signIn();
        $this->getJson(route('calendar.todos'))->assertOk()->assertJsonCount(0, 'todos');
        $this->getJson(route('calendar.todos.show', $todo))->assertNotFound();
        $this->putJson(route('calendar.todos.update', $todo), ['summary' => 'x'])->assertNotFound();
        $this->postJson(route('calendar.todos.complete', $todo))->assertNotFound();
        $this->deleteJson(route('calendar.todos.destroy', $todo))->assertNotFound();
    }

    public function test_reorder_sets_sort_order(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $a = $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'A'])->json('id');
        $b = $this->postJson(route('calendar.todos.store'), ['calendar_id' => $calendar->id, 'summary' => 'B'])->json('id');

        $this->postJson(route('calendar.todos.reorder'), ['order' => [$b, $a]])->assertOk();
        $this->assertSame(0, CalendarTodo::findOrFail($b)->sort_order);
        $this->assertSame(1, CalendarTodo::findOrFail($a)->sort_order);
    }

    public function test_ics_import_export_round_trip(): void
    {
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
            ."BEGIN:VTODO\r\nUID:t1\r\nSUMMARY:One\r\nDUE:20260803T090000Z\r\nSTATUS:NEEDS-ACTION\r\nEND:VTODO\r\n"
            ."BEGIN:VTODO\r\nUID:t2\r\nSUMMARY:Two\r\nPRIORITY:5\r\nEND:VTODO\r\n"
            ."END:VCALENDAR\r\n";

        $this->post(route('calendar.todos.import'), [
            'calendar_id' => $calendar->id,
            'file' => UploadedFile::fake()->createWithContent('t.ics', $ics),
        ])->assertOk()->assertJson(['created' => 2, 'updated' => 0]);
        $this->assertDatabaseCount('calendar_todos', 2);

        $out = $this->get(route('calendar.todos.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:One', $out);
        $this->assertStringContainsString('SUMMARY:Two', $out);
        $this->assertStringContainsString('BEGIN:VTODO', $out);

        // Re-import → dedupe by UID (update, not create).
        $this->post(route('calendar.todos.import'), [
            'calendar_id' => $calendar->id,
            'file' => UploadedFile::fake()->createWithContent('t.ics', $ics),
        ])->assertOk()->assertJson(['created' => 0, 'updated' => 2]);
        $this->assertDatabaseCount('calendar_todos', 2);
    }
}
