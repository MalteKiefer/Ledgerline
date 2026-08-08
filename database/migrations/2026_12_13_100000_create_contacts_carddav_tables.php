<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts + CardDAV storage (Phase 1). Consolidated re-creation of the
 * plaintext-relational contacts module: address books are CardDAV collections;
 * each contact stores the raw vCard 4.0 as the source of truth plus denormalised
 * columns for the UI/search; dav_changes is the RFC 6578 sync-collection change
 * log sabre needs for incremental sync; resource_shares grants another user
 * read/write access to an owned collection.
 *
 * There is NO separate DAV credential table: CardDAV clients authenticate with
 * the single app-specific `users.webdav_password` (the same one that mounts the
 * Files WebDAV tree). Contacts hard-delete (no SoftDeletes) so CardDAV tombstones
 * are logged in dav_changes; identity + change tracking use uid/etag/synctoken,
 * not an optimistic version column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_books', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
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
            $table->string('uid')->nullable();
            $table->longText('vcard');
            // Denormalised for list/search only; the vCard is authoritative.
            $table->string('fn')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('org')->nullable();
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
            $table->boolean('has_photo')->default(false);
            $table->boolean('favorite')->default(false);
            $table->timestamps();
            $table->unique(['address_book_id', 'uri']);
            $table->index(['address_book_id', 'uid']);
        });

        Schema::create('contact_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('contact_group', function (Blueprint $table): void {
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained('contact_groups')->cascadeOnDelete();
            $table->primary(['contact_id', 'group_id']);
        });

        // RFC 6578 sync-collection change log. No timestamps: rows are pruned by
        // synctoken window (dav:prune-changes), and DavChangeLog inserts only the
        // four columns below.
        Schema::create('dav_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('address_book_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->unsignedTinyInteger('operation'); // 1=added, 2=modified, 3=deleted
            $table->unsignedBigInteger('synctoken');
            $table->index(['address_book_id', 'synctoken']);
        });

        Schema::create('contact_duplicate_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // sha1 of the group's sorted contact ids — stable identity for a set.
            $table->string('signature', 40);
            $table->timestamps();
            $table->unique(['user_id', 'signature']);
        });

        // Cross-user sharing: one row grants another user access to a resource
        // (polymorphic shareable) at a permission level.
        Schema::create('resource_shares', function (Blueprint $table): void {
            $table->id();
            $table->string('shareable_type');
            $table->string('shareable_id'); // string: covers int + uuid keys
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 8)->default('read'); // read | write
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['shared_with_user_id', 'shareable_type']);
            $table->unique(['shareable_type', 'shareable_id', 'shared_with_user_id'], 'resource_shares_unique');
        });

        // Per-user contacts list preferences (re-added; the historical columns were
        // dropped with the old contacts module).
        Schema::table('user_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_settings', 'contact_sort')) {
                $table->string('contact_sort', 16)->default('first_name');
            }
            if (! Schema::hasColumn('user_settings', 'contact_display_format')) {
                $table->string('contact_display_format', 16)->default('first_last');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('user_settings', 'contact_display_format')) {
                $table->dropColumn(['contact_sort', 'contact_display_format']);
            }
        });
        Schema::dropIfExists('resource_shares');
        Schema::dropIfExists('contact_duplicate_dismissals');
        Schema::dropIfExists('dav_changes');
        Schema::dropIfExists('contact_group');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('address_books');
    }
};
