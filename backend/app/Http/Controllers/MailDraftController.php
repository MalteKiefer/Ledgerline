<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Owner-scoped persistence for the composer autosave. Local uploads stay browser-local. */
class MailDraftController extends Controller
{
    private const MAX_DRAFTS = 200;

    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json(['drafts' => MailDraft::query()->orderByDesc('updated_at')->limit(50)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $data = $this->validated($request);
        // Autosave creates rows on a fast path with a 1 MB body cap each, so
        // bound how much an account can accumulate.
        if (MailDraft::query()->count() >= self::MAX_DRAFTS) {
            return response()->json(['error' => 'draft_limit_reached'], 422);
        }
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

    /**
     * Every referenced id is checked against the caller's own rows here as well
     * as at send time. The send path is what actually protects the data, but a
     * draft that quietly holds another account's ids is worth refusing at the
     * point it is written rather than discovering it later.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $uid = (int) $this->requireUser($request)->id;
        $ownedBy = static fn (string $table): Exists => Rule::exists($table, 'id')->where('user_id', $uid);

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($request->all(), [
            'mail_account_id' => ['nullable', 'integer', $ownedBy('mail_accounts')], 'mode' => ['required', 'in:compose,reply,forward'],
            'source_message_id' => ['nullable', 'uuid'], 'to' => ['nullable', 'array', 'max:50'],
            'to.*' => ['email:rfc'], 'cc' => ['nullable', 'array', 'max:50'], 'cc.*' => ['email:rfc'],
            'bcc' => ['nullable', 'array', 'max:50'], 'bcc.*' => ['email:rfc'],
            'subject' => ['nullable', 'string', 'max:998'], 'text_body' => ['nullable', 'string', 'max:1000000'],
            'html_body' => ['nullable', 'string', 'max:1000000'],
            'mail_signature_id' => ['nullable', 'integer', $ownedBy('mail_signatures')],
            'sent_folder' => ['nullable', 'string', 'max:255'], 'file_ids' => ['nullable', 'array', 'max:20'],
            'file_ids.*' => ['integer', $ownedBy('files')], 'gallery_photo_ids' => ['nullable', 'array', 'max:20'],
            'gallery_photo_ids.*' => ['integer', $ownedBy('gallery_photos')], 'read_receipt' => ['nullable', 'boolean'],
            'high_priority' => ['nullable', 'boolean'],
            'crypto_mode' => ['nullable', 'in:none,sign,encrypt,sign_encrypt'],
            'crypto_type' => ['nullable', 'in:pgp,smime'],
            'signing_key_id' => ['nullable', 'integer', $ownedBy('mail_pgp_keys')],
            'recipient_key_ids' => ['nullable', 'array', 'max:50'], 'recipient_key_ids.*' => ['integer', $ownedBy('crypto_recipients')],
        ])->validate();

        return $validated;
    }
}
