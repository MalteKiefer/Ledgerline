<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\FileEntry;
use App\Models\MailAttachment;
use App\Support\BlobStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * VirusTotal integration: only the existing SHA-256 is ever sent to the fixed
 * VirusTotal API origin. File bytes are never uploaded, so an unknown hash stays
 * unknown instead of silently disclosing user content to a third party.
 */
class VirusTotalController extends Controller
{
    private const PROBE_HASH = '275a021bbfb6489e54d471899f7db9d18e5a807f17f81f4b6b3cfa861fc5b3ec';

    public function settings(): JsonResponse
    {
        return response()->json(['configured' => $this->key() !== '']);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate(['api_key' => ['required', 'string', 'max:128']]);
        $apiKey = $request->input('api_key');
        $key = is_string($apiKey) ? trim($apiKey) : '';
        if ($key === '') {
            return response()->json(['error' => 'virustotal_key_required'], 422);
        }

        // Do not acknowledge a secret until the same hash-only endpoint used by
        // file checks has accepted it. No user file bytes leave this host.
        $probe = $this->requestHash($key, self::PROBE_HASH);
        if (in_array($probe->status(), [401, 403], true)) {
            return response()->json(['error' => 'virustotal_invalid_api_key'], 422);
        }
        if ($probe->status() === 429) {
            return response()->json(['error' => 'virustotal_rate_limited'], 429);
        }
        if (! $probe->successful() && $probe->status() !== 404) {
            return response()->json(['error' => 'virustotal_unavailable'], 502);
        }

        $settings = AppSettings::current();
        $settings->update(['virustotal_api_key' => $key]);
        AuditLog::record('settings.updated', null, ['group' => 'virustotal', 'configured' => true, 'verified' => true]);

        return response()->json(['configured' => true, 'verified' => true]);
    }

    public function lookup(Request $request, FileEntry $file): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_if((int) $file->user_id !== (int) $user->id, 404);
        if (! is_string($file->sha256) || preg_match('/\A[a-f0-9]{64}\z/i', $file->sha256) !== 1) {
            return response()->json(['error' => 'virustotal_hash_unavailable'], 422);
        }

        return $this->lookupHash($file, $file->sha256, 'files.virustotal_lookup');
    }

    /** Check an archived Mail attachment by hash only — attachment bytes never leave this host. */
    public function lookupAttachment(Request $request, MailAttachment $attachment): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_if((int) $attachment->user_id !== (int) $user->id, 404);

        $stream = BlobStore::disk()->readStream('mail/att/'.$attachment->blob);
        abort_unless(is_resource($stream), 404);

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            $hash = hash_final($context);
        } finally {
            fclose($stream);
        }

        return $this->lookupHash($attachment, $hash, 'mail.attachment_virustotal_lookup');
    }

    private function lookupHash(Model $subject, string $hash, string $auditAction): JsonResponse
    {
        $key = $this->key();
        if ($key === '') {
            return response()->json(['error' => 'virustotal_not_configured'], 422);
        }

        $response = $this->requestHash($key, $hash);

        if ($response->status() === 404) {
            AuditLog::record($auditAction, $subject, ['known' => false]);

            return response()->json(['known' => false, 'sha256' => $hash]);
        }
        if (in_array($response->status(), [401, 403], true)) {
            return response()->json(['error' => 'virustotal_invalid_api_key'], 422);
        }
        if ($response->status() === 429) {
            return response()->json(['error' => 'virustotal_rate_limited'], 429);
        }
        if (! $response->successful()) {
            return response()->json(['error' => 'virustotal_unavailable'], 502);
        }

        $attributes = $response->json('data.attributes');
        $stats = is_array($attributes) && is_array($attributes['last_analysis_stats'] ?? null)
            ? $attributes['last_analysis_stats'] : [];
        AuditLog::record($auditAction, $subject, ['known' => true]);

        return response()->json([
            'known' => true,
            'sha256' => $hash,
            'stats' => [
                'malicious' => $this->integer($stats['malicious'] ?? null),
                'suspicious' => $this->integer($stats['suspicious'] ?? null),
                'harmless' => $this->integer($stats['harmless'] ?? null),
                'undetected' => $this->integer($stats['undetected'] ?? null),
            ],
            'reputation' => is_array($attributes) ? $this->integer($attributes['reputation'] ?? null) : 0,
            'last_analysis_date' => $this->date($attributes),
        ]);
    }

    private function key(): string
    {
        return trim((string) (AppSettings::current()->virustotal_api_key ?? ''));
    }

    private function requestHash(string $key, string $hash): Response
    {
        // The API key rides in a custom header, which a redirect-following
        // client would carry to whatever host the response names (only
        // Authorization/Cookie are dropped automatically). The origin is fixed,
        // so there is no redirect worth following in the first place.
        return Http::withOptions(['allow_redirects' => false])
            ->acceptJson()->withHeaders(['x-apikey' => $key])->timeout(12)
            ->get('https://www.virustotal.com/api/v3/files/'.$hash);
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function date(mixed $attributes): ?string
    {
        if (! is_array($attributes)) {
            return null;
        }

        $timestamp = $this->integer($attributes['last_analysis_date'] ?? null);

        return $timestamp > 0 ? gmdate(DATE_ATOM, $timestamp) : null;
    }
}
