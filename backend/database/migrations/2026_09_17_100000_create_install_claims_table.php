<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Records the OIDC subject that first claimed this single-tenant install. This
// marker deliberately SURVIVES user deletion: without it, the sole owner
// self-deleting would let the next OIDC subject silently provision itself as
// the new owner (User::first() would be null again). With no allow-list
// configured, only the recorded subject may re-provision after an erasure.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_claims', function (Blueprint $table): void {
            $table->id();
            $table->string('oidc_sub');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('install_claims');
    }
};
