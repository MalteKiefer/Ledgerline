<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app error log. Unhandled exceptions are recorded here (deduplicated by a
 * fingerprint) so operators can see failures in the UI without shipping any
 * data to an external error-tracking service — keeping with the app's
 * self-contained, zero-knowledge posture. Messages and traces are redacted of
 * obvious secrets before they land here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_events', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint')->unique();
            $table->string('level')->default('error');
            $table->string('exception');
            $table->text('message');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->json('context')->nullable();
            $table->text('trace')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->index('last_seen_at');
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_events');
    }
};
