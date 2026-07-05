<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public, tokenised read-only share links for calendars and address books, so
 * they can be shared with people who have no account (unlike ResourceShare,
 * which grants access to an existing user). Anyone with the link can view/
 * subscribe (ICS) / export (vCard) — no login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_shares', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('shareable_type');
            $table->string('shareable_id'); // string: calendars/address books use uuid or int ids
            $table->timestamps();
            $table->unique(['shareable_type', 'shareable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_shares');
    }
};
