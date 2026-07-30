<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plaintext-relational pivot (Finance): projects to bundle receipts + manual
 * expenses. Nested via a self-referential parent_id. `expenses` is a plaintext
 * JSON array ([{id,amount,date,note,account,category}]); manual hand-entered
 * spend without a bank booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('finance_projects')->nullOnDelete();
            $table->string('name', 300);
            $table->string('kind', 16)->default('business'); // business|private
            $table->text('note')->nullable();
            $table->longText('expenses')->nullable(); // plaintext JSON array
            $table->unsignedInteger('version')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_projects');
    }
};
