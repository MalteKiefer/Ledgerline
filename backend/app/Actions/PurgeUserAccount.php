<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
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
            $contributors = config('user_data.contributors', []);
            foreach (is_array($contributors) ? $contributors : [] as $class) {
                if (! is_string($class)) {
                    continue;
                }
                /** @var UserDataContributor $contributor */
                $contributor = app($class);
                $contributor->purge($user);
            }

            // Shared per-user infrastructure not owned by any single module.
            // Sanctum personal access tokens are a polymorphic relation with no
            // DB-level FK cascade off users.id, so the final $user->delete()
            // would leave them orphaned (a still-valid bearer pointing at a
            // deleted account). Revoke them explicitly. Web sessions likewise
            // have no cascade and are cleared here.
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
