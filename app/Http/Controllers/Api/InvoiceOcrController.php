<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Invoices\ReceiptOcr;
use App\Support\DiskTempFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Server-side receipt OCR (transient plaintext). The client POSTs the RAW
 * (decrypted) document ONLY to extract text; the server processes it in a
 * shredded temp file, returns line-structured text, and stores/logs nothing —
 * the same accepted transient-cleartext window as GalleryProcessController. The
 * document itself lives only as the client-encrypted blob (uploaded separately
 * via /invoices/upload); the returned text is analysed + stored client-side.
 *
 * The server returns ONLY text — no total/merchant/date parsing (that lives in
 * the shared client recogniser). Best-effort for the client: a 501/timeout just
 * leaves manual entry intact.
 */
class InvoiceOcrController extends Controller
{
    /** 25 MiB request cap (OCR is CPU/memory heavy). */
    private const MAX_BYTES = 25 * 1024 * 1024;

    public function ocr(Request $request, ReceiptOcr $ocr): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            // Well-formed Tesseract language string (e.g. deu, eng, deu+eng).
            'lang' => ['nullable', 'string', 'regex:/^[a-z]{3}(\+[a-z]{3})*$/'],
        ]);

        /** @var UploadedFile $upload */
        $upload = $request->file('file');

        // Size gate BEFORE any heavy work (Laravel `max:` would surface as 422).
        abort_if(($upload->getSize() ?: 0) > self::MAX_BYTES, 413, __('files.upload_failed'));

        $mime = $this->allowedMime($upload);
        abort_if($mime === null, 415, 'Unsupported document type.');

        // Fail cleanly when the OCR toolchain is absent so the client degrades
        // to manual entry (spec §5) instead of receiving misleading empty text.
        abort_unless($ocr->available(), 501, 'OCR is not available on this server.');

        // Controlled temp path with a guaranteed unlink even on throw.
        $tmp = DiskTempFile::create('llocr');
        $upload->move(dirname($tmp->path()), basename($tmp->path()));

        $lang = $request->filled('lang') ? $request->string('lang')->value() : 'deu+eng';
        $result = $ocr->extract($tmp->path(), $mime, $lang);

        if (trim($result['text']) === '') {
            return $this->noStore(response()->json(['error' => 'no_text'], 422));
        }

        return $this->noStore(response()->json([
            'text' => $result['text'],
            'source' => $result['source'],
            'pages' => $result['pages'],
        ]));
    }

    /**
     * The document's MIME, restricted to the OCR allow-list, or null (→ 415).
     * Checks the content sniff first, then the declared type; the downstream
     * binaries detect the real format by content regardless, so this is a soft
     * early-reject, not the security boundary.
     */
    private function allowedMime(UploadedFile $upload): ?string
    {
        $detected = (string) ($upload->getMimeType() ?: '');
        $client = (string) ($upload->getClientMimeType() ?: '');

        foreach ([$detected, $client] as $mime) {
            if ($mime === ReceiptOcr::PDF_MIME) {
                return ReceiptOcr::PDF_MIME;
            }
            if (in_array($mime, ReceiptOcr::IMAGE_MIMES, true)) {
                return $mime;
            }
        }

        return null;
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
