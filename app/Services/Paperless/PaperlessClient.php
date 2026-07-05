<?php

declare(strict_types=1);

namespace App\Services\Paperless;

use App\Models\UserSetting;
use App\Support\OutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Paperless-ngx REST API, authenticated with a stored
 * API token. Used to list and create tags / document types / correspondents
 * and to upload documents. The base URL and token live (encrypted) per user on
 * user_settings; this never persists credentials itself.
 */
class PaperlessClient
{
    /** Term kind → Paperless API collection segment. */
    private const ENDPOINTS = [
        'tag' => 'tags',
        'document_type' => 'document_types',
        'correspondent' => 'correspondents',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    /** Build a client from a user's stored settings, or null if not configured. */
    public static function forUser(int $userId): ?self
    {
        return self::fromUserSetting(UserSetting::for($userId));
    }

    /** Build a client from a UserSetting row, or null if not configured. */
    public static function fromUserSetting(UserSetting $settings): ?self
    {
        $url = trim((string) $settings->paperless_url);
        $token = trim((string) $settings->paperless_token);
        if ($url === '' || $token === '') {
            return null;
        }

        return new self($url, $token);
    }

    /** Verify the URL + token by hitting a cheap authenticated endpoint. */
    public function ping(): void
    {
        $res = $this->http()->get('/api/documents/', ['page_size' => 1]);
        if ($res->status() === 401 || $res->status() === 403) {
            throw new RuntimeException('Authentication failed (check the API token).');
        }
        if (! $res->successful()) {
            throw new RuntimeException('Paperless returned HTTP '.$res->status().'.');
        }
    }

    /**
     * Total number of terms of a kind, read from the collection's `count`
     * field (a single request — no pagination walk).
     */
    public function count(string $kind): int
    {
        $endpoint = self::ENDPOINTS[$kind] ?? throw new RuntimeException("Unknown Paperless term kind: {$kind}");
        $res = $this->http()->get("/api/{$endpoint}/", ['page_size' => 1]);
        if ($res->status() === 401 || $res->status() === 403) {
            throw new RuntimeException('Authentication failed (check the API token).');
        }
        if (! $res->successful()) {
            throw new RuntimeException("Reading {$endpoint} failed: HTTP ".$res->status().'.');
        }

        return (int) ($res->json('count') ?? 0);
    }

    /**
     * All terms of a kind, following pagination.
     *
     * @return list<array{paperless_id:int, name:string, color:?string}>
     */
    public function list(string $kind): array
    {
        $endpoint = self::ENDPOINTS[$kind] ?? throw new RuntimeException("Unknown Paperless term kind: {$kind}");
        $out = [];
        $page = 1;
        do {
            $res = $this->http()->get("/api/{$endpoint}/", ['page' => $page, 'page_size' => 250]);
            if (! $res->successful()) {
                throw new RuntimeException("Listing {$endpoint} failed: HTTP ".$res->status().'.');
            }
            $body = $res->json();
            foreach ($body['results'] ?? [] as $r) {
                $out[] = [
                    'paperless_id' => (int) $r['id'],
                    'name' => (string) ($r['name'] ?? ''),
                    'color' => $r['color'] ?? $r['colour'] ?? null,
                ];
            }
            $page++;
        } while (! empty($body['next']));

        return $out;
    }

    /**
     * Create a term and return its new Paperless id + name. Idempotent: if the
     * name already exists (Paperless answers 400 on the unique constraint), the
     * existing term is looked up and returned instead of failing.
     *
     * @return array{paperless_id:int, name:string, color:?string}
     */
    public function create(string $kind, string $name): array
    {
        $endpoint = self::ENDPOINTS[$kind] ?? throw new RuntimeException("Unknown Paperless term kind: {$kind}");
        $res = $this->http()->post("/api/{$endpoint}/", ['name' => $name]);

        if ($res->successful()) {
            return $this->shape($res->json(), $name);
        }

        // A 400 is usually "already exists" — reuse the existing term so the
        // caller can just pick it. Anything else is a genuine error.
        if ($res->status() === 400) {
            $existing = $this->findByName($endpoint, $name);
            if ($existing !== null) {
                return $existing;
            }
        }

        throw new RuntimeException("Creating {$kind} failed: HTTP ".$res->status().'.');
    }

    /** Find a term by exact (case-insensitive) name, or null. */
    private function findByName(string $endpoint, string $name): ?array
    {
        $res = $this->http()->get("/api/{$endpoint}/", ['name__iexact' => $name]);
        if (! $res->successful()) {
            return null;
        }
        foreach ($res->json('results') ?? [] as $r) {
            if (strcasecmp((string) ($r['name'] ?? ''), $name) === 0) {
                return $this->shape($r, $name);
            }
        }

        return null;
    }

    /** @return array{paperless_id:int, name:string, color:?string} */
    private function shape(array $r, string $fallbackName): array
    {
        return [
            'paperless_id' => (int) $r['id'],
            'name' => (string) ($r['name'] ?? $fallbackName),
            'color' => $r['color'] ?? $r['colour'] ?? null,
        ];
    }

    /**
     * Upload a document. Returns the Paperless consumption task id (a UUID).
     *
     * @param  array{title?:?string, created?:?string, correspondent?:?int, document_type?:?int, tags?:list<int>}  $meta
     */
    public function postDocument(string $contents, string $filename, array $meta = []): string
    {
        $request = $this->http()->asMultipart()->attach('document', $contents, $filename);

        $fields = [];
        if (! empty($meta['title'])) {
            $fields['title'] = (string) $meta['title'];
        }
        if (! empty($meta['created'])) {
            $fields['created'] = (string) $meta['created'];
        }
        if (! empty($meta['correspondent'])) {
            $fields['correspondent'] = (int) $meta['correspondent'];
        }
        if (! empty($meta['document_type'])) {
            $fields['document_type'] = (int) $meta['document_type'];
        }
        // Paperless expects tags as a repeated field; the HTTP client encodes a
        // list under the same key as repeated form parts.
        if (! empty($meta['tags'])) {
            $fields['tags'] = array_map('intval', $meta['tags']);
        }

        $res = $request->post('/api/documents/post_document/', $fields);
        if (! $res->successful()) {
            throw new RuntimeException('Upload failed: HTTP '.$res->status().'.');
        }

        // The endpoint returns the task UUID as a JSON string.
        return trim((string) $res->json(), '"');
    }

    private function http(): PendingRequest
    {
        // Re-check the target at request time (defence in depth: the stored URL
        // could have been changed by a path that skipped validation) and never
        // follow redirects, so a validated host cannot 30x-bounce the request
        // to an internal/metadata address.
        if (! OutboundUrl::safe($this->baseUrl)) {
            throw new RuntimeException('The configured Paperless URL is not an allowed outbound target.');
        }

        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->token, 'Token')
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()
            ->timeout(30);
    }
}
