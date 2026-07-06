<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reusable HTML mail signatures (user-owned, unlimited). Managed on a dedicated
 * page as a JSON API; the HTML is user-authored rich text (sanitised on render,
 * never executed — sending inlines it, the archive viewer sandboxes it).
 */
class MailSignatureController extends Controller
{
    public function page(): View
    {
        return view('mail.signatures');
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'signatures' => MailSignature::orderByDesc('is_default')->orderBy('name')
                ->get()->map(fn (MailSignature $s) => $this->toArray($s))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $signature = DB::transaction(function () use ($data): MailSignature {
            $isDefault = (bool) ($data['is_default'] ?? false) || MailSignature::query()->count() === 0;
            if ($isDefault) {
                MailSignature::query()->update(['is_default' => false]);
            }

            return MailSignature::create([
                'name' => $data['name'],
                'html' => $data['html'] ?? null,
                'is_default' => $isDefault,
            ]);
        });

        return response()->json($this->toArray($signature), 201);
    }

    public function update(Request $request, MailSignature $signature): JsonResponse
    {
        $this->authorizeOwn($signature);
        $data = $this->validated($request);
        DB::transaction(function () use ($signature, $data): void {
            if ((bool) ($data['is_default'] ?? false)) {
                MailSignature::query()->where('id', '!=', $signature->id)->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                $data['is_default'] = $signature->is_default;
            }
            $signature->update([
                'name' => $data['name'],
                'html' => $data['html'] ?? null,
                'is_default' => $data['is_default'],
            ]);
        });

        return response()->json($this->toArray($signature->refresh()));
    }

    public function destroy(MailSignature $signature): JsonResponse
    {
        $this->authorizeOwn($signature);
        $signature->delete(); // identities' signature_id nulls out (FK nullOnDelete)

        return response()->json(['ok' => true]);
    }

    private function authorizeOwn(MailSignature $signature): void
    {
        abort_unless((int) $signature->user_id === (int) auth()->id(), 403);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'html' => ['nullable', 'string', 'max:50000'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string,mixed> */
    private function toArray(MailSignature $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'html' => $s->html,
            'isDefault' => $s->is_default,
        ];
    }
}
