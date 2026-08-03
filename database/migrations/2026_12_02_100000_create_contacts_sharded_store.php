<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts graduate to a sharded sealed store (merge-safety spec §3b), like
 * files/gallery/notes/passwords/invoices. Additive: the old single-blob module_stores
 * `contacts` row stays readable until the client re-shards on first save (dual-read
 * migration). The record-shard blobs REUSE the existing `contact_blobs` ledger +
 * `contacts/` disk prefix that already backs contact avatars (content-addressed, so a
 * shard ref never collides with an avatar ref) — exactly how invoices shares one ledger
 * for its record shards and receipt PDFs. Only the sealed root pointer is new here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts_store', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('ciphertext')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts_store');
    }
};
