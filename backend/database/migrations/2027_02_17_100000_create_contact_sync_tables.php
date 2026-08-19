<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External CardDAV replicas. Ledgerline owns the canonical contact rows; these
 * tables only retain connection state, remote identity and an append-only
 * recovery trail. Remote deletes can therefore never erase local history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_sync_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('address_book_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 24)->default('carddav'); // carddav, icloud, google
            $table->string('endpoint', 2048);
            $table->string('auth_type', 16)->default('basic'); // basic, bearer, oauth2
            $table->string('username')->nullable();
            // Operational secrets are encrypted casts in the model and hidden
            // from every API response. OAuth fields support Google's refresh flow.
            $table->text('password')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();
            $table->string('oauth_state_hash', 64)->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('sync_token')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('propagate_deletes')->default(true);
            $table->string('status', 16)->default('idle');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'enabled']);
        });

        Schema::create('contact_sync_remote_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('source_id')->constrained('contact_sync_sources')->cascadeOnDelete();
            // Deliberately no FK: this mapping must survive a local deletion so
            // its remote counterpart can be deleted after the transaction.
            $table->uuid('contact_id');
            $table->string('remote_uri', 2048);
            $table->string('remote_etag', 255)->nullable();
            $table->string('remote_uid')->nullable();
            $table->string('local_etag', 64)->nullable();
            $table->timestamp('remote_deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'remote_uri']);
            $table->index(['source_id', 'contact_id']);
        });

        Schema::create('contact_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // No contact FK: versions remain restorable after deletion.
            $table->uuid('contact_id')->nullable();
            $table->foreignUuid('source_id')->nullable()->constrained('contact_sync_sources')->nullOnDelete();
            $table->string('action', 24); // created, updated, deleted, conflict, restored
            $table->string('remote_uri', 2048)->nullable();
            $table->string('remote_etag', 255)->nullable();
            $table->longText('vcard');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_versions');
        Schema::dropIfExists('contact_sync_remote_cards');
        Schema::dropIfExists('contact_sync_sources');
    }
};
