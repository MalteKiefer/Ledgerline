<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The Contacts module was removed entirely. Drop its zero-knowledge
 * avatar-blob ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contact_blobs');
    }

    public function down(): void
    {
        // One-way teardown.
    }
};
