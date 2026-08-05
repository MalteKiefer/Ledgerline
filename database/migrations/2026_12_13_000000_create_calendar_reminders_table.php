<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opaque reminder queue for the zero-knowledge calendar. The client computes when
 * each reminder should fire (expanding recurrences locally) and registers only an
 * absolute UTC timestamp per occurrence — NEVER the event title or content. The
 * scheduler pushes a GENERIC notification at that time (the server cannot see what
 * the reminder is for). Metadata trade-off documented in the security register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_id');            // client-side event id (opaque)
            $table->string('recurrence_id')->nullable(); // occurrence date (opaque)
            $table->timestamp('remind_at');        // absolute UTC instant to fire
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'remind_at']);
            $table->index(['remind_at', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_reminders');
    }
};
