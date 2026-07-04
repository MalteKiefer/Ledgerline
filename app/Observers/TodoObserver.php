<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DavChangeOperation;
use App\Models\Todo;
use App\Services\Calendar\TodoVtodoBridge;
use App\Services\Contacts\DavChangeLog;

/**
 * Keeps the virtual VTODO calendars' CalDAV sync tokens in step with the shared
 * to-dos, so CalDAV clients see task changes on the next sync-collection REPORT.
 */
class TodoObserver
{
    public function __construct(
        private readonly DavChangeLog $changes,
        private readonly TodoVtodoBridge $bridge,
    ) {}

    public function created(Todo $todo): void
    {
        $this->record($todo, DavChangeOperation::Added);
    }

    public function updated(Todo $todo): void
    {
        $this->record($todo, DavChangeOperation::Modified);
    }

    public function deleted(Todo $todo): void
    {
        $this->record($todo, DavChangeOperation::Deleted);
    }

    public function restored(Todo $todo): void
    {
        $this->record($todo, DavChangeOperation::Added);
    }

    public function forceDeleted(Todo $todo): void
    {
        $this->record($todo, DavChangeOperation::Deleted);
    }

    private function record(Todo $todo, DavChangeOperation $op): void
    {
        $uri = $this->bridge->uriFor($todo);
        foreach ($this->bridge->tasksCalendars() as $calendar) {
            $this->changes->recordCalendar($calendar, $uri, $op);
        }
    }
}
