<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

enum ProjectKind: string
{
    case Business = 'business';
    case Private = 'private';
}
