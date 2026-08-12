<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_mounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 16); // s3 | sftp
            // Driver credentials (bucket keys / SFTP host+password/key) — operative
            // secret, encrypted under APP_KEY, never serialized to the client.
            $table->text('config');
            $table->boolean('read_only')->default(false);
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_mounts');
    }
};
