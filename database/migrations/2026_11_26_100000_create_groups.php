<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User groups: reusable limit templates (files/gallery quota + device cap) plus an
 * optional "shareable" flag that lets members offer the group as a share target.
 * Limits are metadata only — zero-knowledge is unaffected. A user may belong to
 * many groups; the most generous group limit applies (after any per-user override).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            // Nullable = the group sets no cap for this dimension (does not apply).
            $table->unsignedInteger('files_quota_mb')->nullable();
            $table->unsignedInteger('gallery_quota_mb')->nullable();
            $table->unsignedSmallInteger('max_connected_devices')->nullable();
            // Visible as a share target to its members (invite expands per-member).
            $table->boolean('shareable')->default(false);
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
