<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects\Exception;

use DomainException;

final class InvalidProjectAction extends DomainException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
