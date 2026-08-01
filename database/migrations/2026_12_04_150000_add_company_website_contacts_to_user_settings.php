<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('company_website', 255)->nullable();
            $table->json('company_contacts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn(['company_website', 'company_contacts']);
        });
    }
};
