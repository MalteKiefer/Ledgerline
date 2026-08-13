<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_partners', function (Blueprint $table): void {
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->string('currency', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('finance_partners', function (Blueprint $table): void {
            $table->dropColumn(['hourly_rate', 'currency']);
        });
    }
};
