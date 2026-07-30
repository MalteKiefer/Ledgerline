<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Health — intermittent fasting). One row per fast;
 * end_at null = active. The single-active-fast invariant (previously enforced
 * client-side over the opaque store) is now enforced by the DB itself: a partial
 * unique index on user_id WHERE end_at IS NULL. Postgres and sqlite both support
 * partial indexes with identical syntax, so the raw CREATE INDEX is portable;
 * other drivers fall back to no partial index (feature not used there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_fasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable(); // null = active
            $table->unsignedSmallInteger('target_hours');
            $table->text('note')->nullable(); // encrypted cast
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'start_at']);
        });

        // DB-enforced single active fast per user (portable across pgsql + sqlite).
        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX health_fasts_one_active ON health_fasts (user_id) WHERE end_at IS NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS health_fasts_one_active');
        }
        Schema::dropIfExists('health_fasts');
    }
};
