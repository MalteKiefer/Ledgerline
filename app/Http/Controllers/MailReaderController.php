<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Mail\ImapCredentials;
use App\Services\Mail\ImapReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Read IMAP messages and act on them (delete, move, flag, transfer).
 *
 * Stateless like MailStatsController: credentials are decrypted in the browser
 * and posted per request; the server connects, performs one operation and
 * forgets. Nothing is persisted or logged; TLS is required.
 */
class MailReaderController extends Controller
{
    /** Credential validation rules, optionally namespaced (e.g. "target."). */
    private function credRules(string $prefix = ''): array
    {
        return [
            "{$prefix}host" => ['required', 'string', 'max:255'],
            "{$prefix}port" => ['required', 'integer', 'min:1', 'max:65535'],
            "{$prefix}encryption" => ['required', Rule::in(['ssl', 'starttls'])],
            "{$prefix}username" => ['required', 'string', 'max:255'],
            "{$prefix}password" => ['required', 'string'],
            "{$prefix}validate_cert" => ['sometimes', 'boolean'],
        ];
    }

    private function creds(array $data, string $prefix = ''): ImapCredentials
    {
        return new ImapCredentials(
            host: data_get($data, "{$prefix}host"),
            port: (int) data_get($data, "{$prefix}port"),
            encryption: data_get($data, "{$prefix}encryption"),
            username: data_get($data, "{$prefix}username"),
            password: data_get($data, "{$prefix}password"),
            validateCert: (bool) (data_get($data, "{$prefix}validate_cert") ?? true),
        );
    }

    public function folders(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules());

        return $this->guard(fn () => response()->json(['folders' => $reader->listFolders($this->creds($v))]));
    }

    public function createFolder(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules() + ['folder' => ['required', 'string', 'max:255']]);

        return $this->guard(function () use ($reader, $v) {
            $reader->createFolder($this->creds($v), $v['folder']);

            return response()->json(['created' => true]);
        });
    }

    public function emptyFolder(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules() + ['folder' => ['required', 'string', 'max:255']]);

        return $this->guard(function () use ($reader, $v) {
            $reader->emptyFolder($this->creds($v), $v['folder']);

            return response()->json(['emptied' => true]);
        });
    }

    public function messages(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules() + [
            'folder' => ['required', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->guard(fn () => response()->json(
            $reader->listMessages($this->creds($v), $v['folder'], (int) ($v['page'] ?? 1), 50)
        ));
    }

    public function message(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules() + [
            'folder' => ['required', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
            'mark_seen' => ['sometimes', 'boolean'],
        ]);

        return $this->guard(fn () => response()->json(
            $reader->getMessage($this->creds($v), $v['folder'], (int) $v['uid'], (bool) ($v['mark_seen'] ?? true))
        ));
    }

    public function attachment(Request $request, ImapReader $reader): Response|JsonResponse
    {
        $v = $request->validate($this->credRules() + [
            'folder' => ['required', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
            'attachment' => ['required', 'integer', 'min:0'],
        ]);

        return $this->guard(function () use ($reader, $v) {
            $a = $reader->getAttachment($this->creds($v), $v['folder'], (int) $v['uid'], (int) $v['attachment']);

            return response($a['content'], 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.addslashes($a['name']).'"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control' => 'private, no-store',
            ]);
        });
    }

    public function action(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules() + [
            'folder' => ['required', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['trash', 'delete', 'move', 'seen', 'unseen'])],
            'target' => ['required_if:action,move', 'nullable', 'string', 'max:255'],
        ]);

        return $this->guard(function () use ($reader, $v) {
            $creds = $this->creds($v);
            $result = match ($v['action']) {
                'trash' => $reader->deleteMessage($creds, $v['folder'], (int) $v['uid'], false),
                'delete' => $reader->deleteMessage($creds, $v['folder'], (int) $v['uid'], true),
                'move' => tap(['moved' => true], fn () => $reader->moveMessage($creds, $v['folder'], (int) $v['uid'], $v['target'])),
                'seen' => tap(['seen' => true], fn () => $reader->flagMessage($creds, $v['folder'], (int) $v['uid'], true)),
                'unseen' => tap(['seen' => false], fn () => $reader->flagMessage($creds, $v['folder'], (int) $v['uid'], false)),
            };

            return response()->json($result);
        });
    }

    public function transfer(Request $request, ImapReader $reader): JsonResponse
    {
        $v = $request->validate($this->credRules() + $this->credRules('target.') + [
            'folder' => ['required', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
            'target_folder' => ['required', 'string', 'max:255'],
        ]);

        return $this->guard(function () use ($reader, $v) {
            $reader->transferMessage(
                $this->creds($v), $v['folder'], (int) $v['uid'],
                $this->creds($v, 'target.'), $v['target_folder'],
            );

            return response()->json(['transferred' => true]);
        });
    }

    /**
     * Run an IMAP operation, converting any failure into a 422. Only the
     * exception class (e.g. "AuthFailedException", "ConnectionFailedException")
     * is surfaced as "detail" so the account owner can tell auth from
     * connection problems — the raw message is withheld, as it can echo server
     * banners, folder names or attempted commands.
     */
    private function guard(\Closure $operation): Response|JsonResponse
    {
        try {
            return $operation();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('mail.connect_failed'),
                'detail' => class_basename($e),
            ], 422);
        }
    }
}
