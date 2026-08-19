<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Owner-scoped persistence for the composer autosave. Local uploads stay browser-local. */
class MailDraftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json(['drafts' => MailDraft::query()->orderByDesc('updated_at')->limit(50)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $data = $this->validated($request);
        $draft = new MailDraft($data);
        $draft->save();

        return response()->json(['draft' => $draft], 201);
    }

    public function update(Request $request, MailDraft $draft): JsonResponse
    {
        $this->requireUser($request);
        $draft->update($this->validated($request));

        return response()->json(['draft' => $draft->fresh()]);
    }

    public function destroy(Request $request, MailDraft $draft): JsonResponse
    {
        $this->requireUser($request);
        $draft->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($request->all(), [
            'mail_account_id' => ['nullable', 'integer'], 'mode' => ['required', 'in:compose,reply,forward'],
            'source_message_id' => ['nullable', 'uuid'], 'to' => ['nullable', 'array', 'max:50'],
            'to.*' => ['email:rfc'], 'cc' => ['nullable', 'array', 'max:50'], 'cc.*' => ['email:rfc'],
            'bcc' => ['nullable', 'array', 'max:50'], 'bcc.*' => ['email:rfc'],
            'subject' => ['nullable', 'string', 'max:998'], 'text_body' => ['nullable', 'string', 'max:1000000'],
            'html_body' => ['nullable', 'string', 'max:1000000'], 'mail_signature_id' => ['nullable', 'integer'],
            'sent_folder' => ['nullable', 'string', 'max:255'], 'file_ids' => ['nullable', 'array', 'max:20'],
            'file_ids.*' => ['integer'], 'gallery_photo_ids' => ['nullable', 'array', 'max:20'],
            'gallery_photo_ids.*' => ['integer'], 'read_receipt' => ['nullable', 'boolean'],
            'high_priority' => ['nullable', 'boolean'],
        ])->validate();

        return $validated;
    }
}
