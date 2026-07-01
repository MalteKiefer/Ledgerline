<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add folder support to files and allow general files with no owner.
 *
 * files gain a nullable folder_id, and the polymorphic attachable becomes
 * nullable so a file can be a general/company file not tied to a customer or
 * project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('folders')->nullOnDelete();
            $table->string('attachable_type')->nullable()->change();
            $table->unsignedBigInteger('attachable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('folder_id');
        });
    }
};
