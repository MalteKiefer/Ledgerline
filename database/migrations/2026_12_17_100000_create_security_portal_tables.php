<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security portal: a verbose per-request access log, an IP block-list, and a
 * per-user block flag. All admin-facing, metadata-only (no request bodies).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every HTTP request (web + api), recorded after the response so the real
        // status is known. Metadata only — never bodies. Pruned on a retention
        // window (ops.request_log_retention_days).
        Schema::create('request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('method', 10);
            $table->string('path', 2048);
            $table->unsignedSmallInteger('status');
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status']);
        });

        // Blocked source IPs (single address or CIDR). A request from a matching
        // IP is refused early with 403.
        Schema::create('blocked_ips', function (Blueprint $table): void {
            $table->id();
            $table->string('cidr', 64)->unique(); // "1.2.3.4" or "1.2.3.0/24" or an IPv6 form
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Per-user block: a blocked user cannot authenticate; existing tokens are
        // revoked at block time. Non-fillable (set only via the admin block action).
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
        Schema::dropIfExists('blocked_ips');
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'blocked_at')) {
                $table->dropColumn('blocked_at');
            }
        });
    }
};
