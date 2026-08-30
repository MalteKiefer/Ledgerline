<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Quotes;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Converted = 'converted';
}
