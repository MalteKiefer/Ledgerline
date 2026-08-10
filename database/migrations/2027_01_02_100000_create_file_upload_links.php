<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public inbound upload links: an owner mints a token that lets an
 * unauthenticated external person upload files INTO one of the owner's folders
 * (write-only — no listing/download). The token in the URL is the credential;
 * uploads land under the owner and count against the owner's quota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_upload_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_folder_id')->nullable()->constrained('file_folders')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label', 200)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_upload_links');
    }
};
