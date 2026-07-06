<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// WebDAV lock storage (sabre PDO locks backend). macOS Finder mounts WebDAV
// read-write and requires DAV class-2 locking support, so a shared, persistent
// lock table is needed (the file backend would not work across app containers).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locks', function (Blueprint $table) {
            $table->id();
            $table->string('owner', 100)->nullable();
            $table->unsignedInteger('timeout')->nullable();
            $table->unsignedBigInteger('created')->nullable();
            $table->string('token', 100)->nullable();
            $table->unsignedTinyInteger('scope')->nullable();
            $table->unsignedTinyInteger('depth')->nullable();
            $table->string('uri', 1000)->nullable();
            $table->index('token');
            $table->index('uri');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locks');
    }
};
