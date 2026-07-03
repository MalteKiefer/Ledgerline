<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Read-only source of mail for the archiver: folders, the current UID set per
 * folder (with flags) and a single message's raw bytes + metadata. Implemented
 * against IMAP; faked in tests so the archive/deletion logic can be verified
 * without a live server.
 */
interface MailSource
{
    /**
     * @return list<array{path:string, name:string, delimiter:string, role:?string, uidvalidity:int}>
     */
    public function folders(ImapCredentials $c): array;

    /**
     * Current UIDs in a folder mapped to their flags.
     *
     * @return array<int, array{seen:bool, flagged:bool, answered:bool}>
     */
    public function uids(ImapCredentials $c, string $folder): array;

    /**
     * One message's raw RFC822 bytes plus parsed metadata.
     *
     * @return array{raw:string, message_id:?string, subject:?string, from_name:?string,
     *     from_email:?string, to:list<array{name:?string,email:string}>, date:?string,
     *     has_attachments:bool, size:int, preview:?string}
     */
    public function fetch(ImapCredentials $c, string $folder, int $uid): array;
}
