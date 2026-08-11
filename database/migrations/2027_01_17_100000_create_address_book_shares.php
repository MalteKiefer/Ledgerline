<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal cross-user address-book sharing (viewer-only): the owner grants a
 * registered user read access to one of their address books. Plus a per-user
 * secret token for a subscribeable birthday .ics feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_book_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('address_book_id')->constrained('address_books')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['recipient_id']);
            $table->unique(['owner_id', 'recipient_id', 'address_book_id'], 'abshare_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('birthday_feed_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('birthday_feed_token');
        });
        Schema::dropIfExists('address_book_shares');
    }
};
