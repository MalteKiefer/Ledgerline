<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adopt Laravel's SoftDeletes on notes, todos, bookmarks and files: rename the
 * hand-rolled `trashed_at` column to the framework's `deleted_at` (data is
 * preserved by the rename).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['notes', 'todos', 'bookmarks', 'files'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->renameColumn('trashed_at', 'deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->renameColumn('deleted_at', 'trashed_at');
            });
        }
    }
};
