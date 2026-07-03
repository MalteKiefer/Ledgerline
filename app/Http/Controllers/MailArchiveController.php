<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Services\Mail\MailArchiveReader;
use App\Services\Mail\MailSource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The local mail archive: browse messages the sync captured, view a message
 * from its stored .eml, restore a server-deleted one (re-APPEND to the server)
 * and permanently delete an archived copy. Reading/browsing is data-only and
 * fast; restore needs IMAP and message rendering parses MIME — both server-side.
 */
class MailArchiveController extends Controller
{
    private function disk()
    {
        return Storage::disk(config('files.disk'));
    }

    /** Archived (server-deleted) messages for an account — newest first. */
    public function index(Request $request, MailAccount $account): JsonResponse
    {
        $messages = MailMessage::with('folder:id,name,path')
            ->where('mail_account_id', $account->id)
            ->whereNotNull('deleted_on_server_at')
            ->when($request->filled('q'), fn ($qb) => $qb->where(fn ($w) => $w
                ->where('subject', 'like', '%'.$request->string('q').'%')
                ->orWhere('from_email', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('date_at')
            ->limit(500)
            ->get()
            ->map(fn (MailMessage $m) => $this->summary($m));

        return response()->json(['messages' => $messages, 'count' => $messages->count()]);
    }

    /**
     * Full-text search across the whole local archive (all folders, not only
     * server-deleted mail). Matches the free-text term against subject, from,
     * to, cc, body and attachment names, and filters by a date/time range and
     * an attachments-only flag. Note: % and _ in the term act as wildcards.
     */
    public function search(Request $request, MailAccount $account): JsonResponse
    {
        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'has_attachment' => ['sometimes', 'boolean'],
        ]);

        $term = trim((string) ($v['q'] ?? ''));

        $messages = MailMessage::with('folder:id,name,path')
            ->where('mail_account_id', $account->id)
            ->when($term !== '', function ($qb) use ($term): void {
                $like = '%'.$term.'%';
                $qb->where(function ($w) use ($like): void {
                    foreach (['subject', 'from_name', 'from_email', 'to', 'cc', 'preview', 'body_text', 'attachment_names'] as $col) {
                        $w->orWhere($col, 'like', $like);
                    }
                });
            })
            ->when($v['date_from'] ?? null, fn ($qb, $d) => $qb->where('date_at', '>=', Carbon::parse($d)))
            ->when($v['date_to'] ?? null, fn ($qb, $d) => $qb->where('date_at', '<=', Carbon::parse($d)))
            ->when($request->boolean('has_attachment'), fn ($qb) => $qb->where('has_attachments', true))
            ->orderByDesc('date_at')
            ->limit(500)
            ->get()
            ->map(fn (MailMessage $m) => $this->summary($m));

        return response()->json(['messages' => $messages, 'count' => $messages->count()]);
    }

    /** @return array<string,mixed> */
    private function summary(MailMessage $m): array
    {
        return [
            'id' => $m->id,
            'folder' => $m->folder?->name,
            'subject' => $m->subject,
            'from' => trim(($m->from_name ?: '').' <'.($m->from_email ?: '').'>', ' <>'),
            'date' => $m->date_at?->toIso8601String(),
            'preview' => $m->preview,
            'hasAttachments' => $m->has_attachments,
            'deletedAt' => $m->deleted_on_server_at?->toIso8601String(),
        ];
    }

    /** Render one archived message from its stored .eml. */
    public function show(MailMessage $message, MailArchiveReader $reader): JsonResponse
    {
        return response()->json($reader->parse($this->raw($message)));
    }

    public function attachment(MailMessage $message, int $index, MailArchiveReader $reader): Response|JsonResponse
    {
        $a = $reader->attachment($this->raw($message), $index);
        abort_if($a === null, 404);

        return response($a['content'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $a['name'],
                'attachment'
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Re-append the message to its folder on the server, then drop the local copy. */
    public function restore(MailMessage $message, MailSource $source): JsonResponse
    {
        $account = MailAccount::find($message->mail_account_id);
        $folder = $message->folder;
        if (! $account || ! $folder) {
            return response()->json(['ok' => false], 422);
        }

        try {
            $source->appendMessage($account->credentials(), $folder->path, $this->raw($message));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => __('mail.connect_failed')], 422);
        }

        // It is back on the server (with a new UID); drop the local archived copy
        // so the next sync re-adds it cleanly as a live message.
        $this->deleteLocal($message);

        return response()->json(['ok' => true]);
    }

    /** Permanently delete the archived copy (local only). */
    public function destroy(MailMessage $message): JsonResponse
    {
        $this->deleteLocal($message);

        return response()->json(['ok' => true]);
    }

    private function raw(MailMessage $message): string
    {
        $path = 'mail/'.$message->blob;
        $disk = $this->disk();
        abort_unless($disk->exists($path), 404);

        // Refuse to load an oversized .eml into memory (OOM guard): a hostile
        // message with a huge attachment must not be able to exhaust the worker
        // on view/download/restore.
        $max = (int) config('mail_archive.max_render_bytes', 25 * 1024 * 1024);
        abort_if($disk->size($path) > $max, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);

        return (string) $disk->get($path);
    }

    private function deleteLocal(MailMessage $message): void
    {
        $this->disk()->delete('mail/'.$message->blob);
        $message->delete();
    }
}
