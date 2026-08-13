<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the photos table (gallery). Photos are stored under their own
 * date-structured object-storage prefix, independent of the files module, with
 * a thumbnail and a medium rendition. Sorted by capture date (EXIF or upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('disk_path');
            $table->string('thumb_path');
            $table->string('medium_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamp('taken_at');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('taken_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
