<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

use DomainException;

final class RecurringTemplateVersionConflict extends DomainException
{
    public function __construct(public readonly RecurringTemplateView $current)
    {
        parent::__construct('recurring_template_version_conflict');
    }
}
