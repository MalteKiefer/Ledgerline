<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the teams table.
 *
 * A team is the unit of data ownership and isolation. Teams are derived from
 * Pocket-ID groups (key "group:<id>"); users without a group get a personal
 * team (key "user:<id>"). All owned records carry a team_id and are only
 * visible to members of that team.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            // Stable identity: "group:<pocketid group id>" or "user:<user id>".
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
