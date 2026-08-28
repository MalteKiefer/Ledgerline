<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Invoices\Exception;

use App\Modules\Finance\Domain\Invoices\InvoiceStatus;
use DomainException;

final class InvalidInvoiceState extends DomainException
{
    /** @var string */
    public $code;

    public function __construct(
        public readonly string $errorCode,
        public readonly InvoiceStatus $status,
        public readonly string $action,
    ) {
        parent::__construct(sprintf(
            'Invoice in state "%s" cannot perform action "%s".',
            $status->value,
            $action,
        ));

        $this->code = $errorCode;
    }
}
