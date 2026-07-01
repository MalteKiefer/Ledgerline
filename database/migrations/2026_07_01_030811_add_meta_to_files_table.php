<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add editable metadata to files: an optional display title, a description and
 * a free note. These are searchable alongside the file name and content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('name');
            $table->text('description')->nullable()->after('title');
            $table->text('note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['title', 'description', 'note']);
        });
    }
};
