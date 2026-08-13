<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            // A reminder now belongs to a to-do (managed server-side); deleting
            // the to-do drops its reminder.
            $table->foreignId('todo_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('todo_id');
        });
    }
};
