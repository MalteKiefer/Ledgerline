<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Services\Mail\MailArchiveReader;
use App\Services\Mail\MailSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
            ->map(fn (MailMessage $m) => [
                'id' => $m->id,
                'folder' => $m->folder?->name,
                'subject' => $m->subject,
                'from' => trim(($m->from_name ?: '').' <'.($m->from_email ?: '').'>', ' <>'),
                'date' => $m->date_at?->toIso8601String(),
                'preview' => $m->preview,
                'hasAttachments' => $m->has_attachments,
                'deletedAt' => $m->deleted_on_server_at?->toIso8601String(),
            ]);

        return response()->json(['messages' => $messages, 'count' => $messages->count()]);
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
            'Content-Disposition' => 'attachment; filename="'.addslashes($a['name']).'"',
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
        abort_unless($this->disk()->exists($path), 404);

        return (string) $this->disk()->get($path);
    }

    private function deleteLocal(MailMessage $message): void
    {
        $this->disk()->delete('mail/'.$message->blob);
        $message->delete();
    }
}
