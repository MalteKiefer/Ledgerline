<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailMessage;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\BrandIcon;
use Illuminate\Console\Command;

/**
 * Says why a sender has no picture.
 *
 * "Nothing shows" has several possible causes that look identical from the
 * outside: the setting is on the rung that looks nothing up, the address book
 * has no match, the domain publishes nothing, or this host cannot reach out at
 * all. Guessing between them wastes more time than printing them.
 *
 * Read-only, and it reports each rung separately rather than only the outcome.
 */
class ProbeMailAvatar extends Command
{
    protected $signature = 'mail:probe-avatar {email : The sender address} {--user= : Whose settings and address book to use}';

    protected $description = 'Report, rung by rung, where a sender picture would come from — or why there is none';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $given = $this->option('user');
        $first = User::query()->orderBy('id')->value('id');
        $userId = is_scalar($given) && $given !== ''
            ? (int) $given
            : (is_numeric($first) ? (int) $first : 0);

        if ($userId === 0) {
            $this->error('No user to check against.');

            return self::FAILURE;
        }

        $mode = UserSetting::for($userId)->mail_avatars ?? 'contacts';
        $this->line("user: {$userId}");
        $this->line("setting: {$mode}".($mode === 'contacts' ? '  (address book only — nothing is looked up outside)' : ''));

        if ($mode === 'off') {
            $this->warn('Sender pictures are switched off for this account.');

            return self::SUCCESS;
        }

        // What the archive knows about this sender, which is where the display
        // name for an alias service comes from.
        $name = MailMessage::query()
            ->where('user_id', $userId)
            ->whereRaw('lower(from_email) = ?', [$email])
            ->whereNotNull('from_name')
            ->orderByDesc('created_at')
            ->value('from_name');
        $this->line('display name: '.(is_string($name) && $name !== '' ? $name : '(none stored)'));

        if ($mode !== 'domain') {
            $this->warn('Only the address book is consulted; switch the setting to include the company logo.');

            return self::SUCCESS;
        }

        $envelope = substr($email, strrpos($email, '@') + 1);
        $domains = [];
        if (is_string($name) && preg_match('/[\w.+-]+@([\w-]+(?:\.[\w-]+)+)/', $name, $m) === 1 && strtolower($m[1]) !== $envelope) {
            $domains[] = strtolower($m[1]).'  (from the display name — an alias service puts the real sender there)';
            $domains[] = $envelope.'  (the address itself — the forwarder)';
        } else {
            $domains[] = $envelope;
        }
        foreach ($domains as $d) {
            $this->line('domain to try: '.$d);
        }

        // Each candidate URL, and whether bytes actually came back. This is the
        // rung that fails silently when the host has no way out.
        foreach ($domains as $labelled) {
            $domain = strtok($labelled, ' ') ?: '';
            $this->newLine();
            $this->info("— {$domain}");
            foreach (BrandIcon::candidates($domain) as $url) {
                $icon = BrandIcon::tryFetch($url);
                $this->line(($icon === null ? '  no  ' : '  YES ').$url.($icon !== null ? ' ('.strlen($icon).' bytes as a data URI)' : ''));
                if ($icon !== null) {
                    $this->newLine();
                    $this->info('This is the picture that would be shown.');

                    return self::SUCCESS;
                }
            }
        }

        $this->newLine();
        $this->warn('No candidate returned an image. If every line says "no", this host most likely cannot reach out.');

        return self::SUCCESS;
    }
}
