<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance categories gain an optional colour + monochrome icon (for the
 * "Kategorien" management table). Additive + nullable only — no data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_categories', function (Blueprint $table): void {
            $table->string('color', 16)->nullable()->after('name');
            $table->string('icon', 40)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('finance_categories', function (Blueprint $table): void {
            $table->dropColumn(['color', 'icon']);
        });
    }
};
