<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user isolation, phase 4: mail (accounts, folders, archived messages).
 * user_id is denormalised onto folders/messages (not just accounts) so the
 * global scope applies uniformly; existing rows backfill to the first user.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['mail_accounts', 'mail_folders', 'mail_messages'];

    public function up(): void
    {
        $firstUserId = User::query()->orderBy('id')->value('id');

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->foreignId('user_id')->nullable()->index();
            });
            if ($firstUserId !== null) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        }
    }
};
