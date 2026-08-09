<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use App\Support\BlobStore;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, owner-scoped access to the archived-message ledger. `index`
 * returns the denormalised envelope rows the client lists + filters + searches;
 * `show` returns one message's full reader payload (bodies + headers + auth
 * signals). Archived mail is immutable: there is NO edit endpoint here (seen /
 * trash toggles live in MailSeenController / MailTrashController).
 *
 * Full-text search runs server-side over the `search_text` column: PostgreSQL
 * uses the GIN `to_tsvector('simple') @@ plainto_tsquery` index; SQLite falls
 * back to LIKE. All queries are explicitly owner-scoped (`ownedBy`).
 */
class MailMessageController extends Controller
{
    private const MAX_PER_PAGE = 1000;

    /**
     * Paginated, owner-scoped envelope listing.
     *
     * Query params: ?account_id ?folder ?trashed ?seen ?spam ?thread_id
     *   ?q (full-text) ?from ?to (YYYY-MM-DD date range on the message Date)
     *   ?page ?per_page (1..1000, default 50). No account_id = unified inbox.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $perPage = min(max(1, $request->integer('per_page', 50)), self::MAX_PER_PAGE);
        $wantTrashed = $request->boolean('trashed');

        $query = MailMessage::query()
            ->ownedBy($user->id)
            // Trashed = hidden. Excluded by default; ?trashed=1 returns ONLY those.
            ->when($wantTrashed, fn (Builder $q) => $q->whereNotNull('trashed_at'), fn (Builder $q) => $q->whereNull('trashed_at'))
            ->when($request->filled('account_id'), fn (Builder $q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('folder'), fn (Builder $q) => $q->where('folder', $request->string('folder')->value()))
            ->when($request->filled('thread_id'), fn (Builder $q) => $q->where('thread_id', $request->string('thread_id')->value()))
            ->when($request->has('seen'), fn (Builder $q) => $q->where('seen', $request->boolean('seen')))
            ->when($request->has('spam'), fn (Builder $q) => $q->where('spam', $request->boolean('spam')));

        $this->applyDateRange($query, $request);
        $this->applySearch($query, trim($request->string('q')->value()));

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MailMessage $m): array => $this->presentRow($m))->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** One message's full reader payload (owner-scoped; 404 on foreign). */
    public function show(Request $request, MailMessage $message): JsonResponse
    {
        $this->authorizeOwner($request, $message);

        return response()->json(['message' => $this->presentFull($message)]);
    }

    /** @param  EloquentBuilder<MailMessage>  $query */
    private function applyDateRange(EloquentBuilder $query, Request $request): void
    {
        if ($request->filled('from')) {
            $from = $this->parseDate($request->string('from')->value());
            if ($from !== null) {
                $query->where('date', '>=', $from->startOfDay());
            }
        }
        if ($request->filled('to')) {
            $to = $this->parseDate($request->string('to')->value());
            if ($to !== null) {
                $query->where('date', '<=', $to->endOfDay());
            }
        }
    }

    /** @param  EloquentBuilder<MailMessage>  $query */
    private function applySearch(EloquentBuilder $query, string $q): void
    {
        if ($q === '') {
            return;
        }

        $like = '%'.$q.'%';
        if (DB::getDriverName() === 'pgsql') {
            $query->where(function (EloquentBuilder $inner) use ($q, $like): void {
                $inner->whereRaw(
                    "to_tsvector('simple', coalesce(search_text, '')) @@ plainto_tsquery('simple', ?)",
                    [$q]
                )
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('from_email', 'like', $like)
                    ->orWhere('from_name', 'like', $like);
            });

            return;
        }

        $query->where(function (EloquentBuilder $inner) use ($like): void {
            $inner->where('search_text', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('from_email', 'like', $like)
                ->orWhere('from_name', 'like', $like);
        });
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Envelope row for the list. No bodies / no raw headers (kept lean).
     *
     * @return array<string, mixed>
     */
    private function presentRow(MailMessage $m): array
    {
        return [
            'id' => $m->id,
            'account_id' => $m->account_id,
            'folder' => $m->folder,
            'message_id' => $m->message_id,
            'thread_id' => $m->thread_id,
            'subject' => $m->subject,
            'from_name' => $m->from_name,
            'from_email' => $m->from_email,
            'to' => $m->to_json ?? [],
            'cc' => $m->cc_json ?? [],
            'date' => $m->date?->toIso8601String(),
            'size' => $m->size,
            'has_attachment' => $m->has_attachment,
            'attachment_count' => $m->attachment_count,
            'seen' => $m->seen,
            'trashed' => $m->trashed_at !== null,
            'spam' => $m->spam,
            'spf' => $m->spf,
            'dkim' => $m->dkim,
            'dmarc' => $m->dmarc,
            'encrypted_type' => $m->encrypted_type,
            'decrypt_status' => $m->decrypt_status,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * Full reader payload.
     *
     * @return array<string, mixed>
     */
    private function presentFull(MailMessage $m): array
    {
        return array_merge($this->presentRow($m), [
            'in_reply_to' => $m->in_reply_to,
            'references' => $m->references,
            'reply_to' => $m->reply_to,
            'text_body' => $m->text_body,
            'html' => $m->html_sanitized,
            'headers_raw' => $this->rawHeaders($m),
            // Attachment listing is Phase 2; the shape is stable so the client
            // can render an empty list today.
            'attachments' => [],
        ]);
    }

    /**
     * The raw RFC822 header block, read on demand from the immutable .eml blob
     * (mail/{id}) — everything before the first blank line. Never stored in a
     * column; capped so a pathological header block can't bloat the response.
     */
    private function rawHeaders(MailMessage $m): ?string
    {
        $disk = BlobStore::disk();
        $key = 'mail/'.$m->id;
        if (! $disk->exists($key)) {
            return null;
        }

        $raw = $disk->get($key);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $norm = str_replace("\r\n", "\n", $raw);
        $sep = strpos($norm, "\n\n");
        $block = $sep === false ? $norm : substr($norm, 0, $sep);

        return mb_substr($block, 0, 16_384);
    }

    private function authorizeOwner(Request $request, MailMessage $message): void
    {
        abort_if((int) $message->user_id !== (int) $this->requireUser($request)->id, 404);
    }
}
