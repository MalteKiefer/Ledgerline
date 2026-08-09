<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the immutable raw RFC822 (.eml) bytes of an archived message. The
 * message's own id doubles as the blob key (mail/{id}); the row is resolved
 * owner-scoped (a foreign / unknown id is a 404, no existence leak). Streamed
 * with the same sandboxed, no-sniff, immutable headers as every other blob
 * endpoint so a malicious .eml can never execute or be embedded cross-origin.
 * `?download=1` forces a `.eml` attachment (the single-message export).
 */
class MailBlobController extends Controller
{
    public function raw(Request $request, string $blob): StreamedResponse
    {
        $user = $this->requireUser($request);

        $message = MailMessage::query()->ownedBy($user->id)->whereKey($blob)->first();
        abort_if($message === null, 404);

        $key = 'mail/'.$message->id;
        $fs = $this->fs();
        abort_unless($fs->exists($key), 404);

        $filename = $this->safeName($message->subject).'.eml';

        return $fs->response($key, $filename, [
            'Content-Type' => 'message/rfc822',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    private function fs(): Filesystem
    {
        $disk = config('files.disk');

        return Storage::disk(is_string($disk) ? $disk : 'files');
    }

    private function safeName(?string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', (string) $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'message' : mb_substr($clean, 0, 120);
    }
}
