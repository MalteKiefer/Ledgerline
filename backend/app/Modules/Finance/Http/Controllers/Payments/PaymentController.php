<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Payments;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Payments\RecordPayment;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use App\Modules\Finance\Application\Queries\Payments\GetPayment;
use App\Modules\Finance\Application\Queries\Payments\ListPayments;
use App\Modules\Finance\Domain\Payments\Exception\InvalidAllocation;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Http\Requests\Payments\PaymentListRequest;
use App\Modules\Finance\Http\Requests\Payments\RecordPaymentRequest;
use App\Modules\Finance\Http\Resources\PaymentPageResource;
use App\Modules\Finance\Http\Resources\PaymentResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentController
{
    public function index(PaymentListRequest $request, ListPayments $query): JsonResponse
    {
        $page = $query->handle(
            $request->filters(),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json((new PaymentPageResource($page))->resolve($request));
    }

    public function store(RecordPaymentRequest $request, RecordPayment $command): JsonResponse
    {
        try {
            $payment = $command->handle($request->paymentData(), $request->idempotencyKey());
        } catch (DomainException|InvalidArgumentException $exception) {
            return self::failure($exception);
        }

        return self::paymentResponse($request, $payment, 201);
    }

    public function show(Request $request, string $payment, GetPayment $query, PaymentRepository $payments): JsonResponse
    {
        return self::paymentResponse($request, $query->handle(self::paymentId($request, $payment, $payments)));
    }

    public static function ownerId(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (int) $user->id;
    }

    public static function paymentId(Request $request, string $uuid, PaymentRepository $payments): PaymentId
    {
        self::ownerId($request);

        return $payments->idForUuid($uuid);
    }

    public static function paymentResponse(Request $request, PaymentView $payment, int $status = 200): JsonResponse
    {
        return response()->json(
            (new PaymentResource($payment))->resolve($request),
            $status,
            ['ETag' => '"'.$payment->version.'"'],
        );
    }

    public static function failure(DomainException|InvalidArgumentException $exception): JsonResponse
    {
        return response()->json(['error' => self::errorCode($exception)], self::conflictCode(self::errorCode($exception)) ? 409 : 422);
    }

    public static function conflictCode(string $code): bool
    {
        return str_contains($code, 'idempotency') || str_contains($code, 'conflict');
    }

    public static function errorCode(DomainException|InvalidArgumentException $exception): string
    {
        if ($exception instanceof InvalidAllocation) {
            return $exception->errorCode;
        }
        if ($exception instanceof InvalidMoney) {
            return 'invalid_money';
        }
        if ($exception instanceof DomainException) {
            return $exception->getMessage();
        }

        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'currency') => 'invalid_currency',
            str_contains($message, 'reference') => 'invalid_reference',
            str_contains($message, 'counterparty') => 'invalid_counterparty',
            str_contains($message, 'source') => 'invalid_source',
            default => 'invalid_payment_input',
        };
    }
}
