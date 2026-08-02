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
use App\Services\Backup\BackupVerifier;
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
 * Unencrypted database backups are ALLOWED (operator opt-out, 2026-08-02).
 * A database dump carries the wrapped vault keys + non-ZK rows off-box; the
 * operator accepts that exposure. Encryption stays the default; the UI warns.
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupDestinationFactory $factory) {}

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
     * Unencrypted DB backups are allowed (operator opt-out, Security-Register).
     * A database dump carries the non-ZK rows in plaintext AND the wrapped
     * vault-key material (an offline passphrase-cracking oracle); the operator
     * accepts that off-box exposure. Encryption remains the default.
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
            'runs' => $runs->map(fn (BackupRun $r): array => [
                'id' => $r->id,
                'job' => $r->job?->name,
                'status' => $r->status,
                'message' => $r->message,
                'log' => $r->log,
                'startedIso' => $r->started_at?->toIso8601String(),
                'startedHuman' => $r->started_at?->diffForHumans(),
                'size' => $r->bytes ? Number::fileSize($r->bytes) : null,
                'downloadable' => $r->status === 'success' && $r->filename !== null && ! str_ends_with((string) $r->filename, '/'),
                'encrypted' => $r->status === 'success' && str_ends_with((string) $r->filename, '.enc'),
                'cancellable' => $r->status === 'running' && ! $r->cancel_requested,
                'cancelling' => $r->status === 'running' && $r->cancel_requested,
                'verifiable' => $r->status === 'success' && $r->filename !== null,
                'needsPassphrase' => $r->status === 'success' && str_ends_with((string) $r->filename, '.enc'),
                'verifyStatus' => $r->verify_status,
                'verifyMessage' => $r->verify_message,
                'verifiedHuman' => $r->verified_at?->diffForHumans(),
            ])->all(),
        ]);
    }

    /** Stream a completed backup archive to the caller. */
    public function downloadRun(BackupRun $run): StreamedResponse
    {
        abort_unless($run->status === 'success' && $run->filename && ! str_ends_with((string) $run->filename, '/'), 404);
        $job = $run->job;
        abort_unless($job !== null && $job->destination !== null, 404);

        $fs = $this->factory->make($job->destination);
        abort_unless($fs->fileExists($run->filename), 404);

        $name = basename($run->filename);

        return response()->streamDownload(function () use ($fs, $run): void {
            $stream = $fs->readStream($run->filename);
            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $name, ['Content-Type' => 'application/octet-stream']);
    }

    /**
     * Verify a completed backup archive and return the result.
     * Nothing is written to live data — this is a read-only integrity check.
     */
    public function verifyRun(Request $request, BackupRun $run, BackupVerifier $verifier): JsonResponse
    {
        abort_unless($run->status === 'success' && $run->filename, 404);
        $request->validate(['passphrase' => ['nullable', 'string', 'max:255']]);

        $passphrase = $request->input('passphrase') !== null ? $request->string('passphrase')->value() : null;
        $result = $verifier->verify($run, $passphrase);

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
     * Fetch an encrypted backup archive, decrypt it with the supplied passphrase,
     * and stream the plaintext archive — the headless/CLI-admin equivalent of the
     * backups:decrypt artisan command. Nothing is written to live data.
     */
    public function decryptRun(Request $request, BackupRun $run, ArchiveCipher $cipher): StreamedResponse
    {
        abort_unless($run->status === 'success' && $run->filename && str_ends_with((string) $run->filename, '.enc'), 404);
        $job = $run->job;
        abort_unless($job !== null && $job->destination !== null, 404);
        $request->validate(['passphrase' => ['required', 'string', 'max:255']]);
        $passphrase = $request->string('passphrase')->value();

        $fs = $this->factory->make($job->destination);
        abort_unless($fs->fileExists($run->filename), 404);

        $enc = tempnam(sys_get_temp_dir(), 'llbenc');
        $dec = tempnam(sys_get_temp_dir(), 'llbdec');

        if ($enc === false || $dec === false) {
            @unlink((string) $enc);
            @unlink((string) $dec);
            abort(500);
        }

        $stream = $fs->readStream($run->filename);
        if ($stream === null) {
            @unlink($enc);
            @unlink($dec);
            abort(404);
        }

        $out = fopen($enc, 'w');
        if ($out === false) {
            fclose($stream);
            @unlink($enc);
            @unlink($dec);
            throw new \RuntimeException("Cannot open staging file for backup decryption: {$enc}.");
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        try {
            $cipher->decryptFile($enc, $dec, $passphrase);
        } catch (\Throwable) {
            @unlink($enc);
            @unlink($dec);
            abort(422, 'Wrong passphrase or corrupt archive.');
        }
        @unlink($enc);

        $name = preg_replace('/\.enc$/', '', basename((string) $run->filename)) ?: 'backup';

        return response()->streamDownload(function () use ($dec): void {
            readfile($dec);
            @unlink($dec);
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
            'mode' => $j->mode,
            'destination_id' => $j->backup_destination_id,
            'cron' => $j->cron,
            'retention' => $j->retention,
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
            'source' => ['required', Rule::in([...BackupJob::SOURCES, 'all'])],
            'mode' => ['sometimes', Rule::in(BackupJob::MODES)],
            'backup_destination_id' => ['required', 'exists:backup_destinations,id'],
            'cron' => ['required', 'string', 'max:64'],
            'retention' => ['required', 'integer', 'min:1', 'max:9999'],
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

        $source = $request->string('source')->value();
        $encrypt = $request->boolean('encrypt');
        $passphrase = $request->string('passphrase')->value();
        $notifyChannels = array_values(array_map(
            static fn (mixed $c): string => is_scalar($c) ? (string) $c : '',
            $request->collect('notify_channels')->all(),
        ));

        $data = [
            'name' => $request->string('name')->value(),
            'source' => $source,
            'mode' => $request->input('mode') !== null ? $request->string('mode')->value() : 'mirror',
            'backup_destination_id' => $request->integer('backup_destination_id'),
            'cron' => $cron,
            'retention' => $request->integer('retention'),
            'encrypt' => $encrypt,
            'passphrase' => $passphrase !== '' ? $passphrase : null,
            'notify_channels' => $notifyChannels,
            'enabled' => $request->boolean('enabled'),
        ];

        // Unencrypted database backups are ALLOWED (operator opt-out, 2026-08-02,
        // Security-Register). The dump carries non-ZK rows in plaintext PLUS the
        // wrapped vault-key material (an offline passphrase-cracking oracle) — the
        // operator accepts this off-box exposure; encryption remains the default.
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
            $lines[] = class_basename($cur).': '.$cur->getMessage();
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
