<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the files table.
 *
 * A file is attached polymorphically to a customer or a project and owned by a
 * team (denormalised team_id for isolation and search). Bytes live on the
 * object-storage disk at disk_path; only metadata lives here. extracted_text
 * holds searchable text for unencrypted, text-extractable files.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('disk_path');
            $table->string('mime_type');
            $table->string('type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
