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
    protected $signature = 'user:set-password {email : The user\'s email address} {--admin : Grant the admin role}';

    protected $description = "Set or reset a user's login password (and optionally grant admin)";

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
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
