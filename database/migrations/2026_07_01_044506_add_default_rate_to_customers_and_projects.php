<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a default hourly rate (in cents) to customers and projects. Time entries
 * resolve their rate from the entry, then the project, then the customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('default_rate_cents')->nullable();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('default_rate_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('default_rate_cents');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('default_rate_cents');
        });
    }
};
