<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Searchable columns for the mail archive: recipients (cc), a capped plain-text
 * body and the attachment file names, so the archive can be searched by
 * content, cc and attachment without re-parsing every .eml per query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->json('cc')->nullable()->after('to');
            $table->longText('body_text')->nullable()->after('preview');
            $table->json('attachment_names')->nullable()->after('has_attachments');
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropColumn(['cc', 'body_text', 'attachment_names']);
        });
    }
};
