<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Restore the application database from a backup dump produced by DatabaseSource
 * (a gzipped SQL dump for pg/mysql, or a gzipped copy of the sqlite file). This
 * is DESTRUCTIVE — it overwrites the current database — so it is a deliberate
 * artisan command, never a one-click UI action. Decrypt an encrypted archive
 * first (via the Backup UI "decrypt" or `backups:decrypt`), then point this at
 * the resulting plaintext `database.sql.gz` / `database.sqlite.gz`.
 */
final class RestoreDatabase extends Command
{
    protected $signature = 'backup:restore-db {file : Path to the (decrypted) database.*.gz dump} {--force : Skip the confirmation prompt}';

    protected $description = 'Restore the database from a backup dump (DESTRUCTIVE — overwrites current data)';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("Dump file not found: {$file}");

            return self::FAILURE;
        }

        $connection = config('database.default');
        $connection = is_string($connection) ? $connection : '';
        $raw = config("database.connections.{$connection}");
        if (! is_array($raw)) {
            $this->error("No database connection configured: {$connection}");

            return self::FAILURE;
        }
        $config = [];
        foreach ($raw as $k => $v) {
            $config[(string) $k] = $v;
        }
        $driver = is_string($config['driver'] ?? null) ? $config['driver'] : '';

        if (! $this->option('force') && ! $this->confirm("This OVERWRITES the '{$connection}' database. Continue?", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        return match ($driver) {
            'sqlite' => $this->restoreSqlite($config, $file),
            'pgsql' => $this->restorePsql($config, $file),
            'mysql', 'mariadb' => $this->restoreMysql($config, $file),
            default => tap(self::FAILURE, fn () => $this->error("Unsupported driver: {$driver}")),
        };
    }

    /** @param array<string, mixed> $c */
    private function restoreSqlite(array $c, string $file): int
    {
        $db = is_string($c['database'] ?? null) ? $c['database'] : '';
        if ($db === '') {
            $this->error('SQLite database path not configured.');

            return self::FAILURE;
        }
        $in = gzopen($file, 'rb');
        $out = fopen($db, 'wb');
        if ($in === false || $out === false) {
            $this->error('Could not open files for restore.');

            return self::FAILURE;
        }
        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 262144));
        }
        gzclose($in);
        fclose($out);
        $this->info("Restored SQLite database to {$db}.");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $c */
    private function restorePsql(array $c, string $file): int
    {
        $env = ['PGPASSWORD' => $this->s($c, 'password')];
        $argv = ['psql', '-h', $this->s($c, 'host', '127.0.0.1'), '-p', $this->s($c, 'port', '5432'),
            '-U', $this->s($c, 'username'), '-d', $this->s($c, 'database'), '-v', 'ON_ERROR_STOP=1'];

        return $this->pipeGzInto($argv, $env, $file);
    }

    /** @param array<string, mixed> $c */
    private function restoreMysql(array $c, string $file): int
    {
        $env = ['MYSQL_PWD' => $this->s($c, 'password')];
        $argv = ['mysql', '-h', $this->s($c, 'host', '127.0.0.1'), '-P', $this->s($c, 'port', '3306'),
            '-u', $this->s($c, 'username'), $this->s($c, 'database')];

        return $this->pipeGzInto($argv, $env, $file);
    }

    /**
     * Decompress the gz dump and stream the SQL into the client's stdin.
     *
     * @param  list<string>  $argv
     * @param  array<string, string>  $env
     */
    private function pipeGzInto(array $argv, array $env, string $file): int
    {
        $gz = gzopen($file, 'rb');
        if ($gz === false) {
            $this->error('Could not open the dump.');

            return self::FAILURE;
        }
        // Symfony merges the given env with the inherited parent environment.
        $process = new Process($argv, null, $env, null, 3600);
        $process->setInput((function () use ($gz) {
            while (! gzeof($gz)) {
                yield (string) gzread($gz, 262144);
            }
            gzclose($gz);
        })());
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->error('Restore failed (see output above).');

            return self::FAILURE;
        }
        $this->info('Database restored.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $c */
    private function s(array $c, string $key, string $default = ''): string
    {
        $v = $c[$key] ?? null;

        return is_scalar($v) ? (string) $v : $default;
    }
}
