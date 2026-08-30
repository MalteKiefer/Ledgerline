<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_project_operations', function (Blueprint $table): void {
            $table->unique(
                ['user_id', 'id'],
                'finance_project_operations_owner_id_unique',
            );
        });

        Schema::table('finance_project_document_links', function (Blueprint $table): void {
            $table->unsignedBigInteger('attached_operation_id')->nullable()->after('metadata_snapshot');
            $table->unsignedBigInteger('detached_operation_id')->nullable()->after('detached_at');

            $table->unique(
                ['user_id', 'attached_operation_id'],
                'finance_project_links_owner_attached_operation_unique',
            );
            $table->unique(
                ['user_id', 'detached_operation_id'],
                'finance_project_links_owner_detached_operation_unique',
            );
            $table->foreign(
                ['user_id', 'attached_operation_id'],
                'finance_project_links_owner_attached_operation_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_operations')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
            $table->foreign(
                ['user_id', 'detached_operation_id'],
                'finance_project_links_owner_detached_operation_foreign',
            )
                ->references(['user_id', 'id'])
                ->on('finance_project_operations')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
        });
    }

    public function down(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        Schema::table('finance_project_document_links', function (Blueprint $table) use ($sqlite): void {
            if ($sqlite) {
                $table->dropForeign(['user_id', 'attached_operation_id']);
                $table->dropForeign(['user_id', 'detached_operation_id']);
            } else {
                $table->dropForeign('finance_project_links_owner_attached_operation_foreign');
                $table->dropForeign('finance_project_links_owner_detached_operation_foreign');
            }
            $table->dropUnique('finance_project_links_owner_attached_operation_unique');
            $table->dropUnique('finance_project_links_owner_detached_operation_unique');
            $table->dropColumn(['attached_operation_id', 'detached_operation_id']);
        });

        Schema::table('finance_project_operations', function (Blueprint $table): void {
            $table->dropUnique('finance_project_operations_owner_id_unique');
        });
    }
};
