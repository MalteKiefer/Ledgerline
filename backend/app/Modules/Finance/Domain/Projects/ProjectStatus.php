<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

enum ProjectStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
