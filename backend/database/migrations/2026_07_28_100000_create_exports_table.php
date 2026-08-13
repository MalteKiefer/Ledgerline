<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asynchronous download exports. A worker builds a (possibly multi-part) zip on
 * the files disk; the user picks it up from the Downloads page within its
 * retention window. Kept as its own table so both gallery and files exports
 * share one queue, page and retention policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('source');                 // 'gallery' | 'files'
            $table->string('variant')->nullable();    // 'original' | 'edited' (gallery)
            $table->string('title');                  // human label for the row
            $table->string('status')->default('queued'); // queued|processing|ready|failed
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('part_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);
            $table->json('files')->nullable();        // [{name, path, size}] zip parts
            $table->json('payload')->nullable();      // the selection to build from
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
