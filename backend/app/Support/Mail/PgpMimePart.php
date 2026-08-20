<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractPart;

/** A small immutable MIME leaf for the PGP/MIME control and armored data parts. */
final class PgpMimePart extends AbstractPart
{
    public function __construct(private readonly string $type, private readonly string $body)
    {
        parent::__construct();
    }

    public function getMediaType(): string
    {
        return explode('/', $this->type, 2)[0];
    }

    public function getMediaSubtype(): string
    {
        return explode('/', $this->type, 2)[1];
    }

    public function getPreparedHeaders(): Headers
    {
        $headers = parent::getPreparedHeaders();
        $headers->setHeaderBody('Text', 'Content-Transfer-Encoding', '7bit');

        return $headers;
    }

    public function bodyToString(): string
    {
        return $this->body;
    }

    /** @return iterable<string> */
    public function bodyToIterable(): iterable
    {
        yield $this->body;
    }
}
