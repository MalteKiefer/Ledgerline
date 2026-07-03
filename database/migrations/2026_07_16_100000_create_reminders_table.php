<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Server-side reminders for to-do due dates. The to-do itself stays
        // zero-knowledge; only the minimum needed to fire a notification lives
        // here (due time, channels, and the title/link — encrypted at rest).
        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('due_at');
            $table->json('channels');
            $table->text('title');           // encrypted
            $table->text('url')->nullable(); // encrypted
            $table->timestamp('fired_at')->nullable();
            $table->timestamps();
            $table->index(['fired_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
