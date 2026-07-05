<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\MailMessage;
use App\Models\User;
use App\Support\BlobStore;

/**
 * Mail module contribution to per-user GDPR export and account erasure.
 *
 * All three tables (mail_accounts, mail_folders, mail_messages) carry a
 * denormalised user_id, so every query here is scoped directly by owner.
 * Attachments are not a separate table: each message's raw RFC822 .eml
 * (with its attachments inline) lives on the files disk at mail/{blob};
 * attachment_names is JSON metadata on the message row.
 *
 * None of the mail models use SoftDeletes, so plain delete() is a hard delete.
 */
final class MailData implements UserDataContributor
{
    public function key(): string
    {
        return 'mail';
    }

    public function export(User $user): array
    {
        // Account metadata only: never the encrypted login password.
        $accounts = MailAccount::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'host',
                'port',
                'encryption',
                'validate_cert',
                'username',
                'last_synced_at',
                'created_at',
                'updated_at',
            ])
            ->toArray();

        // Archived message headers only: no raw bodies, preview, or blobs.
        $messages = MailMessage::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get([
                'id',
                'mail_account_id',
                'mail_folder_id',
                'message_id',
                'subject',
                'from_name',
                'from_email',
                'to',
                'cc',
                'date_at',
                'seen',
                'flagged',
                'answered',
                'has_attachments',
                'attachment_names',
                'size',
                'deleted_on_server_at',
                'synced_at',
                'created_at',
                'updated_at',
            ])
            ->toArray();

        return [
            'accounts' => $accounts,
            'messages' => $messages,
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();

        // 1. Delete the raw .eml blobs (which also hold the attachments) from
        //    disk, chunked to bound memory over large archives.
        MailMessage::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereNotNull('blob')
            ->select(['id', 'blob'])
            ->chunkById(500, function ($messages) use ($disk): void {
                foreach ($messages as $message) {
                    $disk->delete('mail/'.$message->blob);
                }
            });

        // 2. Delete message rows, then folder rows, then the accounts. Ordering
        //    keeps things FK-safe even without relying on cascade, and every
        //    step is owner-scoped and idempotent.
        MailMessage::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->delete();

        MailFolder::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->delete();

        MailAccount::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
