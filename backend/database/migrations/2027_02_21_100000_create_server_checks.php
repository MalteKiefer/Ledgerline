<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reachability history, kept apart from the SSH snapshot on purpose.
 *
 * The probe is expensive and runs every quarter hour; a reachability check is a
 * connect and a packet, so it can run every few minutes and actually notice an
 * outage. Storing each result rather than only the latest is what makes latency
 * and uptime answerable at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            // 'icmp' or 'tcp'. Kept as a string rather than an enum so adding a
            // kind later is not a migration against a table with millions of rows.
            $table->string('kind', 16);
            // Null for ICMP, which has no port.
            $table->unsignedInteger('port')->nullable();
            $table->boolean('ok');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The two questions asked of this table: "what happened to this
            // server lately" and "what is the history of this one port".
            $table->index(['server_id', 'created_at']);
            $table->index(['server_id', 'kind', 'port', 'created_at']);
        });

        Schema::table('servers', function (Blueprint $table): void {
            // A short list of {port, label}. A column rather than a table: the
            // list is small, always read with the server, and never queried by
            // itself.
            $table->json('monitor_ports')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn('monitor_ports');
        });
        Schema::dropIfExists('server_checks');
    }
};
