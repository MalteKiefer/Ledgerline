<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Quotes;

enum QuoteRevisionState: string
{
    case Current = 'current';
    case Replaced = 'replaced';
}
