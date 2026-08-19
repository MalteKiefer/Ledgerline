<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\FileEntry;
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
    public function settings(): JsonResponse
    {
        return response()->json(['configured' => $this->key() !== '']);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate(['api_key' => ['nullable', 'string', 'max:128']]);
        $settings = AppSettings::current();
        if ($request->has('api_key')) {
            $apiKey = $request->input('api_key');
            $key = is_string($apiKey) ? trim($apiKey) : '';
            $settings->update(['virustotal_api_key' => $key !== '' ? $key : null]);
            AuditLog::record('settings.updated', null, ['group' => 'virustotal', 'configured' => $key !== '']);
        }

        return response()->json(['configured' => trim((string) ($settings->virustotal_api_key ?? '')) !== '']);
    }

    public function lookup(Request $request, FileEntry $file): JsonResponse
    {
        $user = $this->requireUser($request);
        abort_if((int) $file->user_id !== (int) $user->id, 404);
        $key = $this->key();
        if ($key === '') {
            return response()->json(['error' => 'virustotal_not_configured'], 422);
        }
        if (! is_string($file->sha256) || preg_match('/\A[a-f0-9]{64}\z/i', $file->sha256) !== 1) {
            return response()->json(['error' => 'virustotal_hash_unavailable'], 422);
        }

        $response = Http::acceptJson()->withToken($key)->timeout(12)
            ->get('https://www.virustotal.com/api/v3/files/'.$file->sha256);

        if ($response->status() === 404) {
            AuditLog::record('files.virustotal_lookup', $file, ['known' => false]);

            return response()->json(['known' => false, 'sha256' => $file->sha256]);
        }
        if (! $response->successful()) {
            return response()->json(['error' => 'virustotal_unavailable'], 502);
        }

        $attributes = $response->json('data.attributes');
        $stats = is_array($attributes) && is_array($attributes['last_analysis_stats'] ?? null)
            ? $attributes['last_analysis_stats'] : [];
        AuditLog::record('files.virustotal_lookup', $file, ['known' => true]);

        return response()->json([
            'known' => true,
            'sha256' => $file->sha256,
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

    /** @param mixed $value */
    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    /** @param mixed $attributes */
    private function date(mixed $attributes): ?string
    {
        if (! is_array($attributes)) {
            return null;
        }

        $timestamp = $this->integer($attributes['last_analysis_date'] ?? null);

        return $timestamp > 0 ? gmdate(DATE_ATOM, $timestamp) : null;
    }
}
