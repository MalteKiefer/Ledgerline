<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Read IMAP messages and act on them (delete, move, flag, transfer).
 *
 * The account is referenced by id; the (encrypted) credentials are loaded
 * server-side and never travel to the browser. The server connects, performs
 * one operation and forgets; TLS is required.
 */
class MailReaderController extends Controller
{
    /**
     * Validation for an IMAP folder/mailbox name. Rejects CR, LF and NUL:
     * these could otherwise smuggle a second IMAP command past the client
     * library's quoting into the command stream (IMAP command injection).
     */
    private const FOLDER_RULES = ['required', 'string', 'max:255', 'regex:/\A[^\x00\r\n]+\z/'];

    /** Load an account's IMAP credentials by request field (default account_id). */
    private function creds(Request $request, string $field = 'account_id'): ImapCredentials
    {
        return MailAccount::findOrFail($request->integer($field))->credentials();
    }

    public function folders(Request $request, ImapReader $reader): JsonResponse
    {
        $request->validate(['account_id' => ['required', 'exists:mail_accounts,id']]);

        return $this->guard(fn () => response()->json(['folders' => $reader->listFolders($this->creds($request))]));
    }

    public function createFolder(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate(['account_id' => ['required', 'exists:mail_accounts,id'], 'folder' => self::FOLDER_RULES]);

        return $this->guard(function () use ($reader, $request, $v) {
            $reader->createFolder($this->creds($request), $v['folder']);

            return response()->json(['created' => true]);
        });
    }

    public function emptyFolder(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate(['account_id' => ['required', 'exists:mail_accounts,id'], 'folder' => self::FOLDER_RULES]);

        return $this->guard(function () use ($reader, $request, $v) {
            $reader->emptyFolder($this->creds($request), $v['folder']);

            return response()->json(['emptied' => true]);
        });
    }

    public function messages(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate([
            'account_id' => ['required', 'exists:mail_accounts,id'],
            'folder' => self::FOLDER_RULES,
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->guard(fn () => response()->json(
            $reader->listMessages($this->creds($request), $v['folder'], (int) ($v['page'] ?? 1), 50)
        ));
    }

    public function message(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate([
            'account_id' => ['required', 'exists:mail_accounts,id'],
            'folder' => self::FOLDER_RULES,
            'uid' => ['required', 'integer', 'min:1'],
            'mark_seen' => ['sometimes', 'boolean'],
        ]);

        return $this->guard(fn () => response()->json(
            $reader->getMessage($this->creds($request), $v['folder'], (int) $v['uid'], (bool) ($v['mark_seen'] ?? true))
        ));
    }

    public function attachment(Request $request, ImapReader $reader): Response|JsonResponse
    {
        $v = $request->validate([
            'account_id' => ['required', 'exists:mail_accounts,id'],
            'folder' => self::FOLDER_RULES,
            'uid' => ['required', 'integer', 'min:1'],
            'attachment' => ['required', 'integer', 'min:0'],
        ]);

        return $this->guard(function () use ($reader, $request, $v) {
            $a = $reader->getAttachment($this->creds($request), $v['folder'], (int) $v['uid'], (int) $v['attachment']);

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
        });
    }

    public function action(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate([
            'account_id' => ['required', 'exists:mail_accounts,id'],
            'folder' => self::FOLDER_RULES,
            'uids' => ['required', 'array', 'min:1', 'max:500'],
            'uids.*' => ['integer', 'min:1'],
            'action' => ['required', Rule::in(['trash', 'delete', 'move', 'seen', 'unseen'])],
            'target' => ['required_if:action,move', 'nullable', 'string', 'max:255', 'regex:/\A[^\x00\r\n]+\z/'],
        ]);

        return $this->guard(function () use ($reader, $request, $v) {
            $uids = array_map('intval', $v['uids']);

            return response()->json(
                $reader->actOnMessages($this->creds($request), $v['folder'], $uids, $v['action'], $v['target'] ?? null),
            );
        });
    }

    public function transfer(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate([
            'account_id' => ['required', 'exists:mail_accounts,id'],
            'target_account_id' => ['required', 'exists:mail_accounts,id'],
            'folder' => self::FOLDER_RULES,
            'uids' => ['required', 'array', 'min:1', 'max:500'],
            'uids.*' => ['integer', 'min:1'],
            'target_folder' => self::FOLDER_RULES,
        ]);

        return $this->guard(function () use ($reader, $request, $v) {
            $uids = array_map('intval', $v['uids']);

            return response()->json(
                $reader->transferMessages(
                    $this->creds($request), $v['folder'], $uids,
                    $this->creds($request, 'target_account_id'), $v['target_folder'],
                ),
            );
        });
    }

    /**
     * Run an IMAP operation, converting any failure into a 422.
     *
     * "detail" carries the full exception chain (class + IMAP server message,
     * root cause included) so the single-tenant operator can diagnose failures
     * directly in the browser; it is rendered as plain text (x-text), never as
     * HTML, so a hostile server's banner cannot become script. It never contains
     * credentials — the mail services do not expose them. The same detail is
     * also logged server-side.
     */
    private function guard(\Closure $operation): Response|JsonResponse
    {
        try {
            return $operation();
        } catch (\Throwable $e) {
            // Log the exception detail server-side for diagnostics. This is the
            // IMAP server's own error (class + message + origin) — never
            // credentials, which the mail services do not expose.
            Log::warning('Mail operation failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'message' => __('mail.connect_failed'),
                // Surfaces the full exception chain (class + IMAP server message,
                // root cause included) so a single-tenant operator can diagnose
                // failures directly in the browser. Never includes credentials
                // (the mail services do not expose them).
                'detail' => $this->describeChain($e),
            ], 422);
        }
    }

    /** Full exception chain, newest → root cause, one capped line each. */
    private function describeChain(\Throwable $e): string
    {
        $lines = [];
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            $lines[] = class_basename($cur).': '.Str::limit($cur->getMessage(), 300);
        }

        return implode("\n", array_unique($lines));
    }
}
