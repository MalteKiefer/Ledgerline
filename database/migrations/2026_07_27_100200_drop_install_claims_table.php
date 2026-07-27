<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The install_claims table backed the OIDC "first user wins" single-tenant claim.
 * With first-party user management (admin-managed accounts) it is obsolete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('install_claims');
    }

    public function down(): void
    {
        Schema::create('install_claims', function (Blueprint $table): void {
            $table->id();
            $table->string('oidc_sub')->unique();
            $table->timestamps();
        });
    }
};
