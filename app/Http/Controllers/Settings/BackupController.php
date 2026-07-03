<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Models\BackupRun;
use App\Services\Backup\BackupDestinationFactory;
use Cron\CronExpression;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Backup configuration: remote destinations, scheduled jobs and run history.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('settings.backup.index', [
            'destinations' => BackupDestination::orderBy('name')->get(),
            // Eager-load runs so each job's statistics() works in memory (no N+1).
            'jobs' => BackupJob::with(['destination', 'runs'])->orderBy('name')->get(),
            'runs' => BackupRun::with('job')->latest('started_at')->limit(20)->get(),
        ]);
    }

    /* ---- Destinations ---- */

    public function __construct(private readonly BackupDestinationFactory $factory) {}

    public function storeDestination(Request $request): RedirectResponse
    {
        $data = $this->validateDestination($request);
        // Only persist a destination we can actually reach and write to.
        $this->assertReachable($data['driver'], $data['config']);
        BackupDestination::create($data);

        return back()->with('status', __('flash.backup_saved'));
    }

    public function updateDestination(Request $request, BackupDestination $destination): RedirectResponse
    {
        $data = $this->validateDestination($request, $destination);
        $this->assertReachable($data['driver'], $data['config']);
        $destination->update($data);

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

        return back()->with('status', __('flash.backup_deleted'));
    }

    /* ---- Jobs ---- */

    public function storeJob(Request $request): RedirectResponse
    {
        $data = $this->validateJob($request, requirePassphrase: true);
        BackupJob::create($data);

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

        return redirect()->route('settings.backup.index')->with('status', __('flash.backup_saved'));
    }

    public function destroyJob(BackupJob $job): RedirectResponse
    {
        $job->delete();

        return back()->with('status', __('flash.backup_deleted'));
    }

    public function runNow(BackupJob $job): RedirectResponse
    {
        RunBackupJob::dispatch($job->id);

        return back()->with('status', __('flash.backup_queued'));
    }

    private function validateDestination(Request $request, ?BackupDestination $existing = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::in(BackupDestination::DRIVERS)],
            // S3 / B2
            'bucket' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'use_path_style' => ['sometimes', 'boolean'],
            // SFTP / WebDAV
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'base_uri' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
        ]);

        $keys = match ($validated['driver']) {
            's3', 'b2' => ['bucket', 'region', 'key', 'secret', 'endpoint', 'use_path_style', 'path'],
            'sftp' => ['host', 'port', 'username', 'password', 'path'],
            'webdav' => ['base_uri', 'username', 'password', 'path'],
            default => [],
        };
        $config = [];
        foreach ($keys as $k) {
            $config[$k] = $k === 'use_path_style' ? $request->boolean('use_path_style') : ($validated[$k] ?? null);
        }
        // On edit, keep an existing secret/password when left blank.
        foreach (['secret', 'password'] as $secret) {
            if (in_array($secret, $keys, true) && ($config[$secret] ?? '') === '' && $existing !== null) {
                $config[$secret] = $existing->config[$secret] ?? null;
            }
        }

        return ['name' => $validated['name'], 'driver' => $validated['driver'], 'config' => $config];
    }

    /** Confirm a destination is reachable/writable, else block the save. */
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

    private function validateJob(Request $request, bool $requirePassphrase): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'source' => ['required', Rule::in(BackupJob::SOURCES)],
            'backup_destination_id' => ['required', 'exists:backup_destinations,id'],
            'cron' => ['required', 'string', 'max:64'],
            'retention' => ['required', 'integer', 'min:1', 'max:9999'],
            'encrypt' => ['sometimes', 'boolean'],
            'passphrase' => ['nullable', 'string', 'max:255'],
            'notify' => ['required', Rule::in(BackupJob::NOTIFY_CHANNELS)],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        if (! CronExpression::isValidExpression($data['cron'])) {
            throw ValidationException::withMessages(['cron' => __('settings.backup_cron_invalid')]);
        }

        $data['encrypt'] = $request->boolean('encrypt');
        $data['enabled'] = $request->boolean('enabled');
        if ($requirePassphrase && $data['encrypt'] && ($data['passphrase'] ?? '') === '') {
            throw ValidationException::withMessages(['passphrase' => __('settings.backup_passphrase_required')]);
        }

        return $data;
    }
}
