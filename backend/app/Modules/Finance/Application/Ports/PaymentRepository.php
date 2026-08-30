<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentPage;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use DateTimeImmutable;

interface PaymentRepository
{
    public function get(PaymentId $id): PaymentView;

    /**
     * Resolves the owner-scoped internal ID for a public payment UUID.
     * Throws (not-found) when the UUID does not belong to the current owner.
     */
    public function idForUuid(string $uuid): PaymentId;

    /** @param array<string, mixed> $filters */
    public function page(array $filters, int $page, int $perPage): PaymentPage;

    public function record(RecordPaymentData $data, IdempotencyKey $key): PaymentView;

    public function allocate(AllocatePaymentData $data, IdempotencyKey $key): AllocationResult;

    public function reverse(
        AllocationId $id,
        IdempotencyKey $key,
        ?int $expectedPaymentVersion = null,
    ): AllocationResult;

    /**
     * @return array{
     *   payment: PaymentView,
     *   invoices: list<array{
     *     invoice_id:int,
     *     number:string,
     *     currency:string,
     *     open_minor:int,
     *     issue_date:DateTimeImmutable,
     *     customer:string
     *   }>
     * }
     */
    public function suggestionContext(PaymentId $id): array;
}
