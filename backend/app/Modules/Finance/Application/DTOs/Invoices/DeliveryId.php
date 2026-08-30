<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use InvalidArgumentException;

final readonly class DeliveryId
{
    public function __construct(
        public int $value,
        public ?string $uuid = null,
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException('Delivery IDs must be positive.');
        }
        if ($uuid !== null
            && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $uuid) !== 1) {
            throw new InvalidArgumentException('Delivery UUIDs must use canonical lowercase form.');
        }
    }
}
