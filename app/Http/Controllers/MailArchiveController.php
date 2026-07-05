<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Services\Mail\MailArchiver;
use App\Services\Mail\MailArchiveReader;
use App\Services\Mail\MailSource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * an attachments-only flag. LIKE metacharacters in the term are escaped so
     * they match literally rather than acting as wildcards.
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
                // Escape LIKE metacharacters (\ % _) so the term matches literally
                // instead of acting as a wildcard, and bind it with an explicit
                // ESCAPE clause for driver-portable behaviour.
                $like = '%'.$this->likeEscape($term).'%';
                $qb->where(function ($w) use ($like): void {
                    foreach (['subject', 'from_name', 'from_email', 'to', 'cc', 'preview', 'body_text', 'attachment_names'] as $col) {
                        // Wrap the identifier: `to`/`cc` are SQL reserved words.
                        $w->orWhereRaw($w->getGrammar()->wrap($col)." LIKE ? ESCAPE '\\'", [$like]);
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
            'uid' => $m->uid,
            'folder' => $m->folder?->name,
            'folderPath' => $m->folder?->path,
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

    /** Download a single archived message as an .eml file. */
    public function download(MailMessage $message): StreamedResponse
    {
        $eml = $this->raw($message);
        $name = $this->emlName($message->subject, (string) $message->id);

        return response()->streamDownload(fn () => print ($eml), $name, [
            'Content-Type' => 'message/rfc822',
        ]);
    }

    /**
     * Download the selected messages (by folder + UID) that exist in the local
     * archive as a zip of .eml files. Non-archived selections are skipped.
     */
    public function downloadMany(Request $request, MailAccount $account): StreamedResponse
    {
        $v = $request->validate([
            'folder' => ['required', 'string', 'max:255'],
            'uids' => ['required', 'array', 'max:1000'],
            'uids.*' => ['integer'],
        ]);

        // Owner scope is applied by the model's global scope; pin to the account.
        $messages = MailMessage::where('mail_account_id', $account->id)
            ->whereIn('uid', $v['uids'])
            ->whereHas('folder', fn ($q) => $q->where('path', $v['folder']))
            ->get();
        abort_if($messages->isEmpty(), 404);

        return $this->zipMessages($messages, 'mail-selection-'.now()->format('Ymd-His').'.zip');
    }

    /** Download an account's entire local archive as a zip of .eml files. */
    public function downloadArchive(MailAccount $account): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'llmail');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $disk = $this->disk();
        $used = [];
        $temps = [];
        MailMessage::withoutGlobalScopes()->where('mail_account_id', $account->id)
            ->whereNotNull('blob')->orderBy('id')->chunkById(200, function ($chunk) use ($zip, $disk, &$used, &$temps): void {
                foreach ($chunk as $m) {
                    $this->addBlobToZip($zip, $disk, $m, $used, $temps);
                }
            });
        $zip->close();
        $this->cleanupTemps($temps);

        return response()->streamDownload(function () use ($tmp): void {
            readfile($tmp);
            @unlink($tmp);
        }, 'mail-archive-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip']);
    }

    private function zipMessages($messages, string $filename): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'llmail');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $disk = $this->disk();
        $used = [];
        $temps = [];
        foreach ($messages as $m) {
            $this->addBlobToZip($zip, $disk, $m, $used, $temps);
        }
        $zip->close();
        $this->cleanupTemps($temps);

        return response()->streamDownload(function () use ($tmp): void {
            readfile($tmp);
            @unlink($tmp);
        }, $filename, ['Content-Type' => 'application/zip']);
    }

    /**
     * Add one message's stored .eml to the zip by streaming its blob to a temp
     * file and referencing that file (addFile), so a huge .eml is never held in
     * memory. Oversized messages are skipped; written temp paths are collected
     * in $temps for cleanup after $zip->close().
     *
     * @param  array<string,bool>  $used
     * @param  list<string>  $temps
     */
    private function addBlobToZip(\ZipArchive $zip, $disk, MailMessage $m, array &$used, array &$temps): void
    {
        if (! is_string($m->blob) || $m->blob === '') {
            return;
        }
        $path = 'mail/'.$m->blob;
        if (! $disk->exists($path)) {
            return;
        }
        // Skip a hostile/huge single message so it can't exhaust the worker.
        $max = (int) config('mail_archive.max_render_bytes', 25 * 1024 * 1024);
        if ($disk->size($path) > $max) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'lleml');
        $in = $disk->readStream($path);
        if ($in === null) {
            @unlink($tmp);

            return;
        }
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        if (is_resource($in)) {
            fclose($in);
        }
        $temps[] = $tmp;
        $zip->addFile($tmp, $this->uniqueName($this->emlName($m->subject, (string) $m->id), $used));
    }

    /** @param  list<string>  $temps */
    private function cleanupTemps(array $temps): void
    {
        foreach ($temps as $tmp) {
            @unlink($tmp);
        }
    }

    private function emlName(?string $subject, string $id): string
    {
        $base = trim(preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', (string) $subject) ?? '');
        $base = mb_substr($base !== '' ? $base : 'message', 0, 80);

        return $base.'-'.substr($id, 0, 8).'.eml';
    }

    private function uniqueName(string $name, array &$used): string
    {
        $candidate = $name;
        $i = 1;
        while (isset($used[$candidate])) {
            $candidate = preg_replace('/\.eml$/', '', $name).'-'.(++$i).'.eml';
        }
        $used[$candidate] = true;

        return $candidate;
    }

    /** Escape LIKE metacharacters (\ % _) so a search term matches literally. */
    private function likeEscape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * Fast reader path: if the (account, folder, uid) message is already in the
     * local archive, return it rendered from the stored .eml (no server round
     * trip). Returns {found:false} otherwise so the reader can fall back to live
     * IMAP. archiveId lets the client download attachments from the archive.
     */
    public function cached(Request $request, MailAccount $account, MailArchiveReader $reader): JsonResponse
    {
        $v = $request->validate([
            'folder' => ['required', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
        ]);

        $message = MailMessage::where('mail_account_id', $account->id)
            ->where('uid', $v['uid'])
            ->whereHas('folder', fn ($q) => $q->where('path', $v['folder']))
            ->latest('id')
            ->first();

        if ($message === null) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'message' => array_merge($reader->parse($this->raw($message)), [
                'uid' => $message->uid,
                'seen' => $message->seen,
                'archived' => true,
                'archiveId' => $message->id,
            ]),
        ]);
    }

    /**
     * Opportunistically archive a message the reader just fetched live (so a
     * read mailbox fills the archive without waiting for the hourly sync).
     * Fire-and-forget from the client; never fails the read.
     */
    public function archiveOne(Request $request, MailAccount $account, MailArchiver $archiver): JsonResponse
    {
        $v = $request->validate([
            'folder' => ['required', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
            'uidvalidity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $archived = $archiver->archiveMessage($account, $v['folder'], (int) $v['uid'], (int) $v['uidvalidity']);
        } catch (\Throwable) {
            $archived = false;
        }

        return response()->json(['archived' => $archived]);
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
