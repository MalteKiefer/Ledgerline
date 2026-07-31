<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use App\Support\BlobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-user company profile + invoice defaults over the API (Sanctum device token).
 * This is the user's OWN business identity that prints on every invoice — non-secret
 * (never customer data, which stays zero-knowledge in the client manifest), so it is
 * served in the clear like the avatar. Mirrors the web Settings/CompanyController.
 */
class CompanyController extends Controller
{
    private const LOGO_DIR = 'company';

    /** The editable company/invoice fields (all persisted on the user's settings row). */
    private const FIELDS = [
        'company_name', 'company_address', 'company_email', 'company_phone',
        'company_tax_id', 'company_vat_id', 'company_iban', 'company_bic',
        'company_bank_name', 'invoice_number_format', 'invoice_next_number',
        'invoice_default_vat_rate', 'invoice_payment_terms_days', 'invoice_footer_text',
        'invoice_accent_color', 'invoice_heading_color', 'invoice_template',
        'invoice_payment_methods', 'invoice_payment_terms_text',
    ];

    /** Return the caller's company profile. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['company' => $this->present(UserSetting::for($this->requireUser($request)->id))]);
    }

    /** Update the caller's company profile (JSON fields; optional multipart logo). */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => ['nullable', 'string', 'max:200'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_email' => ['nullable', 'email', 'max:200'],
            'company_phone' => ['nullable', 'string', 'max:100'],
            'company_tax_id' => ['nullable', 'string', 'max:100'],
            'company_vat_id' => ['nullable', 'string', 'max:100'],
            'company_iban' => ['nullable', 'string', 'max:60'],
            'company_bic' => ['nullable', 'string', 'max:20'],
            'company_bank_name' => ['nullable', 'string', 'max:200'],
            'invoice_number_format' => ['nullable', 'string', 'max:40'],
            'invoice_next_number' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'invoice_default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'invoice_payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'invoice_footer_text' => ['nullable', 'string', 'max:2000'],
            'invoice_accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'invoice_heading_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'invoice_template' => ['nullable', 'string', 'in:editorial,modern,elegant,klassisch'],
            'invoice_payment_methods' => ['nullable', 'string', 'max:500'],
            'invoice_payment_terms_text' => ['nullable', 'string', 'max:1000'],
            // Raster only — inline SVG on the app origin is a stored-XSS vector.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $data = [];
        foreach (self::FIELDS as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        $settings = UserSetting::for($this->requireUser($request)->id);
        $disk = BlobStore::disk();

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            if ($settings->company_logo_path) {
                $disk->delete($settings->company_logo_path);
            }
            $data['company_logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $diskName = config('files.disk');
            $data['company_logo_path'] = $request->file('logo')->store(
                self::LOGO_DIR,
                ['disk' => is_string($diskName) ? $diskName : ''],
            );
        }

        $settings->update($data);

        return response()->json(['company' => $this->present($settings->fresh() ?? $settings)]);
    }

    /** Stream the caller's stored company logo (invoice render / PDF). */
    public function logo(Request $request): StreamedResponse
    {
        $disk = BlobStore::disk();
        $path = UserSetting::for($this->requireUser($request)->id)->company_logo_path;
        abort_if(! $path || ! $disk->exists($path), 404);

        return $disk->download($path, 'logo', [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** @return array<string, mixed> */
    private function present(UserSetting $s): array
    {
        return [
            'company_name' => $s->company_name,
            'company_address' => $s->company_address,
            'company_email' => $s->company_email,
            'company_phone' => $s->company_phone,
            'company_tax_id' => $s->company_tax_id,
            'company_vat_id' => $s->company_vat_id,
            'company_iban' => $s->company_iban,
            'company_bic' => $s->company_bic,
            'company_bank_name' => $s->company_bank_name,
            'invoice_number_format' => $s->invoice_number_format,
            'invoice_next_number' => $s->invoice_next_number,
            'invoice_default_vat_rate' => $s->invoice_default_vat_rate,
            'invoice_payment_terms_days' => $s->invoice_payment_terms_days,
            'invoice_footer_text' => $s->invoice_footer_text,
            'invoice_accent_color' => $s->invoice_accent_color,
            'invoice_heading_color' => $s->invoice_heading_color,
            'invoice_template' => $s->invoice_template,
            'invoice_payment_methods' => $s->invoice_payment_methods,
            'invoice_payment_terms_text' => $s->invoice_payment_terms_text,
            'has_logo' => (bool) $s->company_logo_path,
        ];
    }
}
