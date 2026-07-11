<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Services\Backup\BackupArtifact;
use RuntimeException;
use Spatie\DbDumper\Compressors\GzipCompressor;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;

/**
 * Dumps the application database to a gzipped SQL file (or, for SQLite, a
 * gzipped copy of the database file). The dump contains ALL data: the sealed
 * zero-knowledge manifest rows are ciphertext, but the non-ZK rows
 * (blob-ownership ledgers, user + workspace settings) and — critically — the
 * wrapped-vault-key material are present in plaintext (the latter is an offline
 * passphrase-cracking oracle). The dump artifact is therefore NOT ciphertext and
 * MUST be encrypted before it leaves the host —
 * enforcement lives in BackupManager::run() and Settings\BackupController; do
 * not remove those gates.
 */
final class DatabaseSource implements BackupSource
{
    public function build(string $workDir): BackupArtifact
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $driver = $config['driver'] ?? '';

        return match ($driver) {
            'sqlite' => $this->dumpSqlite($config, $workDir),
            'mysql', 'mariadb' => $this->dumpWithDumper($this->mysql($config), $workDir),
            'pgsql' => $this->dumpWithDumper($this->postgres($config), $workDir),
            default => throw new RuntimeException("Unsupported database driver for backup: {$driver}"),
        };
    }

    private function dumpWithDumper(MySql|PostgreSql $dumper, string $workDir): BackupArtifact
    {
        $path = $workDir.'/database.sql.gz';
        try {
            $dumper->useCompressor(new GzipCompressor)->dumpToFile($path);
        } catch (\Throwable $e) {
            // The dumper shells out to pg_dump/mysqldump (+ gzip). Turn the raw
            // "command not found" into an actionable message.
            throw new RuntimeException(
                'Database dump failed. Ensure the client tools (pg_dump/mysqldump and gzip) are installed on the server. Detail: '.$e->getMessage(),
                previous: $e,
            );
        }

        return new BackupArtifact($path, 'sql.gz');
    }

    private function dumpSqlite(array $config, string $workDir): BackupArtifact
    {
        $db = $config['database'] ?? '';
        if (! is_string($db) || ! is_file($db)) {
            throw new RuntimeException('SQLite database file not found for backup.');
        }
        $path = $workDir.'/database.sqlite.gz';
        $in = fopen($db, 'rb');
        if ($in === false) {
            throw new RuntimeException('Could not open the SQLite database for backup.');
        }
        $out = gzopen($path, 'wb9');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException('Could not open the backup archive for writing.');
        }
        try {
            while (! feof($in)) {
                $chunk = fread($in, 262144);
                if ($chunk === false) {
                    throw new RuntimeException('Error reading the SQLite database during backup.');
                }
                if ($chunk !== '' && gzwrite($out, $chunk) === 0) {
                    throw new RuntimeException('Error writing the SQLite backup (disk full?).');
                }
            }
        } finally {
            fclose($in);
            gzclose($out);
        }

        return new BackupArtifact($path, 'sqlite.gz');
    }

    private function mysql(array $c): MySql
    {
        return MySql::create()
            ->setHost($c['host'] ?? '127.0.0.1')
            ->setPort((int) ($c['port'] ?? 3306))
            ->setDbName($c['database'])
            ->setUserName((string) ($c['username'] ?? ''))
            ->setPassword((string) ($c['password'] ?? ''));
    }

    private function postgres(array $c): PostgreSql
    {
        return PostgreSql::create()
            ->setHost($c['host'] ?? '127.0.0.1')
            ->setPort((int) ($c['port'] ?? 5432))
            ->setDbName($c['database'])
            ->setUserName((string) ($c['username'] ?? ''))
            ->setPassword((string) ($c['password'] ?? ''));
    }
}
