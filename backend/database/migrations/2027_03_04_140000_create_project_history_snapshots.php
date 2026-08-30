<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_project_history_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id');
            $table->unsignedBigInteger('project_id');
            $table->timestamp('expires_at', 6);
            $table->timestamp('created_at', 6);

            $table->unique(['user_id', 'uuid'], 'finance_project_history_snapshots_owner_uuid_unique');
            $table->foreign(
                ['user_id', 'project_id'],
                'finance_project_history_snapshots_owner_project_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_records')
                ->cascadeOnDelete();
            $table->index(['expires_at'], 'finance_project_history_snapshots_expiry_index');
        });

        Schema::create('finance_project_history_snapshot_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')
                ->constrained('finance_project_history_snapshots')
                ->cascadeOnDelete();
            $table->string('source_kind', 16);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('occurred_at', 6);

            $table->unique(
                ['snapshot_id', 'source_kind', 'source_id'],
                'finance_project_history_snapshot_items_source_unique',
            );
            $table->index(
                ['snapshot_id', 'occurred_at', 'source_kind', 'source_id'],
                'finance_project_history_snapshot_items_page_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_project_history_snapshot_items');
        Schema::dropIfExists('finance_project_history_snapshots');
    }
};
