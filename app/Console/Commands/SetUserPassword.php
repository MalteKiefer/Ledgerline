<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Set (or reset) a user's password from the server console — the mail-independent
 * bootstrap path for first-party auth. Essential right after the OIDC→first-party
 * migration (the existing user has no password yet) and whenever SMTP is not
 * configured so password-reset-by-email is unavailable.
 *
 *   php artisan user:set-password owner@example.com --admin
 */
class SetUserPassword extends Command
{
    protected $signature = 'user:set-password {email : The user\'s email address} {--admin : Grant the admin role} {--create : Create the user if none exists} {--name= : Display name when creating (defaults to the email local-part)}';

    protected $description = "Set or reset a user's login password (create with --create; optionally grant admin)";

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();
        if (! $user) {
            // Bootstrap path for a fresh install / empty database: `user:set-password`
            // alone only updates an existing row, so the very first admin has no way
            // in. `--create` mints the row (role stays non-fillable → set via
            // forceFill below), verifying the email so mail-less setups aren't stuck.
            if (! $this->option('create')) {
                $this->error("No user with email {$email}. Pass --create to create it.");

                return self::FAILURE;
            }
            $name = (string) ($this->option('name') ?: strstr($email, '@', true) ?: $email);
            $user = new User;
            $user->forceFill(['name' => $name, 'email' => $email]);
        }

        $entered = $this->secret('New password (min 12 chars)');
        $password = is_string($entered) ? $entered : '';
        if (mb_strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }
        $confirm = $this->secret('Confirm password');
        if ($password !== (is_string($confirm) ? $confirm : '')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        if ($this->option('admin')) {
            $user->forceFill(['role' => 'admin']);
        }
        $user->save();

        $this->info("Password set for {$email}".($this->option('admin') ? ' (admin).' : '.'));

        return self::SUCCESS;
    }
}
