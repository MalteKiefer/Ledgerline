<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Services\Backup\Sources\AvatarSource;
use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\GallerySource;
use App\Services\Backup\Sources\InvoiceBlobSource;
use App\Services\Backup\Sources\MailSource;
use App\Services\Backup\Sources\NotesSource;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use PDO;
use RuntimeException;
use Throwable;

/**
 * A real restore drill: prove the backup can be RESTORED, not merely that its
 * archive is intact.
 *
 * BackupVerifier answers "is the archive readable" (gzip header, decryptable,
 * a plausible table count). That is a necessary but not sufficient claim — an
 * archive can decompress perfectly and still restore to nothing. This drill
 * therefore actually replays the database dump into a THROWAWAY database and
 * reads sanity numbers out of it, and it fetches a random sample of mirrored
 * blobs and compares their sha256 against the live counterpart.
 *
 * SAFETY INVARIANT: this class only ever READS from the backup destination and
 * the live disk/database, and only ever WRITES to throwaway temporary paths that
 * are unlinked on every exit path. It must never touch the live files disk or
 * the live database — a drill that can damage production is worse than no drill.
 */
final class RestoreDrill
{
    /** Blob sources whose mirror can be sampled, mapped to their files-disk prefix. */
    private const BLOB_SOURCES = [
        'files' => FilesSource::class,
        'gallery' => GallerySource::class,
        'invoices' => InvoiceBlobSource::class,
        'mail' => MailSource::class,
        'notes' => NotesSource::class,
        'avatars' => AvatarSource::class,
    ];

    /** Cap on the decompressed bytes scanned when a dump cannot be replayed locally. */
    private const SCAN_CAP = 8 * 1024 * 1024 * 1024;

    public function __construct(
        private readonly BackupDestinationFactory $factory,
        private readonly ArchiveCipher $cipher,
        private readonly BackupManager $manager,
    ) {}

    /**
     * Drill the newest successful run of a job.
     *
     * @param  int  $sampleSize  number of mirrored blobs to sample per blob source
     * @return array{
     *     ok: bool,
     *     job: string,
     *     run_id: int|null,
     *     duration_ms: int,
     *     database: array{checked: bool, ok: bool, message: string, tables: int, rows: int|null},
     *     blobs: array{checked: int, matched: int, mismatched: int, mismatches: list<string>, errors: list<string>},
     *     errors: list<string>,
     * }
     */
    public function run(BackupJob $job, int $sampleSize = 10): array
    {
        $started = microtime(true);
        $name = is_string($job->name) && $job->name !== '' ? $job->name : 'backup';
        $result = [
            'ok' => false,
            'job' => $name,
            'run_id' => null,
            'duration_ms' => 0,
            'database' => ['checked' => false, 'ok' => true, 'message' => 'No database source in this job.', 'tables' => 0, 'rows' => null],
            'blobs' => ['checked' => 0, 'matched' => 0, 'mismatched' => 0, 'mismatches' => [], 'errors' => []],
            'errors' => [],
        ];

        $run = $job->runs()->where('status', 'success')->orderByDesc('finished_at')->first();
        if (! $run instanceof BackupRun || $run->filename === null) {
            $result['errors'][] = 'No successful run with a stored archive to drill.';
            $result['duration_ms'] = $this->elapsed($started);

            return $result;
        }
        $result['run_id'] = (int) $run->id;

        if ($job->destination === null) {
            $result['errors'][] = 'No destination configured for this backup job.';
            $result['duration_ms'] = $this->elapsed($started);

            return $result;
        }

        try {
            $fs = $this->factory->make($job->destination);
        } catch (Throwable $e) {
            $result['errors'][] = 'Could not open the destination: '.$e->getMessage();
            $result['duration_ms'] = $this->elapsed($started);

            return $result;
        }

        $sources = $job->effectiveSources();
        $passphrase = $job->effectivePassphrase();

        if (in_array('database', $sources, true)) {
            $result['database'] = $this->drillDatabase($fs, (string) $run->filename, $passphrase);
        }

        foreach ($sources as $src) {
            if (! array_key_exists($src, self::BLOB_SOURCES)) {
                continue;
            }
            $this->drillBlobs($fs, $job, $src, $sampleSize, $passphrase, $result['blobs']);
        }

        // Anything in $result['errors'] returns early above, so reaching here means
        // the outcome rests on the database replay and the blob sample alone.
        $result['ok'] = $result['database']['ok']
            && $result['blobs']['mismatched'] === 0
            && $result['blobs']['errors'] === [];
        $result['duration_ms'] = $this->elapsed($started);

        return $result;
    }

