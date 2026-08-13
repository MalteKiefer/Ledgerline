<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-user sharing: one row grants another user access to a resource
 * (polymorphic shareable) at a permission level. The owner keeps full control;
 * the sharee sees it (read) and may edit it (write). Used by files/folders,
 * notes, calendars, address books and gallery photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_shares', function (Blueprint $table): void {
            $table->id();
            $table->string('shareable_type');
            $table->string('shareable_id'); // string: covers int + uuid keys
            $table->foreignId('owner_id');
            $table->foreignId('shared_with_user_id');
            $table->string('permission', 8)->default('read'); // read | write
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['shared_with_user_id', 'shareable_type']);
            $table->unique(['shareable_type', 'shareable_id', 'shared_with_user_id'], 'resource_shares_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_shares');
    }
};
