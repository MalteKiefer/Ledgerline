<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only, owner-scoped listing of the archived-message LEDGER — never
 * content. Each row is only what the server itself ever stores: an id, which
 * account/folder it came from, its plaintext byte size, when it was archived,
 * and the sealed per-message key (`sealed_key`, a Store v3 §6.3 PQ-hybrid
 * envelope wrapped to the caller's own identity keys).
 *
 * This is the client's list source for the archive: for each row, the client
 * unwraps `sealed_key` with its identity SECRET keys (hybridUnwrap — the same
 * primitive used for cross-user vault sharing) to recover the per-message
 * symmetric key, then fetches `GET /mail/raw/{id}` (MailBlobController — the
 * message's own `id` doubles as the sealed blob's primary key, see
 * MaildirIngestor::ingestFile, so no extra lookup is needed) and opens it
 * with libsodium secretstream. There is deliberately NO server-decrypt
 * endpoint: the server sealed every message to the user's PUBLIC key and
 * cannot read it back.
 */
class MailMessageController extends Controller
{
    private const MAX_PER_PAGE = 200;

    /**
     * Paginated, owner-scoped ledger listing.
     *
     * Query params:
     *   ?account_id= – restrict to one of the caller's own accounts
     *   ?folder=     – exact folder match (e.g. "INBOX")
     *   ?page=       – page number (default 1)
     *   ?per_page=   – rows per page, clamped 1..200 (default 50)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $perPage = min(max(1, $request->integer('per_page', 50)), self::MAX_PER_PAGE);

        $query = MailMessage::query()
            ->ownedBy($user->id)
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('folder'), fn ($q) => $q->where('folder', $request->string('folder')->value()))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MailMessage $m): array => $this->present($m))->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(MailMessage $message): array
    {
        return [
            'id' => $message->id,
            'account_id' => $message->account_id,
            'folder' => $message->folder,
            'size' => $message->size,
            'created_at' => $message->created_at?->toIso8601String(),
            'sealed_key' => $message->sealed_key,
        ];
    }
}
