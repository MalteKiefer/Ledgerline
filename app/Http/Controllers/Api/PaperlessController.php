<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaperlessTerm;
use App\Models\UserSetting;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Mobile API mirror of the web Paperless transfer endpoints.
 *
 * Each user connects their own Paperless-ngx instance (URL + API token stored on
 * their user_settings row). These endpoints expose three user-facing operations:
 *
 *   GET  /paperless/terms      — cached quick-pick lists (configured flag + terms)
 *   POST /paperless/terms      — create a tag / document_type / correspondent live
 *   POST /paperless/documents  — push a decrypted document to the user's Paperless
 *   POST /paperless/sync       — refresh the cached term list from live Paperless
 *
 * The submit endpoint is a transient-cleartext boundary: the client uploads the
 * already-decrypted document bytes, which are forwarded directly to the user's
 * own Paperless instance and are NOT persisted or logged by this server — the
 * same accepted ZK boundary as /invoices/ocr and /gallery/process. The Cache-
 * Control: no-store header is set on the response.
 *
 * All endpoints are Sanctum device-token gated (abilities:device) and
 * owner-scoped through PaperlessClient::forUser + PaperlessTerm's OwnsUserData
 * global scope.
 */
class PaperlessController extends Controller
{
    /** 50 MiB cap — matches the web submit endpoint's `max:51200` rule. */
    private const MAX_BYTES = 50 * 1024 * 1024;

    /**
     * Return the cached term lists and a configured flag.
     *
     * GET /api/v1/paperless/terms
     * Throttle: 60/min
     */
    public function terms(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $settings = UserSetting::for($user->id);
        $enabled = (bool) $settings->paperless_enabled
            && PaperlessClient::fromUserSetting($settings) !== null;

        // Auto-scoped to the current user by OwnsUserData global scope.
        $grouped = PaperlessTerm::orderBy('name')
            ->get(['kind', 'paperless_id', 'name', 'color'])
            ->groupBy('kind');

        $map = fn (string $kind) => ($grouped[$kind] ?? collect())
            ->map(fn (mixed $t) => $t instanceof PaperlessTerm
                ? ['id' => $t->paperless_id, 'name' => $t->name, 'color' => $t->color]
                : null)
            ->filter()
            ->values();

        return response()->json([
            'configured' => $enabled,
            'tags' => $map('tag'),
            'document_types' => $map('document_type'),
            'correspondents' => $map('correspondent'),
        ]);
    }

    /**
     * Create a new tag, document type, or correspondent in the live Paperless
     * instance and cache the result locally for quick-pick use.
     *
     * POST /api/v1/paperless/terms
     * Body: { kind: tag|document_type|correspondent, name: string }
     * Throttle: 30/min
     */
    public function createTerm(Request $request): JsonResponse
    {
        $request->validate([
            'kind' => ['required', Rule::in(PaperlessTerm::KINDS)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $kind = $request->string('kind')->value();
        $name = $request->string('name')->value();

        $user = $this->requireUser($request);
        $client = PaperlessClient::forUser($user->id);
        if ($client === null) {
            return response()->json(['ok' => false, 'detail' => 'Paperless is not configured.'], 422);
        }

        try {
            $term = $client->create($kind, $name);
        } catch (\Throwable $e) {
            Log::warning('Paperless createTerm failed (api)', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'detail' => __('paperless.request_failed')], 422);
        }

        PaperlessTerm::updateOrCreate(
            ['user_id' => $user->id, 'kind' => $kind, 'paperless_id' => $term['paperless_id']],
            ['name' => $term['name'], 'color' => $term['color']],
        );

        return response()->json(['ok' => true, 'id' => $term['paperless_id'], 'name' => $term['name']]);
    }

    /**
     * Forward a decrypted document to the user's Paperless instance.
     *
     * Transient-cleartext boundary: the client POSTs the raw (already-decrypted)
     * document bytes. This server forwards them to the user's own Paperless
     * instance and stores/logs nothing — same accepted ZK window as
     * /gallery/process and /invoices/ocr. The response carries Cache-Control:
     * no-store so proxies/clients discard the bytes immediately.
     *
     * POST /api/v1/paperless/documents
     * Body (multipart): file (required, ≤50 MB), title?, created?(date),
     *                   correspondent?(int), document_type?(int), tags[]?(int[])
     * Throttle: 20/min
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:255'],
            'created' => ['nullable', 'date'],
            'correspondent' => ['nullable', 'integer'],
            'document_type' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer'],
        ]);

        /** @var UploadedFile $upload */
        $upload = $request->file('file');

        // Size gate before any forwarding work (returns 413 rather than 422).
        abort_if(($upload->getSize() ?: 0) > self::MAX_BYTES, 413, 'File too large (max 50 MB).');

        $user = $this->requireUser($request);
        $client = PaperlessClient::forUser($user->id);
        if ($client === null) {
            return $this->noStore(
                response()->json(['ok' => false, 'detail' => 'Paperless is not configured.'], 422)
            );
        }

        $title = $request->input('title');
        $created = $request->input('created');
        $correspondent = $request->input('correspondent');
        $documentType = $request->input('document_type');
        $tags = array_values(array_map(
            fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            $request->collect('tags')->all(),
        ));

        try {
            $task = $client->postDocument(
                (string) file_get_contents($upload->getRealPath()),
                $upload->getClientOriginalName() ?: 'document.pdf',
                [
                    'title' => is_string($title) ? $title : null,
                    'created' => is_string($created) ? $created : null,
                    'correspondent' => is_numeric($correspondent) ? (int) $correspondent : null,
                    'document_type' => is_numeric($documentType) ? (int) $documentType : null,
                    'tags' => $tags,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Paperless submit failed (api)', ['error' => $e->getMessage()]);

            return $this->noStore(
                response()->json(['ok' => false, 'detail' => __('paperless.request_failed')], 422)
            );
        }

        return $this->noStore(response()->json(['ok' => true, 'task' => $task]));
    }

    /**
     * Refresh the current user's cached Paperless terms from the live instance.
     *
     * POST /api/v1/paperless/sync
     * Throttle: 20/min
     */
    public function sync(Request $request, PaperlessSync $sync): JsonResponse
    {
        $user = $this->requireUser($request);

        try {
            $counts = $sync->run($user->id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'counts' => $counts]);
    }

    /** Prevent proxies and clients from caching the transient plaintext response. */
    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
