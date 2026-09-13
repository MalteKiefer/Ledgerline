<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Finance-only pivot (round 3): the Servers module (agentless SSH-based
 * monitoring of the owner's own remote servers) is removed on top of the
 * earlier Mail/Notes/Tasks/Calendar/Contacts/Gallery/Files removal. Only
 * Finance and the shared infra (auth, backup, settings, notifications,
 * Paperless, security portal) remain. Drop its relational tables — children
 * before parents to respect foreign keys.
 *
 * This is unrelated to the independent Docker-control agent used by the admin
 * System dashboard (App\Http\Controllers\Api\DockerController): that feature
 * has no database table and is untouched by this migration.
 *
 * This is a destructive, one-way migration — there is no down().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('server_checks'); // FK -> servers
        Schema::dropIfExists('server_facts');  // FK -> servers
        Schema::dropIfExists('servers');       // parent
    }

    public function down(): void
    {
        // Irreversible: the Servers module is gone for good.
    }
};
