<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the cross-user and public sharing tables. Every module is now
 * zero-knowledge: the server holds only opaque, client-encrypted blobs, so it
 * cannot grant another user or an anonymous visitor access to a resource it
 * can no longer read. Both the private grants (resource_shares) and the
 * tokenised public links (public_shares) are removed along with their
 * controllers and models. Forward-only: there is no meaningful down() as the
 * feature and its models no longer exist in the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('resource_shares');
        Schema::dropIfExists('public_shares');
    }

    public function down(): void
    {
        // Forward-only: cross-user and public sharing have been removed entirely.
    }
};
