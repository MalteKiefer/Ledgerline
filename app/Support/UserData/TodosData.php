<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\User;

/**
 * Per-user data contributor for the to-dos module: exports and erases a user's
 * to-do tasks together with their attached reminder rows. To-dos own their data
 * via the `user_id` column; reminders hang off a to-do by `todo_id` and are
 * scoped transitively through it. Purge also removes the user's named to-do
 * lists (own their data via `user_id`) so none are left orphaned.
 */
final class TodosData implements UserDataContributor
{
    public function key(): string
    {
        return 'todos';
    }

    public function export(User $user): array
    {
        return Todo::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->getKey())
            ->with('reminder')
            ->orderBy('id')
            ->get()
            ->map(function (Todo $todo): array {
                $row = $todo->attributesToArray();

                $reminder = $todo->reminder;
                $row['reminder'] = $reminder instanceof Reminder
                    ? $reminder->attributesToArray()
                    : null;

                return $row;
            })
            ->all();
    }

    public function purge(User $user): void
    {
        $todoIds = Todo::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('user_id', $user->getKey())
            ->pluck('id');

        if ($todoIds->isNotEmpty()) {
            Reminder::query()
                ->whereIn('todo_id', $todoIds)
                ->delete();

            Todo::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->whereIn('id', $todoIds)
                ->forceDelete();
        }

        // Named lists the tasks hung off (owner column user_id); orphaned once
        // the tasks are gone, so purge them too.
        TodoList::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
