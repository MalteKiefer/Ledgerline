<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 16)->default('compose');
            $table->uuid('source_message_id')->nullable()->index();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('subject', 998)->nullable();
            $table->text('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->foreignId('mail_signature_id')->nullable()->constrained('mail_signatures')->nullOnDelete();
            $table->string('sent_folder', 255)->nullable();
            $table->json('file_ids')->nullable();
            $table->json('gallery_photo_ids')->nullable();
            $table->json('local_attachments')->nullable();
            $table->boolean('read_receipt')->default(false);
            $table->boolean('high_priority')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_drafts');
    }
};
