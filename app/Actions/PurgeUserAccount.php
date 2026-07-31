<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Vault;
use App\Support\UserData\UserDataContributor;
use Illuminate\Support\Facades\DB;

/**
 * Permanently erases a user's account and every piece of data they own, across
 * all modules (GDPR right to erasure). Each module contributes its own purge via
 * a UserDataContributor; this runs them all in one transaction, then removes the
 * shared per-user records and the account itself.
 */
class PurgeUserAccount
{
    public function handle(User $user): void
    {
        // Contributors delete IRREVERSIBLE disk bytes alongside their ledger rows.
        // They must NOT run inside a database transaction: a rollback (deadlock /
        // lock-timeout / a later cascade error) would restore the ledger rows while
        // the object-store bytes are already gone — permanent 404s, including for an
        // innocent shared-folder recipient. Run each contributor OUTSIDE a transaction
        // instead. Purge is idempotent (re-deleting an already-deleted row/byte is a
        // no-op), so an interrupted purge is safely re-runnable and never leaves a
        // "row present, bytes destroyed" inconsistency across a rollback.
        $contributors = config('user_data.contributors', []);
        foreach (is_array($contributors) ? $contributors : [] as $class) {
            if (! is_string($class)) {
                continue;
            }
            /** @var UserDataContributor $contributor */
            $contributor = app($class);
            $contributor->purge($user);
        }

        // The remaining teardown is pure DB (no disk), so it can be atomic.
        DB::transaction(function () use ($user): void {
            // Shared per-user infrastructure not owned by any single module.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            // The zero-knowledge vault (wrapped keys) — delete explicitly rather
            // than relying solely on the FK cascade.
            Vault::where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
