<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a type, priority and estimated effort to projects.
 *
 * Type and priority are stored as the backing values of the ProjectType and
 * ProjectPriority enums. Existing rows default to OTHER / NORMAL.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('type')->default('OTHER')->after('status');
            $table->string('priority')->default('NORMAL')->after('type');
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('budget');

            $table->index(['customer_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'type']);
            $table->dropColumn(['type', 'priority', 'estimated_hours']);
        });
    }
};
