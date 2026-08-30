<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Documents;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Projects\AppendDocumentNote;
use App\Modules\Finance\Application\Queries\Projects\ListDocumentNotes;
use App\Modules\Finance\Http\Requests\Projects\ProjectNoteRequest;
use App\Modules\Finance\Http\Resources\Projects\ProjectHistoryResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class DocumentNoteController
{
    public function index(ProjectNoteRequest $request, string $series, ListDocumentNotes $query): JsonResponse
    {
        return response()->json((new ProjectHistoryResource($query->handle($this->ownerId($request), $series, $request->filter())))->resolve($request));
    }

    public function store(ProjectNoteRequest $request, string $series, AppendDocumentNote $command): JsonResponse
    {
        try {
            $note = $command->handle($request->documentData($this->ownerId($request), $series));
        } catch (DomainException|InvalidArgumentException $exception) {
            return response()->json(['error' => 'invalid_document_note'], 422);
        }

        return response()->json((new ProjectHistoryResource($note))->resolve($request), 201);
    }

    private function ownerId(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (int) $user->id;
    }
}
