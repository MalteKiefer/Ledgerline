<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Diagnostic sync/ingest log lines, per mail account. Metadata only —
            // never message content. Cascades away with its account.
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->string('level', 8)->default('info'); // info | warn | error
            $table->string('event', 64);
            $table->string('folder', 255)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['account_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
