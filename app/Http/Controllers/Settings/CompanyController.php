<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use App\Support\BlobStore;
use App\Support\HtmlMailSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-user company profile for invoices. Each user invoices under their OWN
 * business identity + number sequence (stored in the clear in the user's settings
 * row — it is their own business identity that prints on every invoice, not
 * customer data, which stays zero-knowledge in the client manifest).
 */
class CompanyController extends Controller
{
    use RedirectsToSettings;

    /** Logo lives on the shared blob disk (S3), unencrypted like other assets;
     *  served only to the owning user. */
    private const LOGO_DIR = 'company';

    public function edit(Request $request): View
    {
        return view('settings.company.edit', ['s' => UserSetting::for($this->requireUser($request)->id)]);
    }

    public function update(Request $request): RedirectResponse
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
            'company_website' => ['nullable', 'string', 'max:255'],
            'company_contacts' => ['nullable', 'array', 'max:20'],
            'company_contacts.*.name' => ['nullable', 'string', 'max:200'],
            'company_contacts.*.role' => ['nullable', 'string', 'max:200'],
            'company_contacts.*.email' => ['nullable', 'string', 'max:200'],
            'company_contacts.*.phone' => ['nullable', 'string', 'max:100'],
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
            // Dedicated invoice SMTP (for sending invoices by e-mail).
            'invoice_mail_enabled' => ['nullable', 'boolean'],
            'invoice_smtp_host' => ['nullable', 'string', 'max:255'],
            'invoice_smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'invoice_smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'invoice_smtp_username' => ['nullable', 'string', 'max:255'],
            'invoice_smtp_password' => ['nullable', 'string', 'max:255'],
            'invoice_from_email' => ['nullable', 'email', 'max:254'],
            'invoice_from_name' => ['nullable', 'string', 'max:200'],
            'invoice_mail_subject' => ['nullable', 'string', 'max:255'],
            'invoice_mail_body' => ['nullable', 'string', 'max:20000'],
            'invoice_mail_signature' => ['nullable', 'string', 'max:20000'],
            // Raster only — SVG served inline on the app origin is a stored-XSS
            // vector (embedded <script>). Logos rarely need vector.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        // Persist only the fields that were actually submitted (mirrors the
        // validated set); logo/remove_logo are handled separately below.
        $fields = [
            'company_name', 'company_address', 'company_email', 'company_phone',
            'company_tax_id', 'company_vat_id', 'company_iban', 'company_bic',
            'company_bank_name', 'company_website', 'invoice_number_format', 'invoice_next_number',
            'invoice_default_vat_rate', 'invoice_payment_terms_days', 'invoice_footer_text',
            'invoice_accent_color', 'invoice_heading_color', 'invoice_template',
            'invoice_payment_methods', 'invoice_payment_terms_text',
            'invoice_smtp_host', 'invoice_smtp_port', 'invoice_smtp_encryption',
            'invoice_smtp_username', 'invoice_from_email', 'invoice_from_name',
            'invoice_mail_subject', 'invoice_mail_body',
        ];

        $data = [];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }
        if ($request->has('invoice_mail_enabled')) {
            $data['invoice_mail_enabled'] = $request->boolean('invoice_mail_enabled');
        }
        // Keep the stored SMTP password when the field is submitted blank (never
        // round-trip the secret to the browser).
        if ($request->filled('invoice_smtp_password')) {
            $data['invoice_smtp_password'] = $request->string('invoice_smtp_password')->value();
        }
        // The mail body + signature are rich HTML — sanitise server-side (defence in
        // depth; the client DOMPurify-sanitises before submit).
        foreach (['invoice_mail_body', 'invoice_mail_signature'] as $htmlField) {
            if ($request->has($htmlField)) {
                $data[$htmlField] = HtmlMailSanitizer::clean($request->string($htmlField)->value());
            }
        }
        // Company contact persons: drop fully-empty rows, keep only the four fields.
        if ($request->has('company_contacts')) {
            $rows = $request->input('company_contacts');
            $str = static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '';
            $clean = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $c = [
                    'name' => $str($row['name'] ?? null),
                    'role' => $str($row['role'] ?? null),
                    'email' => $str($row['email'] ?? null),
                    'phone' => $str($row['phone'] ?? null),
                ];
                if ($c['name'] !== '' || $c['email'] !== '' || $c['phone'] !== '') {
                    $clean[] = $c;
                }
            }
            $data['company_contacts'] = $clean;
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

        return $this->savedSettings('company', 'settings.company.edit', 'settings.company_saved');
    }

    /** Stream the current user's stored company logo (invoice view + print/PDF). */
    public function logo(Request $request): StreamedResponse
    {
        $disk = BlobStore::disk();
        $path = UserSetting::for($this->requireUser($request)->id)->company_logo_path;
        abort_if(! $path || ! $disk->exists($path), 404);

        // Defense-in-depth: even though only raster images are accepted, pin the
        // sniffed type off and sandbox the response so a direct open can never
        // execute script, regardless of stored bytes.
        return $disk->download($path, 'logo', [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
