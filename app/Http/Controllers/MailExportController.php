<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use App\Support\BlobStore;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export a selection of archived messages as an .mbox stream or a ZIP of .eml
 * files. Owner-scoped; the selection is ids[] OR a folder OR a label. The bytes
 * come from each message's immutable raw .eml blob.
 */
class MailExportController extends Controller
{
    private const MAX_MESSAGES = 5000;

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'format' => ['required', Rule::in(['mbox', 'zip'])],
            'ids' => ['array', 'max:'.self::MAX_MESSAGES],
            'ids.*' => ['string'],
            'folder' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'integer'],
        ]);

        $ids = $this->resolveIds($request, $uid);
        abort_if($ids === [], 422);

        return $request->string('format')->value() === 'zip'
            ? $this->zip($ids, $uid)
            : $this->mbox($ids);
    }

    /**
     * @param  list<string>  $ids
     */
    private function mbox(array $ids): StreamedResponse
    {
        $disk = BlobStore::disk();
        $filename = 'mail-'.now()->format('Ymd-His').'.mbox';

        return response()->streamDownload(function () use ($ids, $disk): void {
            foreach ($ids as $id) {
                $raw = $disk->get('mail/'.$id);
                if (! is_string($raw) || $raw === '') {
                    continue;
                }
                // mboxrd: a "From " separator line, and every body line already
                // starting with (>*)From gets an extra '>' so it can't be
                // mistaken for a separator on re-import.
                $norm = str_replace("\r\n", "\n", $raw);
                $escaped = (string) preg_replace('/^(>*From )/m', '>$1', $norm);
                echo 'From MAILER-DAEMON '.now()->format('D M d H:i:s Y')."\n";
                echo $escaped;
                echo "\n\n";
            }
        }, $filename, [
            'Content-Type' => 'application/mbox',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  list<string>  $ids
     */
    private function zip(array $ids, int $uid): BinaryFileResponse
    {
        $disk = BlobStore::disk();
        $tmp = tempnam(sys_get_temp_dir(), 'llmbox');
        abort_if($tmp === false, 500);

        $zip = new \ZipArchive;
        abort_if($zip->open($tmp, \ZipArchive::OVERWRITE) !== true, 500);

        $seen = [];
        // Re-pin the owner scope on the re-query (defense-in-depth; $ids is already
        // owner-scoped via resolveIds()).
        foreach (MailMessage::query()->ownedBy($uid)->whereIn('id', $ids)->get() as $message) {
            $raw = $disk->get('mail/'.$message->id);
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $name = $this->uniqueName($this->safeName($message->subject).'.eml', $seen);
            $zip->addFromString($name, $raw);
        }
        $zip->close();

        return response()->download($tmp, 'mail-'.now()->format('Ymd-His').'.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Resolve the owner-scoped ordered message-id set from ids[] | folder |
     * label (in that precedence).
     *
     * @return list<string>
     */
    private function resolveIds(Request $request, int $uid): array
    {
        $query = MailMessage::query()->ownedBy($uid)->orderByDesc('created_at');

        if ($request->filled('ids')) {
            $query->whereIn('id', (array) $request->input('ids'));
        } elseif ($request->filled('label')) {
            $labelId = $request->integer('label');
            $query->whereHas('labels', fn (Builder $q) => $q->where('mail_labels.id', $labelId)->where('mail_labels.user_id', $uid));
        } elseif ($request->filled('folder')) {
            $query->where('folder', $request->string('folder')->value());
        } else {
            return [];
        }

        $ids = $query->limit(self::MAX_MESSAGES)->pluck('id')->all();

        return array_values(array_map(
            static fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
            $ids,
        ));
    }

    /**
     * @param  array<string, bool>  $seen
     */
    private function uniqueName(string $name, array &$seen): string
    {
        $candidate = $name;
        $i = 1;
        while (isset($seen[$candidate])) {
            $candidate = preg_replace('/(\.eml)$/', " ({$i})$1", $name) ?? $name.' ('.$i.')';
            $i++;
        }
        $seen[$candidate] = true;

        return $candidate;
    }

    private function safeName(?string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', (string) $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'message' : mb_substr($clean, 0, 120);
    }
}
