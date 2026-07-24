<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only forensic trail of every MUTATION to a content/shard blob (create,
 * delete, reconcile-delete, orphan-sweep) and every sealed sharded-store ROOT
 * write/reject. Purpose: when data loss occurs again, we can reconstruct exactly
 * who touched which blob, when, from where, why, and with what result — including
 * a sha256 of the ciphertext (create) and of the sealed root + its shard set
 * (root write). Metadata only: blob refs are non-secret UUIDs, sizes are bucketed,
 * hashes are over CIPHERTEXT — never plaintext, keys or the sealed content itself.
 * Reads are deliberately NOT recorded here (volume; the device access trail covers
 * access at a throttled level).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blob_audit_log', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Actor (null for console/sweep). No FK: the trail must survive user
            // deletion for post-mortem.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('module', 32);              // gallery | files | shared-folders
            $table->string('action', 32);              // create|delete|reconcile_delete|sweep_delete|root_write|root_reject
            $table->uuid('blob')->nullable();          // the blob touched (null for root_write/reject)
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();    // hex sha256 of the ciphertext (create) or sealed root (root_write)
            $table->string('source', 16)->nullable();  // web | api | command
            $table->string('reason', 48)->nullable();  // upload | client_delete | reconcile | orphan_sweep | missing_shard | …
            $table->string('result', 16)->default('ok'); // ok | fail | rejected
            $table->json('meta')->nullable();          // version, shard_count, shard_set_sha256, missing[], ip, …
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('blob');
            $table->index(['module', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blob_audit_log');
    }
};
