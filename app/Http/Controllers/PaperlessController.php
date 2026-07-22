<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaperlessTerm;
use App\Models\UserSetting;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * App-facing Paperless endpoints used by the transfer modal: the cached
 * quick-pick terms, creating a new term, and uploading a document. Backed by
 * the current user's own Paperless credentials; nothing is persisted here.
 */
class PaperlessController extends Controller
{
    /** Cached tags / document types / correspondents for the modal's pickers. */
    public function terms(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $settings = UserSetting::for($user->id);
        $enabled = (bool) $settings->paperless_enabled && PaperlessClient::fromUserSetting($settings) !== null;

        // Auto-scoped to the current user by OwnsUserData.
        $grouped = PaperlessTerm::orderBy('name')->get(['kind', 'paperless_id', 'name', 'color'])
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

    /** Create a tag / document type / correspondent in Paperless and cache it. */
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
            Log::warning('Paperless createTerm failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'detail' => __('paperless.request_failed')], 422);
        }

        PaperlessTerm::updateOrCreate(
            ['user_id' => $user->id, 'kind' => $kind, 'paperless_id' => $term['paperless_id']],
            ['name' => $term['name'], 'color' => $term['color']],
        );

        return response()->json(['ok' => true, 'id' => $term['paperless_id'], 'name' => $term['name']]);
    }

    /** Upload a document (from a mail attachment or a stored file) to Paperless. */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50 MB
            'title' => ['nullable', 'string', 'max:255'],
            'created' => ['nullable', 'date'],
            'correspondent' => ['nullable', 'integer'],
            'document_type' => ['nullable', 'integer'],
            'tags' => ['array'],
            'tags.*' => ['integer'],
        ]);

        $user = $this->requireUser($request);
        $client = PaperlessClient::forUser($user->id);
        if ($client === null) {
            return response()->json(['ok' => false, 'detail' => 'Paperless is not configured.'], 422);
        }

        $title = $request->input('title');
        $created = $request->input('created');
        $correspondent = $request->input('correspondent');
        $documentType = $request->input('document_type');
        $tags = array_values(array_map(
            fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            $request->collect('tags')->all(),
        ));

        $file = $request->file('file');
        try {
            $task = $client->postDocument(
                (string) file_get_contents($file->getRealPath()),
                $file->getClientOriginalName() ?: 'document.pdf',
                [
                    'title' => is_string($title) ? $title : null,
                    'created' => is_string($created) ? $created : null,
                    'correspondent' => is_numeric($correspondent) ? (int) $correspondent : null,
                    'document_type' => is_numeric($documentType) ? (int) $documentType : null,
                    'tags' => $tags,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Paperless submit failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'detail' => __('paperless.request_failed')], 422);
        }

        return response()->json(['ok' => true, 'task' => $task]);
    }
}
