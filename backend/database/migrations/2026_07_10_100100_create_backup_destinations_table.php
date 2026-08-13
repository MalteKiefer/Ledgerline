<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_destinations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('driver', 16); // s3 | b2 | sftp | webdav
            // Driver config (bucket/region/key/secret/endpoint or host/port/user/
            // pass/path). Encrypted at rest via the model's cast.
            $table->text('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_destinations');
    }
};
