<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Health). One row per measurement. The metric key
 * and timestamp stay plaintext so the server can sort/filter/group for charts;
 * the actual readings (v/v2) and notes are Art. 9 sensitive → `encrypted` cast.
 * Canonical units (kg / mmHg / bpm / % / °C / mg-dL) stored as strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 32); // weight/bp/pulse/spo2/temp/glucose
            $table->timestamp('ts');
            $table->text('v');            // encrypted cast (measurement)
            $table->text('v2')->nullable(); // encrypted cast (diastolic for bp)
            $table->text('note')->nullable(); // encrypted cast
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'metric', 'ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_entries');
    }
};
