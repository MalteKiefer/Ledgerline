<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-knowledge encryption for notes. The browser seals {title, content, tags}
 * into enc_note with the per-user vault key; the plaintext title/content/tags
 * stay null and is_encrypted is true. Markdown is rendered client-side. pinned
 * stays a plaintext boolean (an ordering flag, not content).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->longText('enc_note')->nullable()->after('content');
            $table->boolean('is_encrypted')->default(false)->after('enc_note');
            $table->string('title')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->dropColumn(['enc_note', 'is_encrypted']);
        });
    }
};
