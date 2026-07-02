<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Models\BackupDestination;
use App\Models\BackupJob;
use App\Models\BackupRun;
use Cron\CronExpression;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'jobs' => BackupJob::with('destination')->orderBy('name')->get(),
            'runs' => BackupRun::with('job')->latest('started_at')->limit(20)->get(),
        ]);
    }

    /* ---- Destinations ---- */

    public function storeDestination(Request $request): RedirectResponse
    {
        $data = $this->validateDestination($request);
        BackupDestination::create($data);

        return back()->with('status', __('flash.backup_saved'));
    }

    public function updateDestination(Request $request, BackupDestination $destination): RedirectResponse
    {
        $data = $this->validateDestination($request, $destination);
        $destination->update($data);

        return redirect()->route('settings.backup.index')->with('status', __('flash.backup_saved'));
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
