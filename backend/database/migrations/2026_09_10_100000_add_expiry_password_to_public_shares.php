<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A leaked public link exposed data forever. Add an optional expiry and an
// optional (hashed) password so owners can time-box and gate their links.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_shares', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('token');
            $table->string('password')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('public_shares', function (Blueprint $table): void {
            $table->dropColumn(['expires_at', 'password']);
        });
    }
};
