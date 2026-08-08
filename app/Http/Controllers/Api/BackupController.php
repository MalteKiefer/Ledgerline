<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Models\AuditLog;
use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Rules\SafeHost;
use App\Rules\SafeUrl;
use App\Services\Backup\ArchiveCipher;
use App\Services\Backup\BackupDestinationFactory;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupVerifier;
use App\Support\DiskTempFile;
use App\Support\Redactor;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin backup management over the API (Sanctum device token + manage-global-settings).
 *
 * Mirrors Settings\BackupController exactly, with two critical credential-hygiene
 * invariants enforced on every response:
 *
 *   1. BackupDestination.config (encrypted:array — holds S3/SFTP/WebDAV credentials)
 *      is NEVER serialised into any GET/list response.
 *   2. BackupJob.passphrase (encrypted — the only thing protecting the vault-key
 *      material inside a database dump) is NEVER serialised into any GET/list response.
 *
 * Encryption of a database dump is recommended (cleartext financial PII) but no
 * longer forced — the ZK/vault-key oracle is gone (plaintext pivot) and a local
 * FDE server may back up unencrypted by choice. Still 422 if encryption is
 * requested without an available passphrase.
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly BackupDestinationFactory $factory,
        private readonly BackupManager $manager,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  Destinations */
    /* ------------------------------------------------------------------ */

    /**
     * List all destinations.
     *
     * CRITICAL: only {id, name, driver} — config holds credentials and must
     * never appear in API responses.
     */
    public function destinations(): JsonResponse
    {
        $destinations = BackupDestination::orderBy('name')->get();

        return response()->json([
            'destinations' => $destinations->map(fn (BackupDestination $d): array => $this->presentDestination($d))->all(),
        ]);
    }

    /**
     * Create a destination. Runs the same assertReachable gate as the web
     * controller — the destination must be connectable before it is persisted.
     * The supplied config is accepted from the request body but never echoed back.
     */
    public function storeDestination(Request $request): JsonResponse
    {
        $data = $this->validateDestination($request);
        $this->assertReachable($data['driver'], $data['config']);
        $destination = BackupDestination::create($data);

        AuditLog::record('backup.destination.created', $destination);

        return response()->json(['destination' => $this->presentDestination($destination)], 201);
    }

    /**
     * Update a destination. A blank secret/password field keeps the stored value
     * (mirrors the web controller keep-existing-secret behaviour).
     */
    public function updateDestination(Request $request, BackupDestination $destination): JsonResponse
    {
        $data = $this->validateDestination($request, $destination);
        $this->assertReachable($data['driver'], $data['config']);
        $destination->update($data);

        AuditLog::record('backup.destination.updated', $destination);

        return response()->json(['destination' => $this->presentDestination($destination)]);
    }

    public function destroyDestination(BackupDestination $destination): JsonResponse
    {
        $destination->delete();

        AuditLog::record('backup.destination.deleted', null, ['id' => $destination->id]);

        return response()->json([], 204);
    }

    /**
     * Test a destination's config (from the request body) without saving it.
     * Returns {ok, detail} so a mobile client can surface the error inline.
     * Throttled to prevent using this endpoint as a port-scanner.
     */
    public function testDestination(Request $request): JsonResponse
    {
        $data = $this->validateDestination($request, $this->existingForTest($request));
        try {
            $this->factory->test($data['driver'], $data['config']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'detail' => $this->describeChain($e),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Jobs */
    /* ------------------------------------------------------------------ */

    /**
     * List all jobs with statistics.
     *
     * CRITICAL: passphrase is NEVER included — it is the key protecting the
     * vault-key material in database backups.
     */
    public function jobs(): JsonResponse
    {
        $jobs = BackupJob::with(['destination', 'runs'])->orderBy('name')->get();

        return response()->json([
            'jobs' => $jobs->map(fn (BackupJob $j): array => $this->presentJob($j))->all(),
        ]);
    }

    /**
     * Create a job.
     *
     * Encryption of a database dump is recommended (cleartext financial PII) but
     * optional; 422 only if encryption is requested without an available passphrase.
     */
    public function storeJob(Request $request): JsonResponse
    {
        $data = $this->validateJob($request, requirePassphrase: true);
        $job = BackupJob::create($data);

        AuditLog::record('backup.job.created', $job);

        return response()->json(['job' => $this->presentJob($job->load(['destination', 'runs']))], 201);
    }

    /**
     * Update a job. A blank passphrase field keeps the stored value.
     */
    public function updateJob(Request $request, BackupJob $job): JsonResponse
    {
        $data = $this->validateJob($request, requirePassphrase: false);
        if (($data['passphrase'] ?? '') === '') {
            unset($data['passphrase']);
        }
        $job->update($data);

        AuditLog::record('backup.job.updated', $job);

        return response()->json(['job' => $this->presentJob($job->load(['destination', 'runs']))]);
    }

    public function destroyJob(BackupJob $job): JsonResponse
    {
        $job->delete();

        AuditLog::record('backup.job.deleted', null, ['id' => $job->id]);

        return response()->json([], 204);
    }

    /** Dispatch the job to run immediately. */
    public function runNow(BackupJob $job): JsonResponse
    {
        RunBackupJob::dispatch($job->id);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /*  Runs */
    /* ------------------------------------------------------------------ */

    /** Recent runs (same shape as the web live-list endpoint). */
    public function runs(): JsonResponse
    {
        $this->reapStale();
        $runs = BackupRun::with('job')->latest('started_at')->limit(20)->get();

        return response()->json([
            'runs' => $runs->map(fn (BackupRun $r): array => $this->presentRun($r))->all(),
        ]);
    }

    /**
     * A run is a batch folder holding one archive per selected source.
     *
     * @return array<string, mixed>
     */
    private function presentRun(BackupRun $r): array
    {
        $ok = $r->status === 'success' && $r->filename !== null;
        $encrypt = (bool) ($r->job?->encrypt);
        $archives = $ok
            ? array_map(fn (string $s): array => [
                'source' => $s,
                'encrypted' => $encrypt,
                'restorable' => in_array($s, BackupJob::INCREMENTAL_SOURCES, true),
            ], $r->job?->effectiveSources() ?? [])
            : [];

        return [
            'id' => $r->id,
            'job' => $r->job?->name,
            'status' => $r->status,
            'message' => $r->message,
            'log' => $r->log,
            'startedIso' => $r->started_at?->toIso8601String(),
            'startedHuman' => $r->started_at?->diffForHumans(),
            'size' => $r->bytes ? Number::fileSize($r->bytes) : null,
            'archives' => $archives,
            'cancellable' => $r->status === 'running' && ! $r->cancel_requested,
            'cancelling' => $r->status === 'running' && $r->cancel_requested,
            'verifyStatus' => $r->verify_status,
            'verifyMessage' => $r->verify_message,
            'verifiedHuman' => $r->verified_at?->diffForHumans(),
        ];
    }

    /** Stream one source's archive from a run's batch to the caller. */
    public function downloadRun(Request $request, BackupRun $run): StreamedResponse
    {
        abort_unless($run->status === 'success' && $run->filename, 404);
        $request->validate(['source' => ['required', Rule::in(BackupJob::SOURCES)]]);
        $job = $run->job;
        abort_unless($job !== null && $job->destination !== null, 404);

        $fs = $this->factory->make($job->destination);
        $path = $this->manager->archiveIn($fs, (string) $run->filename, $request->string('source')->value());
        abort_unless($path !== null, 404);

        $name = basename($path);

        return response()->streamDownload(function () use ($fs, $path): void {
            $stream = $fs->readStream($path);
            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $name, ['Content-Type' => 'application/octet-stream']);
    }

    /**
     * Verify one source's archive in a run's batch and return the result.
     * Nothing is written to live data — this is a read-only integrity check.
     */
    public function verifyRun(Request $request, BackupRun $run, BackupVerifier $verifier): JsonResponse
    {
        abort_unless($run->status === 'success' && $run->filename, 404);
        $request->validate([
            'passphrase' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::in(BackupJob::SOURCES)],
        ]);
        $job = $run->job;
        abort_unless($job !== null && $job->destination !== null, 404);
        $fs = $this->factory->make($job->destination);
        $archive = $this->manager->archiveIn($fs, (string) $run->filename, $request->string('source')->value());
        abort_unless($archive !== null, 404);

        $passphrase = $request->input('passphrase') !== null ? $request->string('passphrase')->value() : null;
        $result = $verifier->verify($run, $passphrase, $archive);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'verifiedHuman' => $run->verified_at?->diffForHumans(),
        ]);
    }

    /**
     * Cancel a running backup. First request asks for graceful stop; a second
     * request (while cancel is already pending) force-finalises the run — for
     * when the worker was killed or wedged.
     */
    public function cancelRun(BackupRun $run): JsonResponse
    {
        $forced = false;
        if ($run->status === 'running') {
            if ($run->cancel_requested) {
                $run->update(['status' => 'cancelled', 'finished_at' => now(), 'message' => 'Force-stopped.']);
                $run->job?->update(['last_status' => 'cancelled']);
                $forced = true;
            } else {
                $run->update(['cancel_requested' => true]);
            }
        }

        return response()->json(['ok' => true, 'forced' => $forced]);
    }

    /**
     * Restore a blob source (files/invoices) from a run's batch onto the live
     * disk (additive overwrite). The database is NOT restorable via the API —
     * download the dump and run `backup:restore-db`.
     */
    public function restoreRun(Request $request, BackupRun $run, BackupManager $manager): JsonResponse
    {
        abort_unless($run->status === 'success' && $run->filename, 404);
        $request->validate(['source' => ['required', Rule::in(BackupJob::INCREMENTAL_SOURCES)]]);
        $job = $run->job;
        abort_unless($job !== null && $job->destination !== null, 404);

        try {
            $written = $manager->restoreBlobs($job, (string) $run->filename, $request->string('source')->value());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => Str::limit($e->getMessage(), 300)], 422);
        }
        AuditLog::record('backup.restored', $run, ['source' => $request->string('source')->value(), 'files' => $written]);

        return response()->json(['ok' => true, 'files' => $written]);
    }

    /**
     * Fetch an encrypted backup archive, decrypt it with the supplied passphrase,
     * and stream the plaintext archive — the headless/CLI-admin equivalent of the
     * backups:decrypt artisan command. Nothing is written to live data.
     */
    public function decryptRun(Request $request, BackupRun $run, ArchiveCipher $cipher): StreamedResponse
    {
        abort_unless($run->status === 'success' && $run->filename, 404);
        $request->validate([
            'passphrase' => ['required', 'string', 'max:255'],
            'source' => ['required', Rule::in(BackupJob::SOURCES)],
        ]);
        $job = $run->job;
        abort_unless($job !== null && $job->destination !== null, 404);
        $passphrase = $request->string('passphrase')->value();

        $fs = $this->factory->make($job->destination);
        $encPath = $this->manager->archiveIn($fs, (string) $run->filename, $request->string('source')->value());
        abort_unless($encPath !== null && str_ends_with($encPath, '.enc'), 404);

        // RAII temp handles — the decrypted plaintext dump (full financial PII) is
        // unlinked even on throw/abort/client-disconnect; $decHandle is captured by
        // the stream closure so it survives until streaming completes, then destructs.
        $encHandle = DiskTempFile::create('llbenc');
        $decHandle = DiskTempFile::create('llbdec');

        $stream = $fs->readStream($encPath);
        abort_if($stream === null, 404);

        $out = fopen($encHandle->path(), 'w');
        if ($out === false) {
            fclose($stream);
            throw new \RuntimeException('Cannot open staging file for backup decryption.');
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        try {
            $cipher->decryptFile($encHandle->path(), $decHandle->path(), $passphrase);
        } catch (\Throwable) {
            abort(422, 'Wrong passphrase or corrupt archive.');
        }

        $name = preg_replace('/\.enc$/', '', basename($encPath)) ?: 'backup';

        return response()->streamDownload(function () use ($decHandle): void {
            readfile($decHandle->path());
        }, $name, ['Content-Type' => 'application/octet-stream']);
    }

    /* ------------------------------------------------------------------ */
    /*  Presentation helpers — credential hygiene enforced here */
    /* ------------------------------------------------------------------ */

    /**
     * Safe destination shape: NEVER includes config (holds remote credentials).
     *
     * @return array{id: int, name: string, driver: string}
     */
    private function presentDestination(BackupDestination $d): array
    {
        return ['id' => $d->id, 'name' => $d->name, 'driver' => $d->driver];
    }

    /**
     * Safe job shape: NEVER includes passphrase.
     *
     * @return array<string, mixed>
     */
    private function presentJob(BackupJob $j): array
    {
        $stats = $j->statistics();

        return [
            'id' => $j->id,
            'name' => $j->name,
            'source' => $j->source,
            'sources' => $j->effectiveSources(),
            'mode' => $j->mode ?? 'full',
            'destination_id' => $j->backup_destination_id,
            'cron' => $j->cron,
            'retention' => $j->retention,
            'keep_daily' => $j->retentionTiers()['daily'],
            'keep_weekly' => $j->retentionTiers()['weekly'],
            'keep_monthly' => $j->retentionTiers()['monthly'],
            'encrypt' => $j->encrypt,
            'notify_channels' => $j->notify_channels ?? [],
            'enabled' => $j->enabled,
            'last_run_at' => $j->last_run_at?->toIso8601String(),
            'last_status' => $j->last_status ?? null,
            'statistics' => [
                'runs' => $stats['runs'],
                'ok' => $stats['ok'],
                'failed' => $stats['failed'],
                'successRate' => $stats['successRate'],
                'lastStatus' => $stats['lastStatus'],
                'lastRun' => $stats['lastRun']?->toIso8601String(),
                'lastDuration' => $stats['lastDuration'],
                'avgDuration' => $stats['avgDuration'],
                'lastBytes' => $stats['lastBytes'],
                'totalBytes' => $stats['totalBytes'],
                'nextRun' => $stats['nextRun']?->toIso8601String(),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Shared validation / helpers (mirrors web controller exactly) */
    /* ------------------------------------------------------------------ */

    /** @return array{name: string, driver: string, config: array<string, mixed>} */
    private function validateDestination(Request $request, ?BackupDestination $existing = null): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::in(BackupDestination::DRIVERS)],
            // S3 / B2
            'bucket' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255', new SafeUrl],
            'use_path_style' => ['sometimes', 'boolean'],
            // SFTP / WebDAV
            'host' => ['nullable', 'string', 'max:255', new SafeHost],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'host_fingerprint' => ['nullable', 'string', 'max:255'],
            'base_uri' => ['nullable', 'string', 'max:255', new SafeUrl],
            'path' => ['nullable', 'string', 'max:255'],
        ]);

        $driver = $request->string('driver')->value();
        $keys = match ($driver) {
            's3', 'b2' => ['bucket', 'region', 'key', 'secret', 'endpoint', 'use_path_style', 'path'],
            'sftp' => ['host', 'port', 'username', 'password', 'host_fingerprint', 'path'],
            'webdav' => ['base_uri', 'username', 'password', 'path'],
            default => [],
        };

        $config = [];
        foreach ($keys as $k) {
            if ($k === 'use_path_style') {
                $config[$k] = $request->boolean('use_path_style');

                continue;
            }
            $v = $request->input($k);
            $config[$k] = $k === 'port'
                ? ($v !== null ? $request->integer('port') : null)
                : (is_string($v) ? $v : null);
        }

        // On edit, keep existing secret/password when left blank.
        foreach (['secret', 'password'] as $secret) {
            if (in_array($secret, $keys, true) && ($config[$secret] ?? '') === '' && $existing !== null) {
                $existingConfig = $existing->config;
                $config[$secret] = is_array($existingConfig) ? ($existingConfig[$secret] ?? null) : null;
            }
        }

        return ['name' => $request->string('name')->value(), 'driver' => $driver, 'config' => $config];
    }

    /**
     * Confirm a destination is reachable/writable before persisting it.
     *
     * @param  array<string, mixed>  $config
     */
    private function assertReachable(string $driver, array $config): void
    {
        try {
            $this->factory->test($driver, $config);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'name' => Str::limit($e->getMessage(), 200),
            ]);
        }
    }

    private function existingForTest(Request $request): ?BackupDestination
    {
        $id = $request->integer('destination_id');

        return $id ? BackupDestination::find($id) : null;
    }

    /** @return array<string, mixed> */
    private function validateJob(Request $request, bool $requirePassphrase): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*' => [Rule::in(BackupJob::SOURCES)],
            'source' => ['nullable', Rule::in(BackupJob::SOURCES)],
            'mode' => ['sometimes', Rule::in(BackupJob::MODES)],
            'backup_destination_id' => ['required', 'exists:backup_destinations,id'],
            'cron' => ['required', 'string', 'max:64'],
            'retention' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'keep_daily' => ['required', 'integer', 'min:0', 'max:9999'],
            'keep_weekly' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'keep_monthly' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'encrypt' => ['sometimes', 'boolean'],
            'passphrase' => ['nullable', 'string', 'min:12', 'max:255'],
            'notify_channels' => ['nullable', 'array'],
            'notify_channels.*' => [Rule::in(BackupJob::NOTIFY_CHANNELS)],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $cron = $request->string('cron')->value();
        if (! CronExpression::isValidExpression($cron)) {
            throw ValidationException::withMessages(['cron' => __('settings.backup_cron_invalid')]);
        }

        $sources = array_values(array_unique(array_filter(
            array_map(static fn (mixed $s): string => is_string($s) ? $s : '', $request->collect('sources')->all()),
            static fn (string $s): bool => in_array($s, BackupJob::SOURCES, true),
        )));
        if ($sources === []) {
            throw ValidationException::withMessages(['sources' => __('settings.backup_sources_required')]);
        }
        $mode = in_array($request->string('mode')->value(), BackupJob::MODES, true) ? $request->string('mode')->value() : 'full';
        $keepDaily = $request->integer('keep_daily');
        $encrypt = $request->boolean('encrypt');
        $passphrase = $request->string('passphrase')->value();
        $notifyChannels = array_values(array_map(
            static fn (mixed $c): string => is_scalar($c) ? (string) $c : '',
            $request->collect('notify_channels')->all(),
        ));

        $data = [
            'name' => $request->string('name')->value(),
            'sources' => $sources,
            'source' => $sources[0],
            'mode' => $mode,
            'backup_destination_id' => $request->integer('backup_destination_id'),
            'cron' => $cron,
            'retention' => max(1, $keepDaily),
            'keep_daily' => $keepDaily,
            'keep_weekly' => $request->input('keep_weekly') !== null ? $request->integer('keep_weekly') : 0,
            'keep_monthly' => $request->input('keep_monthly') !== null ? $request->integer('keep_monthly') : 0,
            'encrypt' => $encrypt,
            'passphrase' => $passphrase !== '' ? $passphrase : null,
            'notify_channels' => $notifyChannels,
            'enabled' => $request->boolean('enabled'),
        ];

        // Encryption is strongly recommended for a database dump (cleartext financial
        // PII) but no longer forced — the ZK/vault-key material that made an
        // unencrypted dump an offline cracking oracle is gone (plaintext pivot), and
        // a local FDE-encrypted server may back up unencrypted by choice.
        $envPassphrase = config('backup.passphrase', '');
        if ($requirePassphrase && $encrypt
            && $passphrase === '' && (is_string($envPassphrase) ? $envPassphrase : '') === '') {
            throw ValidationException::withMessages(['passphrase' => __('settings.backup_passphrase_required')]);
        }

        return $data;
    }

    /** Full exception chain, newest → root cause. */
    private function describeChain(\Throwable $e): string
    {
        $lines = [];
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            // Redact credentials a driver may echo into its error before returning.
            $lines[] = class_basename($cur).': '.Redactor::redact($cur->getMessage());
        }

        return implode("\n", array_unique($lines));
    }

    /**
     * Finalise stale running backup runs whose worker has died or been cancelled.
     * Mirrors Settings\BackupController::reapStale() exactly.
     */
    private function reapStale(): void
    {
        $now = now();

        BackupRun::where('status', 'running')
            ->where('cancel_requested', true)
            ->where('updated_at', '<', $now->copy()->subMinutes(2))
            ->update(['status' => 'cancelled', 'finished_at' => $now, 'message' => 'Cancelled (worker stopped).']);

        BackupRun::where('status', 'running')
            ->where('updated_at', '<', $now->copy()->subMinutes(30))
            ->update(['status' => 'failed', 'finished_at' => $now, 'message' => 'Interrupted (no progress).']);
    }
}
