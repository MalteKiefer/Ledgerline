<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;

/** RFC 3156 multipart/signed wrapper. The exact nested MIME bytes are signed. */
final class PgpSignedPart extends AbstractMultipartPart
{
    public function __construct(AbstractPart $signed, string $signature)
    {
        parent::__construct($signed, new PgpMimePart('application/pgp-signature', $signature));
    }

    public function getMediaSubtype(): string { return 'signed'; }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();
        $headers->setHeaderParameter('Content-Type', 'protocol', 'application/pgp-signature');
        $headers->setHeaderParameter('Content-Type', 'micalg', 'pgp-sha256');

        return $headers;
    }
}
