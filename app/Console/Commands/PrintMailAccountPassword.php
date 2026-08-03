<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailAccount;
use Illuminate\Console\Command;

/**
 * Internal helper invoked ONLY as the `PassCmd` of a generated mbsync config
 * (see App\Services\Mail\MbsyncConfig::passCmd()) — never run interactively.
 * It decrypts the given mail account's IMAP password (MailAccount::password,
 * `encrypted`-cast, APP_KEY-backed) and prints it, bare, to stdout — the
 * mechanism that keeps the plaintext password OUT of the generated config
 * file on disk: mbsync shells this command at auth time and reads the
 * password directly from its stdout pipe instead of a literal config line.
 * The plaintext therefore exists only transiently, in this short-lived
 * process's memory and the pipe mbsync reads it from — never at rest.
 *
 * Deliberately minimal and quiet: no Artisan banners, no logging of the
 * account or its password, exit 1 with empty stdout if the account cannot be
 * found (so a broken sync fails loudly with an auth error rather than
 * silently authenticating with an empty password).
 */
final class PrintMailAccountPassword extends Command
{
    /** @var string */
    protected $signature = 'mail:account-password {account : Mail account ID}';

    /** @var string */
    protected $description = "Print a mail account's decrypted IMAP password to stdout (internal — used as an mbsync PassCmd, not for interactive use)";

    /** @var bool */
    protected $hidden = true;

    public function handle(): int
    {
        $account = MailAccount::query()->find((int) $this->argument('account'));
        if ($account === null) {
            return self::FAILURE;
        }

        // Raw stdout write — no Artisan output decoration, no trailing
        // newline beyond what mbsync itself strips.
        fwrite(STDOUT, (string) $account->password);

        return self::SUCCESS;
    }
}
