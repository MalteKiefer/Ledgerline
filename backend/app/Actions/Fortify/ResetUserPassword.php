<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // A password reset is the owner's remediation for a compromised account,
        // so it must act as a kill switch: session auth (keyed on the session id)
        // and Sanctum device tokens (keyed on the token hash) both survive a mere
        // password change otherwise. The reset flow is unauthenticated (the user
        // arrives via an emailed token), so there is no "current" session to keep
        // — evict them all.
        self::revokeAllAccess($user);
    }

    /**
     * Sign the user out everywhere: delete their persisted web sessions and
     * revoke every Sanctum personal access (device) token.
     */
    public static function revokeAllAccess(User $user): void
    {
        // Revoke device / API bearer tokens.
        $user->tokens()->delete();

        // Purge persisted web sessions (only meaningful for the database driver;
        // guarded so it is a no-op on file/cookie/array drivers or a fresh box).
        $cfgTable = config('session.table', 'sessions');
        $table = is_string($cfgTable) ? $cfgTable : 'sessions';
        if (config('session.driver') === 'database' && Schema::hasTable($table)) {
            DB::table($table)
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
