<?php

declare(strict_types=1);

namespace App\Services\Todos;

use App\Models\AppSettings;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Selects due to-dos needing a reminder and resolves the owner's delivery
 * channels. Runs outside web auth (scheduled command) — the owner global scope
 * is inactive there, so queries see every user's rows and we scope explicitly
 * per todo. Selection + channel resolution live here so they stay unit-testable.
 */
class TodoReminders
{
    /**
     * Not-done, non-trashed to-dos whose due moment has arrived (due <= now) and
     * that haven't been reminded since that due moment. `reminded_at < due`
     * re-arms a reminder when the due date is pushed forward; a null marker means
     * never reminded. Owner-agnostic (all users).
     *
     * @return Collection<int, Todo>
     */
    public function dueForReminder(?Carbon $now = null): Collection
    {
        $now ??= Carbon::now();

        // Scan every user's rows (drop only the owner scope — soft-delete stays,
        // so trashed tasks are excluded); isolation is per-todo at send time.
        return Todo::query()
            ->withoutGlobalScope('owner')
            ->whereNotNull('due')
            ->where('done', false)
            ->where('due', '<=', $now)
            ->where(function ($w): void {
                $w->whereNull('reminded_at')->orWhereColumn('reminded_at', '<', 'due');
            })
            ->orderBy('due')
            ->get();
    }

    /**
     * The channels a reminder for the given owner is delivered on: the per-user
     * in-app bell ('desktop', always) plus whichever global channels the
     * workspace has enabled. An empty result means the owner is skipped.
     *
     * @return list<string>
     */
    public function channelsFor(int $userId): array
    {
        $s = AppSettings::current();

        return array_values(array_filter([
            $userId > 0 ? 'desktop' : null,
            $s->ntfy_enabled ? 'ntfy' : null,
            $s->webhook_enabled ? 'webhook' : null,
            $s->mail_enabled ? 'mail' : null,
        ]));
    }
}
