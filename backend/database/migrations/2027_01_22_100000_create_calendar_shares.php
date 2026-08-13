<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('calendar_id')->constrained()->cascadeOnDelete(); // calendars use uuid pks
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16)->default('viewer'); // viewer | editor
            $table->timestamps();

            $table->unique(['calendar_id', 'recipient_id']);
            $table->index(['recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_shares');
    }
};
