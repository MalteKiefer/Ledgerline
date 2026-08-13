<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookmark_folders', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('bookmark_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookmark_folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
