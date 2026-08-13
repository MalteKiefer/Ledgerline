<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate-group membership for content-based duplicate detection: photos that
 * the detector clustered as the same/similar share a group id; a dismissed
 * photo is excluded from future grouping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->uuid('duplicate_group_id')->nullable()->index();
            $table->float('dup_score')->nullable();
            $table->timestamp('dup_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropColumn(['duplicate_group_id', 'dup_score', 'dup_dismissed_at']);
        });
    }
};
