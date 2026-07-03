<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\PaperlessTerm;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * App-facing Paperless endpoints used by the transfer modal: the cached
 * quick-pick terms, creating a new term, and uploading a document. Backed by
 * the credentials stored (encrypted) in settings; nothing is persisted here.
 */
class PaperlessController extends Controller
{
    /** Cached tags / document types / correspondents for the modal's pickers. */
    public function terms(): JsonResponse
    {
        $settings = AppSettings::current();
        $enabled = (bool) $settings->paperless_enabled && PaperlessClient::fromSettings($settings) !== null;

        $grouped = PaperlessTerm::orderBy('name')->get(['kind', 'paperless_id', 'name', 'color'])
            ->groupBy('kind');

        $map = fn (string $kind) => ($grouped[$kind] ?? collect())
            ->map(fn ($t) => ['id' => $t->paperless_id, 'name' => $t->name, 'color' => $t->color])
            ->values();

        return response()->json([
            'configured' => $enabled,
            'tags' => $map('tag'),
            'document_types' => $map('document_type'),
            'correspondents' => $map('correspondent'),
        ]);
    }

    /** Create a tag / document type / correspondent in Paperless and cache it. */
    public function createTerm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(PaperlessTerm::KINDS)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $client = PaperlessClient::fromSettings();
        if ($client === null) {
            return response()->json(['ok' => false, 'detail' => 'Paperless is not configured.'], 422);
        }

        try {
            $term = $client->create($data['kind'], $data['name']);
        } catch (\Throwable $e) {
            Log::warning('Paperless createTerm failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'detail' => __('paperless.request_failed')], 422);
        }

        PaperlessTerm::updateOrCreate(
            ['kind' => $data['kind'], 'paperless_id' => $term['paperless_id']],
            ['name' => $term['name'], 'color' => $term['color']],
        );

        return response()->json(['ok' => true, 'id' => $term['paperless_id'], 'name' => $term['name']]);
    }

    /** Upload a document (from a mail attachment or a vault file) to Paperless. */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50 MB
            'title' => ['nullable', 'string', 'max:255'],
            'created' => ['nullable', 'date'],
            'correspondent' => ['nullable', 'integer'],
            'document_type' => ['nullable', 'integer'],
            'tags' => ['array'],
            'tags.*' => ['integer'],
        ]);

        $client = PaperlessClient::fromSettings();
        if ($client === null) {
            return response()->json(['ok' => false, 'detail' => 'Paperless is not configured.'], 422);
        }

        $file = $request->file('file');
        try {
            $task = $client->postDocument(
                (string) file_get_contents($file->getRealPath()),
                $file->getClientOriginalName() ?: 'document.pdf',
                [
                    'title' => $data['title'] ?? null,
                    'created' => $data['created'] ?? null,
                    'correspondent' => $data['correspondent'] ?? null,
                    'document_type' => $data['document_type'] ?? null,
                    'tags' => $data['tags'] ?? [],
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Paperless submit failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'detail' => __('paperless.request_failed')], 422);
        }

        return response()->json(['ok' => true, 'task' => $task]);
    }
}
