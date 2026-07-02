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
 * gzipped copy of the database file). The dump contains the encrypted vault
 * manifests as-is — it is ciphertext, not decrypted content.
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
        $dumper->useCompressor(new GzipCompressor)->dumpToFile($path);

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
        $out = gzopen($path, 'wb9');
        try {
            while (! feof($in)) {
                gzwrite($out, fread($in, 262144));
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
