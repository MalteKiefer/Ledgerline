<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local mail archive, filled by the hourly sync. Metadata lives here;
        // the raw RFC822 message (.eml, with its attachments) is stored on the
        // files disk at mail/{blob}. Messages deleted on the server are kept
        // (deleted_on_server_at set) so nothing is ever lost by accident.
        Schema::create('mail_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->string('delimiter', 8)->default('/');
            $table->string('role')->nullable();
            $table->unsignedBigInteger('uidvalidity')->nullable();
            $table->timestamps();
            $table->unique(['mail_account_id', 'path']);
        });

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_folder_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('uid');
            $table->unsignedBigInteger('uidvalidity')->default(0);
            $table->string('message_id')->nullable()->index();
            $table->text('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->json('to')->nullable();
            $table->timestamp('date_at')->nullable();
            $table->boolean('seen')->default(false);
            $table->boolean('flagged')->default(false);
            $table->boolean('answered')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->unsignedBigInteger('size')->default(0);
            $table->uuid('blob'); // raw .eml on the files disk (mail/{blob})
            $table->text('preview')->nullable();
            $table->timestamp('deleted_on_server_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['mail_folder_id', 'uidvalidity', 'uid']);
            $table->index(['mail_account_id', 'deleted_on_server_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_folders');
    }
};
