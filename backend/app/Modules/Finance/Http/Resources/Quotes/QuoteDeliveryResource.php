<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Quotes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuoteDeliveryResource extends JsonResource
{
    /**
     * @param  array{uuid: string, revision_id: int, state: string, attempts: int, last_error_code: string|null, queued_at: string, sent_at: string|null, failed_at: string|null}  $delivery
     */
    public function __construct(private readonly array $delivery)
    {
        parent::__construct($delivery);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->delivery['uuid'],
            'revision_id' => $this->delivery['revision_id'],
            'state' => $this->delivery['state'],
            'attempts' => $this->delivery['attempts'],
            'last_error_code' => $this->delivery['last_error_code'],
            'queued_at' => $this->delivery['queued_at'],
            'sent_at' => $this->delivery['sent_at'],
            'failed_at' => $this->delivery['failed_at'],
        ];
    }
}