    /**
     * Replay the run's database dump into a throwaway database and read sanity
     * numbers back out of it. A SQLite dump is a full restore (the gunzipped
     * image IS a database, so it is opened and queried). A pg/mysql dump cannot
     * be replayed without that server, so it is parsed structurally instead —
     * still more than an integrity check, because a truncated dump loses its
     * completion trailer and is reported as not restorable.
     *
     * @return array{checked: bool, ok: bool, message: string, tables: int, rows: int|null}
     */
    private function drillDatabase(Filesystem $fs, string $batch, ?string $passphrase): array
    {
        $archive = $this->manager->archiveIn($fs, $batch, 'database');
        if ($archive === null) {
            return ['checked' => true, 'ok' => false, 'message' => 'The run has no database archive in its batch folder.', 'tables' => 0, 'rows' => null];
        }

        $staged = DiskTempFile::create('lldrill');
        $plain = null;
        try {
            $this->download($fs, $archive, $staged->path());

            if (str_ends_with($archive, '.enc')) {
                if (($passphrase ?? '') === '') {
                    return ['checked' => true, 'ok' => false, 'message' => 'The dump is encrypted but no passphrase is available.', 'tables' => 0, 'rows' => null];
                }
                $plain = DiskTempFile::create('lldrilldec');
                $this->cipher->decryptFile($staged->path(), $plain->path(), (string) $passphrase);
            }

            $path = $plain !== null ? $plain->path() : $staged->path();

            return str_contains($archive, '.sqlite.gz')
                ? $this->replaySqlite($path)
                : $this->parseSqlDump($path);
        } catch (Throwable $e) {
            return ['checked' => true, 'ok' => false, 'message' => 'Restore drill failed: '.$e->getMessage(), 'tables' => 0, 'rows' => null];
        }
        // DiskTempFile destructors ($staged, $plain) unlink on scope exit.
    }

