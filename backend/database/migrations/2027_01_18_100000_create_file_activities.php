<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_activities', function (Blueprint $table) {
            $table->id();
            // Owner of the affected data (the feed is owner-scoped).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->unsignedBigInteger('file_folder_id')->nullable();
            $table->string('action', 32);
            // Who performed it (null = anonymous upload-link contributor).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_activities');
    }
};
