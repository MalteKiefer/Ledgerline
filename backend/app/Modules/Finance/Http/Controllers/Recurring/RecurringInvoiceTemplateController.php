<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Recurring;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Recurring\AddRecurringInvoiceTemplateVersion;
use App\Modules\Finance\Application\Commands\Recurring\CreateRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Commands\Recurring\PauseRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Commands\Recurring\ResumeRecurringInvoiceTemplate;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateVersionConflict;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateView;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use App\Modules\Finance\Application\Queries\Recurring\GetRecurringInvoiceTemplate;
use App\Modules\Finance\Application\Queries\Recurring\ListRecurringTemplates;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;
use App\Modules\Finance\Http\Requests\Recurring\RecurringTemplateActionRequest;
use App\Modules\Finance\Http\Requests\Recurring\RecurringTemplateListRequest;
use App\Modules\Finance\Http\Requests\Recurring\RecurringTemplateRequest;
use App\Modules\Finance\Http\Requests\Recurring\RecurringTemplateVersionRequest;
use App\Modules\Finance\Http\Resources\RecurringInvoiceTemplatePageResource;
use App\Modules\Finance\Http\Resources\RecurringInvoiceTemplateResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RecurringInvoiceTemplateController
{
    public function index(RecurringTemplateListRequest $request, ListRecurringTemplates $query): JsonResponse
    {
        $page = $query->handle(
            $request->filters(),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json((new RecurringInvoiceTemplatePageResource($page))->resolve($request));
    }

    public function store(RecurringTemplateRequest $request, CreateRecurringInvoiceTemplate $command): JsonResponse
    {
        try {
            $template = $command->handle($request->templateData(), $request->idempotencyKey());
        } catch (DomainException|InvalidArgumentException $exception) {
            return self::failure($exception);
        }

        return self::templateResponse($request, $template, 201);
    }

    public function show(
        Request $request,
        string $template,
        GetRecurringInvoiceTemplate $query,
        RecurringInvoiceRepository $templates,
    ): JsonResponse {
        return self::templateResponse($request, $query->handle(self::templateId($request, $template, $templates)));
    }

    public function addVersion(
        RecurringTemplateVersionRequest $request,
        string $template,
        AddRecurringInvoiceTemplateVersion $command,
        RecurringInvoiceRepository $templates,
    ): JsonResponse {
        try {
            $id = self::templateId($request, $template, $templates);
            $view = $command->handle($id, $request->versionData(), $request->expectedVersion(), $request->idempotencyKey());

            return self::templateResponse($request, $view, 201);
        } catch (RecurringTemplateVersionConflict $conflict) {
            return self::conflictResponse($request, $conflict->current);
        } catch (DomainException|InvalidArgumentException $exception) {
            return self::failure($exception);
        }
    }

    public function pause(
        RecurringTemplateActionRequest $request,
        string $template,
        PauseRecurringInvoiceTemplate $command,
        RecurringInvoiceRepository $templates,
    ): JsonResponse {
        try {
            $id = self::templateId($request, $template, $templates);
            $view = $command->handle($id, $request->expectedVersion(), $request->idempotencyKey());

            return self::templateResponse($request, $view);
        } catch (RecurringTemplateVersionConflict $conflict) {
            return self::conflictResponse($request, $conflict->current);
        } catch (DomainException|InvalidArgumentException $exception) {
            return self::failure($exception);
        }
    }

    public function resume(
        RecurringTemplateActionRequest $request,
        string $template,
        ResumeRecurringInvoiceTemplate $command,
        RecurringInvoiceRepository $templates,
    ): JsonResponse {
        try {
            $id = self::templateId($request, $template, $templates);
            $view = $command->handle($id, $request->expectedVersion(), $request->idempotencyKey());

            return self::templateResponse($request, $view);
        } catch (RecurringTemplateVersionConflict $conflict) {
            return self::conflictResponse($request, $conflict->current);
        } catch (DomainException|InvalidArgumentException $exception) {
            return self::failure($exception);
        }
    }

    public static function ownerId(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (int) $user->id;
    }

    public static function templateId(Request $request, string $uuid, RecurringInvoiceRepository $templates): RecurringTemplateId
    {
        self::ownerId($request);

        return $templates->templateIdForUuid($uuid);
    }

    public static function templateResponse(Request $request, RecurringTemplateView $template, int $status = 200): JsonResponse
    {
        return response()->json(
            (new RecurringInvoiceTemplateResource($template))->resolve($request),
            $status,
            ['ETag' => '"'.$template->version.'"'],
        );
    }

    private static function conflictResponse(Request $request, RecurringTemplateView $current): JsonResponse
    {
        return response()->json(
            ['error' => 'recurring_template_version_conflict', 'current' => (new RecurringInvoiceTemplateResource($current))->resolve($request)],
            409,
            ['ETag' => '"'.$current->version.'"'],
        );
    }

    public static function failure(DomainException|InvalidArgumentException $exception): JsonResponse
    {
        $code = self::errorCode($exception);

        return response()->json(['error' => $code], self::conflictCode($code) ? 409 : 422);
    }

    private static function conflictCode(string $code): bool
    {
        return str_contains($code, 'idempotency') || str_contains($code, 'conflict') || str_contains($code, 'operation_in_progress');
    }

    private static function errorCode(DomainException|InvalidArgumentException $exception): string
    {
        if ($exception instanceof InvalidMoney) {
            return 'invalid_money';
        }
        if ($exception instanceof InvalidQuantity) {
            return 'invalid_quantity';
        }
        if ($exception instanceof DomainException) {
            return $exception->getMessage();
        }

        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'timezone') => 'invalid_timezone',
            str_contains($message, 'interval') => 'invalid_interval',
            str_contains($message, 'effective') => 'invalid_effective_date',
            str_contains($message, 'mode') => 'invalid_recurring_mode',
            default => 'invalid_recurring_template_input',
        };
    }
}
