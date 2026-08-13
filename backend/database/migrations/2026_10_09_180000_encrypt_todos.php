<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge encryption for to-dos. The browser seals {title, description,
 * url, tags} into enc_todo with the per-user vault key; plaintext columns stay
 * null and is_encrypted is true. Kept plaintext (scheduling/sort metadata, not
 * content): priority, marked, due_at, done, todo_list_id, reminder_channels.
 * To-do list names are sealed too. Reminders no longer hold the title/url — the
 * server can't read the sealed content, so due reminders fire a generic message.
 * Existing plaintext to-dos/lists are wiped (they cannot be re-encrypted server
 * side, matching the notes/bookmarks rollout).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->longText('enc_todo')->nullable()->after('url');
            $table->boolean('is_encrypted')->default(false)->after('enc_todo');
            $table->string('title')->nullable()->change();
        });
        Schema::table('todo_lists', function (Blueprint $table): void {
            $table->boolean('is_encrypted')->default(false)->after('name');
            $table->string('name', 4096)->change();
        });

        // Plaintext to-dos/lists cannot be re-sealed server-side — wipe them.
        DB::table('reminders')->delete();
        DB::table('todos')->delete();
        DB::table('todo_lists')->delete();

        Schema::table('reminders', function (Blueprint $table): void {
            // The server must not hold readable to-do content; reminders fire a
            // generic "a to-do is due" message now.
            $table->dropColumn(['title', 'url']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->text('title')->nullable();
            $table->text('url')->nullable();
        });
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropColumn(['enc_todo', 'is_encrypted']);
        });
        Schema::table('todo_lists', function (Blueprint $table): void {
            $table->dropColumn('is_encrypted');
        });
    }
};
