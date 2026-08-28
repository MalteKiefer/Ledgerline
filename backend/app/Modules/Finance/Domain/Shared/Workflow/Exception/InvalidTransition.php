<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared\Workflow\Exception;

use DomainException;

final class InvalidTransition extends DomainException
{
    /** @var string */
    public $code;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf('Transition from "%s" to "%s" is not allowed.', $from, $to));

        $this->code = 'invalid_transition';
    }
}
