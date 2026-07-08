<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge encryption for bookmarks. The browser seals {url, title,
 * description, tags} into enc_bookmark with the per-user vault key; the plaintext
 * url/title/description/tags stay null and is_encrypted is true. Folder, favorite,
 * read_later and read_at stay plaintext (ordering/filter flags, not the link
 * itself). No server favicon fetch / metadata fetch / dead-link check / search —
 * those need the plaintext URL the server never has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->longText('enc_bookmark')->nullable()->after('url');
            $table->boolean('is_encrypted')->default(false)->after('enc_bookmark');
            $table->string('title')->nullable()->change();
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->dropColumn(['enc_bookmark', 'is_encrypted']);
        });
    }
};
