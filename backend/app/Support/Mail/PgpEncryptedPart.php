<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractMultipartPart;

/** RFC 3156 multipart/encrypted wrapper. */
final class PgpEncryptedPart extends AbstractMultipartPart
{
    public function __construct(string $armoredPayload)
    {
        parent::__construct(
            new PgpMimePart('application/pgp-encrypted', "Version: 1\r\n"),
            new PgpMimePart('application/octet-stream', $armoredPayload),
        );
    }

    public function getMediaSubtype(): string { return 'encrypted'; }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();
        $headers->setHeaderParameter('Content-Type', 'protocol', 'application/pgp-encrypted');

        return $headers;
    }
}
