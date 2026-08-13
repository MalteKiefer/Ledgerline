<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendars + calendar objects (CalDAV) + their own sync-collection change log
 * (calendar_changes, mirroring dav_changes but separate so the contacts path is
 * untouched). The raw ICS is the source of truth; denormalised columns drive the
 * UI + time-range REPORT. Subscription columns (K5) are added now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->string('name');
            $table->string('uri');
            $table->string('color', 9)->nullable();
            $table->string('description')->nullable();
            $table->json('components')->nullable(); // ["VEVENT"] / ["VTODO"]
            $table->unsignedBigInteger('synctoken')->default(1);
            // Subscriptions (read-only remote ICS feeds).
            $table->text('subscription_url')->nullable();
            $table->boolean('read_only')->default(false);
            $table->unsignedInteger('refresh_minutes')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'uri']);
        });

        Schema::create('calendar_objects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->string('etag', 64);
            $table->longText('ics');
            $table->string('component')->default('VEVENT'); // VEVENT | VTODO
            $table->string('summary')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('rrule')->nullable();
            $table->timestamps();
            $table->unique(['calendar_id', 'uri']);
            $table->index(['calendar_id', 'starts_at']);
        });

        Schema::create('calendar_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->unsignedTinyInteger('operation'); // 1=added, 2=modified, 3=deleted
            $table->unsignedBigInteger('synctoken');
            $table->index(['calendar_id', 'synctoken']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_changes');
        Schema::dropIfExists('calendar_objects');
        Schema::dropIfExists('calendars');
    }
};
