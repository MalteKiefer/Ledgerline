<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tear down the dead per-to-do storage and the server-side reminder system.
 * To-dos now live entirely inside the zero-knowledge store (vault_store, one
 * sealed manifest per user), and due dates are sealed with them — so there are
 * no server reminders anymore. The per-row `todos`/`todo_lists` tables, the
 * `reminders` table, and the per-user `reminder_channels` default all have no
 * remaining server-side use. One-way cleanup: there is no rollback path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::dropIfExists('reminders');
            Schema::dropIfExists('todos');
            Schema::dropIfExists('todo_lists');
        });

        if (Schema::hasColumn('user_settings', 'reminder_channels')) {
            Schema::table('user_settings', function (Blueprint $table): void {
                $table->dropColumn('reminder_channels');
            });
        }
    }

    public function down(): void
    {
        // One-way cleanup; the removed tables/column have no rollback path.
    }
};
