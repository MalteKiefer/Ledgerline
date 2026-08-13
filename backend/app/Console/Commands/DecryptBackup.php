<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\ArchiveCipher;
use Illuminate\Console\Command;

/**
 * Decrypt a downloaded, encrypted backup archive (*.enc) with its passphrase.
 * The passphrase is prompted (not passed as an argument) so it stays out of the
 * shell history and process list.
 */
class DecryptBackup extends Command
{
    protected $signature = 'backups:decrypt {input : Path to the .enc archive} {output : Where to write the decrypted archive}';

    protected $description = 'Decrypt an encrypted backup archive with its passphrase';

    public function handle(ArchiveCipher $cipher): int
    {
        $input = $this->argument('input');
        $output = $this->argument('output');

        if (! is_file($input)) {
            $this->error("Input file not found: {$input}");

            return self::FAILURE;
        }

        $passphrase = $this->secret('Backup passphrase');
        if (! is_string($passphrase) || $passphrase === '') {
            $this->error('A passphrase is required.');

            return self::FAILURE;
        }

        try {
            $cipher->decryptFile($input, $output, $passphrase);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Decrypted to {$output}");

        return self::SUCCESS;
    }
}
