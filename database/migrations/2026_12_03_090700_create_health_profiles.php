<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Health). One profile row per user — the opaque
 * sealed health store is being retired. Health values are GDPR Art. 9 special
 * category data, so the sensitive columns (birthdate, weight goal) carry a
 * Laravel `encrypted` cast (APP_KEY, not in the DB dump) rather than opaque ZK
 * ciphertext. Metadata (height/sex) stays plaintext for the client to read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('birthdate')->nullable();      // encrypted cast
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('sex', 16)->nullable();
            $table->text('weight_goal_kg')->nullable();  // encrypted cast
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique('user_id'); // one profile per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_profiles');
    }
};
