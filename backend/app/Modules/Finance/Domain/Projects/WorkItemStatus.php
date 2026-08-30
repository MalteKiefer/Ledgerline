<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

enum WorkItemStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
}
