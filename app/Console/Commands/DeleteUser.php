<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PurgeUserAccount;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Permanently delete a user and ALL their data from the server console — the
 * mail-independent counterpart to `user:set-password --create`. Runs the same
 * GDPR erasure as the in-app account deletion (PurgeUserAccount): every module's
 * sealed store rows + ciphertext blob bytes on disk/S3 + the vault (wrapped keys)
 * + sessions + device tokens, then the account itself.
 *
 * IRREVERSIBLE. Requires an explicit confirmation (--force to skip the prompt for
 * scripted use).
 *
 *   php artisan user:delete owner@example.com
 *   php artisan user:delete owner@example.com --force
 */
class DeleteUser extends Command
{
    protected $signature = 'user:delete {email : The user\'s email address} {--force : Skip the interactive confirmation}';

    protected $description = 'Permanently delete a user and all of their data (irreversible)';

    public function handle(PurgeUserAccount $purge): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        $this->warn("This permanently deletes user #{$user->id} <{$email}> and ALL of their data — files, gallery, notes, passwords, invoices, contacts, explore, shared folders, the vault keys, and every blob on disk. This CANNOT be undone.");

        if (! $this->option('force') && ! $this->confirm('Delete this user and all their data?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $purge->handle($user);

        $this->info("Deleted user <{$email}> and all associated data.");

        return self::SUCCESS;
    }
}
