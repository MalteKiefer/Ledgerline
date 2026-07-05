<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\DavCredential;
use App\Services\Contacts\DavCredentialService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Shared CardDAV/CalDAV sync data (one login serves both) for the per-user
 * calendar and contacts settings pages: the credential, the /dav/ URL and a QR
 * code of it.
 */
trait ProvidesDavSync
{
    /**
     * @return array{credential: ?DavCredential, davUrl: string, qr: ?string}
     */
    protected function davSync(int $userId): array
    {
        $credential = app(DavCredentialService::class)->for($userId);
        $davUrl = url('/dav/');

        return [
            'credential' => $credential,
            'davUrl' => $davUrl,
            'qr' => $credential !== null ? $this->davQr($davUrl) : null,
        ];
    }

    private function davQr(string $text): string
    {
        $renderer = new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($text);
    }
}
