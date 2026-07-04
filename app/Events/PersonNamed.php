<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A person was given a name. The upcoming vCard contacts module will listen for
 * this to link/create a contact; nothing subscribes yet.
 */
class PersonNamed
{
    use Dispatchable;

    public function __construct(public string $personId, public string $name, public ?int $userId = null) {}
}
