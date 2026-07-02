<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalise the encrypted vault manifest into its own table.
 *
 * The files manifest used to live in columns on the vault row; notes (and any
 * future zero-knowledge modules) each need their own manifest, so manifests
 * become named rows. The existing files manifest is carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_manifests', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->longText('cipher')->nullable();
            $table->string('nonce')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        // Carry the existing files manifest over.
        $vault = DB::table('vault')->first();
        if ($vault !== null && ($vault->manifest_cipher ?? null) !== null) {
            DB::table('vault_manifests')->insert([
                'name' => 'files',
                'cipher' => $vault->manifest_cipher,
                'nonce' => $vault->manifest_nonce,
                'version' => $vault->manifest_version ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('vault', function (Blueprint $table): void {
            $table->dropColumn(['manifest_cipher', 'manifest_nonce', 'manifest_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_manifests');
    }
};
