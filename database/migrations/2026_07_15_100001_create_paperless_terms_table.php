<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Locally cached copy of Paperless tags, document types and
        // correspondents, refreshed hourly so the transfer modal has them
        // instantly without hitting Paperless on every open.
        Schema::create('paperless_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('kind'); // tag | document_type | correspondent
            $table->unsignedBigInteger('paperless_id');
            $table->string('name');
            $table->string('color')->nullable(); // tags only
            $table->timestamps();
            $table->unique(['kind', 'paperless_id']);
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paperless_terms');
    }
};
