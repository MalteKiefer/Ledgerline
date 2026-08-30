<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Quotes\Exception;

use DomainException;
use Throwable;

final class InvalidQuoteAction extends DomainException
{
    public function __construct(
        public readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, previous: $previous);
    }
}
