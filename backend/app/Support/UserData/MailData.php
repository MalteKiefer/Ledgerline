<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\MailBlob;
use App\Models\MailLabel;
use App\Models\MailMessage;
use App\Models\MailRule;
use App\Models\MailSavedSearch;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Support\Str;

/**
 * Per-user data contributor for the mail archive's raw message blobs
 * (mail/{blob}). The message METADATA (mail_accounts/mail_sync_state/
 * mail_messages/mail_logs) already cascades on `users.id` via FK, so
 * PurgeUserAccount's final `$user->delete()` removes those rows on its own.
 *
 * What does NOT cascade is the raw .eml blob BYTES on disk plus their ownership
 * ledger row (mail_blobs). This contributor promptly frees them on account
 * erasure and lists them in the GDPR export, exactly like every other
 * blob-backed module — without it, export omits the blob inventory and erasure
 * leaves bytes on disk until the next mail:sweep-orphans grace window.
 */
final class MailData implements UserDataContributor
{
    public function key(): string
    {
        return 'mail';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $uid = $user->getKey();

        $blobs = MailBlob::query()
            ->where('user_id', $uid)
            ->orderBy('blob')
            ->get(['blob', 'kind', 'size', 'created_at'])
            ->map(fn (MailBlob $b): array => [
                'blob' => $b->blob,
                'kind' => $b->kind,
                'size' => $b->size,
                'created_at' => $b->created_at,
            ])
            ->all();

        // Denormalised message metadata (subject/from/to/date/folder). The raw
        // .eml body stays represented by the blob inventory above; here we give
        // the data subject the searchable headers of every archived message.
        $messages = MailMessage::query()
            ->where('user_id', $uid)
            ->orderBy('id')
            ->get([
                'id', 'account_id', 'folder', 'thread_id', 'message_id',
                'subject', 'from_name', 'from_email', 'to_json', 'cc_json',
                'date', 'has_attachment', 'attachment_count', 'seen', 'trashed_at', 'created_at',
            ])
            ->toArray();

        $labels = MailLabel::query()
            ->where('user_id', $uid)
            ->orderBy('id')
            ->get(['id', 'name', 'color', 'created_at'])
            ->toArray();

        $rules = MailRule::query()
            ->where('user_id', $uid)
            ->orderBy('id')
            ->get(['id', 'name', 'enabled', 'priority', 'match_json', 'action_json', 'created_at'])
            ->toArray();

        $savedSearches = MailSavedSearch::query()
            ->where('user_id', $uid)
            ->orderBy('id')
            ->get(['id', 'name', 'filters_json', 'created_at'])
            ->toArray();

        return [
            'blobs' => $blobs,
            'messages' => $messages,
            'labels' => $labels,
            'rules' => $rules,
            'saved_searches' => $savedSearches,
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        MailBlob::query()
            ->where('user_id', $user->getKey())
            ->orderBy('blob')
            ->chunkById(500, function ($blobs) use ($disk): void {
                foreach ($blobs as $blob) {
                    if (is_string($blob->blob) && Str::isUuid($blob->blob)) {
                        $disk->delete(($blob->kind === 'attachment' ? 'mail/att/' : 'mail/').$blob->blob);
                    }
                }

                MailBlob::query()
                    ->whereIn('blob', $blobs->modelKeys())
                    ->delete();
            }, 'blob');
    }
}
