<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DavCredential;
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
        DB::transaction(function () use ($user): void {
            foreach (config('user_data.contributors', []) as $class) {
                /** @var UserDataContributor $contributor */
                $contributor = app($class);
                $contributor->purge($user);
            }

            // Shared per-user infrastructure not owned by any single module.
            DavCredential::where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            // The zero-knowledge vault (wrapped keys) — delete explicitly rather
            // than relying solely on the FK cascade.
            Vault::where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