    /**
     * Gunzip the dump into a throwaway SQLite file, open it and count tables plus
     * the rows of a known table. This is an actual restore: if the image is
     * damaged, opening or querying it fails here rather than in an emergency.
     *
     * @return array{checked: bool, ok: bool, message: string, tables: int, rows: int|null}
     */
    private function replaySqlite(string $gzPath): array
    {
        // A throwaway path OUTSIDE any live database location.
        $target = sys_get_temp_dir().'/lldrill-'.Str::uuid()->toString().'.sqlite';
        try {
            $this->gunzip($gzPath, $target);

            $pdo = new PDO('sqlite:'.$target, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // Fail loudly on a structurally broken image instead of reporting a
            // confident zero.
            $pdo->query('PRAGMA quick_check');

            $tables = 0;
            $stmt = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            if ($stmt !== false) {
                $value = $stmt->fetchColumn();
                $tables = is_numeric($value) ? (int) $value : 0;
            }

            $rows = $this->countKnownTable($pdo);
            unset($pdo);

            if ($tables === 0) {
                return ['checked' => true, 'ok' => false, 'message' => 'The restored database has no tables.', 'tables' => 0, 'rows' => $rows];
            }

            return [
                'checked' => true,
                'ok' => true,
                'message' => sprintf('Replayed into a throwaway database: %d table(s), %s.', $tables, $rows === null ? 'no reference table found' : $rows.' migration row(s)'),
                'tables' => $tables,
                'rows' => $rows,
            ];
        } finally {
            // Throwaway artefacts, including any journal siblings SQLite may create.
            @unlink($target);
            @unlink($target.'-wal');
            @unlink($target.'-shm');
        }
    }

    /**
     * Rows of a table every install has, as a "the data actually came across"
     * signal. Null when the table is absent (so the caller reports it plainly
     * instead of pretending zero).
     */
    private function countKnownTable(PDO $pdo): ?int
    {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM migrations');
        } catch (Throwable) {
            return null;
        }
        if ($stmt === false) {
            return null;
        }
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Structural check of a pg/mysql dump: it must fully decompress, define
     * tables, and carry its dumper's completion trailer (a dump cut short by a
     * dying process has none — exactly the failure an integrity check misses).
     *
     * @return array{checked: bool, ok: bool, message: string, tables: int, rows: int|null}
     */
    private function parseSqlDump(string $path): array
    {
        $gz = gzopen($path, 'rb');
        if ($gz === false) {
            return ['checked' => true, 'ok' => false, 'message' => 'Could not open the dump.', 'tables' => 0, 'rows' => null];
        }

        $tables = 0;
        $inserts = 0;
        $bytes = 0;
        $complete = false;
        $carry = '';
        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 262144);
                if ($chunk === false) {
                    return ['checked' => true, 'ok' => false, 'message' => 'The dump is corrupt — decompression failed.', 'tables' => 0, 'rows' => null];
                }
                $bytes += strlen($chunk);
                $window = $carry.$chunk;
                $tables += substr_count($window, 'CREATE TABLE ');
                $inserts += substr_count($window, 'INSERT INTO ') + substr_count($window, 'COPY ');
                if (str_contains($window, 'PostgreSQL database dump complete') || str_contains($window, 'Dump completed on')) {
                    $complete = true;
                }
                // Keep a tail so a marker split across chunks is still found.
                $carry = substr($window, -64);
                if ($bytes > self::SCAN_CAP) {
                    break;
                }
            }
        } finally {
            gzclose($gz);
        }

        if ($tables === 0) {
            return ['checked' => true, 'ok' => false, 'message' => 'The dump defines no tables.', 'tables' => 0, 'rows' => null];
        }
        if (! $complete) {
            return ['checked' => true, 'ok' => false, 'message' => sprintf('The dump has %d table(s) but no completion marker — it is truncated.', $tables), 'tables' => $tables, 'rows' => null];
        }

        return [
            'checked' => true,
            'ok' => true,
            'message' => sprintf('Dump parses completely: %d table(s), %d data statement(s). Replay it with backup:restore-db.', $tables, $inserts),
            'tables' => $tables,
            'rows' => null,
        ];
    }

    /**
     * Fetch a random sample of one source's mirrored objects and compare their
     * sha256 against the live counterpart — the DB row's recorded hash when the
     * module keeps one, otherwise the hash of the live blob on disk.
     *
     * @param  array{checked: int, matched: int, mismatched: int, mismatches: list<string>, errors: list<string>}  $out
     */
    private function drillBlobs(Filesystem $fs, BackupJob $job, string $source, int $sampleSize, ?string $passphrase, array &$out): void
    {
        $prefix = (Str::slug(is_string($job->name) ? $job->name : '') ?: 'backup').'-'.$job->id;
        $mirrorRoot = $prefix.'/mirror/';
        $ledger = $this->readLedger($fs, $mirrorRoot.'.ledger-'.$source.'.json');

        $keys = array_keys($ledger);
        if ($keys === []) {
            return; // nothing mirrored for this source yet — not a failure
        }
        shuffle($keys);
        $sample = array_slice($keys, 0, max(1, $sampleSize));

        $disk = BlobStore::disk();
        foreach ($sample as $key) {
            $out['checked']++;
            $remote = $mirrorRoot.$key;
            if (! $fs->fileExists($remote)) {
                $out['errors'][] = $source.': '.$key.' is in the ledger but missing from the mirror.';

                continue;
            }

            try {
                $backedUp = $this->hashMirrored($fs, $remote, (bool) ($ledger[$key]['enc'] ?? false), $passphrase);
            } catch (Throwable $e) {
                $out['errors'][] = $source.': '.$key.' could not be read back ('.$e->getMessage().').';

                continue;
            }

            $expected = $this->liveHash($key, $disk);
            if ($expected === null) {
                $out['errors'][] = $source.': '.$key.' has no live counterpart to compare against.';

                continue;
            }

            if (hash_equals($expected['hash'], $backedUp)) {
                $out['matched']++;
            } else {
                $out['mismatched']++;
                $out['mismatches'][] = $source.': '.$key.' differs from the live copy (compared against '.$expected['source'].').';
            }
        }
    }

    /**
     * sha256 of a mirrored object as it would be restored: decrypted when the
     * ledger says it was stored encrypted. Streams through throwaway files.
     */
    private function hashMirrored(Filesystem $fs, string $remote, bool $encrypted, ?string $passphrase): string
    {
        $staged = DiskTempFile::create('lldrillblob');
        $this->download($fs, $remote, $staged->path());

        if (! $encrypted) {
            return $this->hashFile($staged->path());
        }

        if (($passphrase ?? '') === '') {
            throw new RuntimeException('mirror is encrypted but no passphrase is available');
        }
        $plain = DiskTempFile::create('lldrillplain');
        $this->cipher->decryptFile($staged->path(), $plain->path(), (string) $passphrase);

        return $this->hashFile($plain->path());
    }

    /**
     * The authoritative live hash for a blob key. The DB row is preferred where
     * the module records one (it is what the app itself trusts); otherwise the
     * live object is hashed directly. Read-only in both cases.
     *
     * @return array{hash: string, source: string}|null
     */
    private function liveHash(string $key, \Illuminate\Contracts\Filesystem\Filesystem $disk): ?array
    {
        $recorded = $this->recordedHash($key);
        if ($recorded !== null) {
            return ['hash' => $recorded, 'source' => 'the database row'];
        }

        if (! $disk->exists($key)) {
            return null;
        }
        $stream = $disk->readStream($key);
        if (! is_resource($stream)) {
            return null;
        }
        $ctx = hash_init('sha256');
        try {
            hash_update_stream($ctx, $stream);
        } finally {
            fclose($stream);
        }

        return ['hash' => hash_final($ctx), 'source' => 'the live file'];
    }

    /** The sha256 a module recorded for this storage path, when it keeps one. */
    private function recordedHash(string $key): ?string
    {
        if (str_starts_with($key, 'files/')) {
            $hash = FileEntry::withoutGlobalScopes()->withTrashed()->where('storage_path', $key)->value('sha256');

            return is_string($hash) && $hash !== '' ? $hash : null;
        }
        if (str_starts_with($key, 'gallery/')) {
            $hash = GalleryPhoto::withoutGlobalScopes()->withTrashed()->where('storage_path', $key)->value('sha256');

            return is_string($hash) && $hash !== '' ? $hash : null;
        }

        return null;
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('could not hash the staged copy');
        }

        return $hash;
    }

    /** Decompress a gzip file to a throwaway path, streaming (memory-flat). */
    private function gunzip(string $from, string $to): void
    {
        $gz = gzopen($from, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Could not open the gzipped dump.');
        }
        $out = fopen($to, 'wb');
        if ($out === false) {
            gzclose($gz);
            throw new RuntimeException('Could not stage the restored database.');
        }
        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 262144);
                if ($chunk === false) {
                    throw new RuntimeException('The dump is corrupt — decompression failed.');
                }
                if ($chunk !== '' && fwrite($out, $chunk) === false) {
                    throw new RuntimeException('Could not write the staged database.');
                }
            }
        } finally {
            fclose($out);
            gzclose($gz);
        }
    }

    private function download(Filesystem $fs, string $path, string $to): void
    {
        $stream = $fs->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Could not read '.$path.' from the destination.');
        }
        $out = fopen($to, 'wb');
        if ($out === false) {
            fclose($stream);
            throw new RuntimeException('Could not stage '.$path.' locally.');
        }
        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
            fclose($stream);
        }
    }

    /**
     * @return array<string, array{size: int, enc: bool}>
     */
    private function readLedger(Filesystem $fs, string $path): array
    {
        if (! $fs->fileExists($path)) {
            return [];
        }
        try {
            $raw = $fs->read($path);
        } catch (Throwable) {
            return [];
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && is_array($v) && isset($v['size']) && is_numeric($v['size'])) {
                $out[$k] = ['size' => (int) $v['size'], 'enc' => (bool) ($v['enc'] ?? false)];
            }
        }

        return $out;
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
