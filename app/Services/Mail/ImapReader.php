<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Read and act on IMAP messages with transient credentials.
 *
 * Every call opens a connection, performs one bundled operation and
 * disconnects. Nothing is persisted or logged — the server sees message
 * content only in memory for the duration of the request.
 */
interface ImapReader
{
    /**
     * List a page of message envelopes (newest first), without bodies.
     *
     * @return array{total:int, page:int, perPage:int, messages:list<array{
     *     uid:int, subject:string, from:array{name:string,email:string}|null,
     *     date:string|null, seen:bool, flagged:bool, answered:bool,
     *     hasAttachments:bool}>}
     */
    public function listMessages(ImapCredentials $c, string $folder, int $page, int $perPage): array;

    /**
     * Fetch one full message. Marks it \Seen when $markSeen is true.
     *
     * @return array{uid:int, subject:string, from:array{name:string,email:string}|null,
     *     to:list<array{name:string,email:string}>, cc:list<array{name:string,email:string}>,
     *     date:string|null, seen:bool, html:string|null, text:string|null,
     *     attachments:list<array{id:int,name:string,mime:string,size:int}>}
     */
    public function getMessage(ImapCredentials $c, string $folder, int $uid, bool $markSeen): array;

    /**
     * Fetch one attachment's bytes for download.
     *
     * @return array{name:string, mime:string, content:string}
     */
    public function getAttachment(ImapCredentials $c, string $folder, int $uid, int $attachmentId): array;

    /**
     * Delete a message: move to Trash, or expunge permanently.
     *
     * @return array{deleted:bool, trashed:bool}
     */
    public function deleteMessage(ImapCredentials $c, string $folder, int $uid, bool $permanent): array;

    /** Move a message to another folder in the same account. */
    public function moveMessage(ImapCredentials $c, string $folder, int $uid, string $targetFolder): void;

    /** Set or clear the \Seen flag. */
    public function flagMessage(ImapCredentials $c, string $folder, int $uid, bool $seen): void;

    /**
     * Copy a message to another account (append its raw source there), then
     * remove it from the source account.
     */
    public function transferMessage(ImapCredentials $source, string $folder, int $uid, ImapCredentials $target, string $targetFolder): void;
}
