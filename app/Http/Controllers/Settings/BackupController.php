<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

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
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backup configuration: remote destinations, scheduled jobs and run history.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        $this->reapStale();

        return view('settings.backup.index', [
            'destinations' => BackupDestination::orderBy('name')->get(),
            // Eager-load runs so each job's statistics() works in memory (no N+1).
            'jobs' => BackupJob::with(['destination', 'runs'])->orderBy('name')->get(),
            // The run history is loaded live via the runs() JSON endpoint.
        ]);
    }

    /* ---- Destinations ---- */

    public function __construct(private readonly BackupDestinationFactory $factory) {}

    public function storeDestination(Request $request): RedirectResponse
    {
        $data = $this->validateDestination($request);
        // Only persist a destination we can actually reach and write to.
        $this->assertReachable($data['driver'], $data['config']);
        $destination = BackupDestination::create($data);

        AuditLog::record('backup.destination.created', $destination);

        return back()->with('status', __('flash.backup_saved'));
    }

    public function updateDestination(Request $request, BackupDestination $destination): RedirectResponse
    {
        $data = $this->validateDestination($request, $destination);
        $this->assertReachable($data['driver'], $data['config']);
        $destination->update($data);

        AuditLog::record('backup.destination.updated', $destination);

        return redirect()->route('settings.backup.index')->with('status', __('flash.backup_saved'));
    }

    /**
     * Test a destination's config (from the form) without saving it. Returns
     * JSON so the form can report the result inline — no navigation, so the
     * operator's unsaved input is preserved.
     */
    public function testDestination(Request $request): JsonResponse
    {
        $data = $this->validateDestination($request, $this->existingForTest($request));
        try {
            $this->factory->test($data['driver'], $data['config']);
        } catch (\Throwable $e) {
            // Surface the full exception chain (root cause) so the operator can
            // see why the connection failed — auth, host, port, TLS, permissions.
            return response()->json([
                'ok' => false,
                'message' => __('flash.backup_test_failed', ['error' => '']),
                'detail' => $this->describeChain($e),
            ]);
        }

        return response()->json(['ok' => true, 'message' => __('flash.backup_test_ok')]);
    }

    /** Full exception chain, newest → root cause, one line each. */
    private function describeChain(\Throwable $e): string
    {
        $lines = [];
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            $lines[] = class_basename($cur).': '.$cur->getMessage();
        }

        return implode("\n", array_unique($lines));
    }

    public function destroyDestination(BackupDestination $destination): RedirectResponse
    {
        $destination->delete();

        AuditLog::record('backup.destination.deleted', null, ['id' => $destination->id]);

        return back()->with('status', __('flash.backup_deleted'));
    }

    /* ---- Jobs ---- */

    public function storeJob(Request $request): RedirectResponse
    {
        $data = $this->validateJob($request, requirePassphrase: true);
        $job = BackupJob::create($data);

        AuditLog::record('backup.job.created', $job);

        return back()->with('status', __('flash.backup_saved'));
    }

    public function updateJob(Request $request, BackupJob $job): RedirectResponse
    {
        $data = $this->validateJob($request, requirePassphrase: false);
        // Keep the stored passphrase if the field was left blank.
        if (($data['passphrase'] ?? '') === '') {
            unset($data['passphrase']);
        }
        $job->update($data);

        AuditLog::record('backup.job.updated', $job);

        return redirect()->route('settings.backup.index')->with('status', __('flash.backup_saved'));
    }

    public function destroyJob(BackupJob $job): RedirectResponse
    {
        $job->delete();

        AuditLog::record('backup.job.deleted', null, ['id' => $job->id]);

        return back()->with('status', __('flash.backup_deleted'));
    }

    public function runNow(Request $request, BackupJob $job): RedirectResponse|JsonResponse
    {
        RunBackupJob::dispatch($job->id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', __('flash.backup_queued'));
    }

    /** Recent runs as JSON, for the live-updating run list. */
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
                // Downloadable once finished successfully and the object still exists.
                // A trailing "/" marks a folder mirror (files/gallery) — no single archive to download.
                'downloadable' => $r->status === 'success' && $r->filename !== null && ! str_ends_with((string) $r->filename, '/'),
                // Encrypted archives (.enc) can be decrypted to a plaintext download.
                'encrypted' => $r->status === 'success' && str_ends_with((string) $r->filename, '.enc'),
                'cancellable' => $r->status === 'running' && ! $r->cancel_requested,
                'cancelling' => $r->status === 'running' && $r->cancel_requested,
                // Any successful run (archive or mirror) can be integrity-checked.
                'verifiable' => $r->status === 'success' && $r->filename !== null,
                // Verifying an encrypted archive needs the passphrase.
                'needsPassphrase' => $r->status === 'success' && str_ends_with((string) $r->filename, '.enc'),
                'verifyStatus' => $r->verify_status,
                'verifyMessage' => $r->verify_message,
                'verifiedHuman' => $r->verified_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Non-destructively verify a completed backup: confirm the archive is
     * present and intact, that the passphrase decrypts an encrypted archive, and
     * that the inner dump is a restorable database image (a dry run — nothing is
     * ever written to live data). The result is recorded on the run.
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

    /** Stream a completed backup archive from its destination to the browser. */
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
     * Fetch an encrypted (.enc) backup archive from its destination, decrypt it
     * with the supplied passphrase and stream the plaintext archive back — the
     * in-app equivalent of the backups:decrypt command, so recovering a backup
     * no longer needs SSH. Nothing is written to live data; the user gets a
     * decrypted archive to restore from.
     */
    public function decryptRun(Request $request, BackupRun $run, ArchiveCipher $cipher): StreamedResponse|RedirectResponse
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

            return back()->withErrors(['passphrase' => __('settings.backup_decrypt_failed')]);
        }
        @unlink($enc);

        $name = preg_replace('/\.enc$/', '', basename((string) $run->filename)) ?: 'backup';

        return response()->streamDownload(function () use ($dec): void {
            readfile($dec);
            @unlink($dec);
        }, $name, ['Content-Type' => 'application/octet-stream']);
    }

    /**
     * Stop a running backup. The first request asks it to stop gracefully at
     * its next checkpoint; a second request (once a cancel is already pending)
     * force-finalizes the run in the database — for when the worker was killed
     * or is wedged inside a long upload and can no longer reach a checkpoint.
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
     * Finalize runs whose worker is gone: a cancel was requested but nothing has
     * progressed for a couple of minutes, or a run has sat with no progress for
     * so long the process must have died. Each step touches the run, so
     * updated_at tracks liveness.
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
        // On edit, keep an existing secret/password when left blank.
        foreach (['secret', 'password'] as $secret) {
            if (in_array($secret, $keys, true) && ($config[$secret] ?? '') === '' && $existing !== null) {
                $existingConfig = $existing->config;
                $config[$secret] = is_array($existingConfig) ? ($existingConfig[$secret] ?? null) : null;
            }
        }

        return ['name' => $request->string('name')->value(), 'driver' => $driver, 'config' => $config];
    }

    /**
     * Confirm a destination is reachable/writable, else block the save.
     *
     * @param  array<string, mixed>  $config
     */
    private function assertReachable(string $driver, array $config): void
    {
        try {
            $this->factory->test($driver, $config);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'name' => __('flash.backup_test_failed', ['error' => Str::limit($e->getMessage(), 200)]),
            ]);
        }
    }

    /** The destination being edited (for keeping a blank secret) when testing. */
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
            'source' => ['required', Rule::in(BackupJob::SOURCES)],
            'mode' => ['sometimes', Rule::in(BackupJob::MODES)],
            'backup_destination_id' => ['required', 'exists:backup_destinations,id'],
            'cron' => ['required', 'string', 'max:64'],
            'retention' => ['required', 'integer', 'min:1', 'max:9999'],
            'encrypt' => ['sometimes', 'boolean'],
            // Min length: this passphrase is the only thing standing between an
            // offline attacker and the wrapped vault-key material in the dump.
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
            $request->collect('notify_channels')->all()
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

        // A database dump carries the non-ZK rows in plaintext plus the wrapped
        // vault-key material (an offline passphrase-cracking oracle). It must
        // never be written to an off-box destination unencrypted.
        if ($source === 'database' && ! $encrypt) {
            throw ValidationException::withMessages(['encrypt' => __('settings.backup_db_encrypt_required')]);
        }
        // A per-job passphrase is only required when there is no environment
        // passphrase (BACKUP_PASSPHRASE); the latter keeps the key out of the DB.
        $envPassphrase = config('backup.passphrase', '');
        if ($requirePassphrase && $encrypt
            && $passphrase === '' && (is_string($envPassphrase) ? $envPassphrase : '') === '') {
            throw ValidationException::withMessages(['passphrase' => __('settings.backup_passphrase_required')]);
        }

        return $data;
    }
}
