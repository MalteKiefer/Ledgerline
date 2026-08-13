<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts + CardDAV storage. Address books are CardDAV collections; contacts
 * store the raw vCard 4.0 as the source of truth plus denormalised columns for
 * the UI/search. dav_credentials holds the Basic-auth login for external DAV
 * clients; dav_changes is the sync-collection log sabre needs for incremental
 * sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('address_books', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->string('name');
            $table->string('uri');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('synctoken')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'uri']);
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('address_book_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->string('etag', 64);
            $table->longText('vcard');
            // Denormalised for list/search only; the vCard is authoritative.
            $table->string('fn')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('org')->nullable();
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
            $table->boolean('has_photo')->default(false);
            $table->timestamps();
            $table->unique(['address_book_id', 'uri']);
        });

        Schema::create('dav_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('address_book_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->unsignedTinyInteger('operation'); // 1=added, 2=modified, 3=deleted
            $table->unsignedBigInteger('synctoken');
            $table->index(['address_book_id', 'synctoken']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_changes');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('address_books');
        Schema::dropIfExists('dav_credentials');
    }
};
