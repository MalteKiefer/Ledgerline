<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Finance-only pivot: the Health and Explore modules are removed entirely
 * (mirroring the earlier Contacts/Passwords removals). Drop their relational
 * tables. Children are dropped before parents to respect foreign keys.
 *
 * This is a destructive, one-way migration — there is no down().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Explore: couplings + settings reference tracks/photos → drop first.
        Schema::dropIfExists('explore_couplings');
        Schema::dropIfExists('explore_settings');
        Schema::dropIfExists('explore_tracks');

        // Health: entries + fasts reference the owner alongside the profile.
        Schema::dropIfExists('health_entries');
        Schema::dropIfExists('health_fasts');
        Schema::dropIfExists('health_profiles');
    }

    public function down(): void
    {
        // Irreversible: the Health and Explore modules are gone for good.
    }
};
