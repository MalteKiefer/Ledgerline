<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('source', 16);  // database | files | gallery
            $table->foreignId('backup_destination_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cron', 64)->default('0 3 * * *'); // 5-field cron expression
            $table->unsignedSmallInteger('retention')->default(7); // versions to keep
            $table->boolean('encrypt')->default(false);
            $table->text('passphrase')->nullable(); // encrypted at rest via the model cast
            $table->string('notify', 16)->default('none'); // none | ntfy | webhook | mail
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable(); // success | failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
