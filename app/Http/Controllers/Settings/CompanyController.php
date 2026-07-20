<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Support\BlobStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company profile for invoices (admin). Stored in the clear in AppSettings — it
 * is the workspace owner's own business identity that prints on every invoice,
 * not customer data (which stays zero-knowledge in the client manifest).
 */
class CompanyController extends Controller
{
    use RedirectsToSettings;

    /** Logo lives on the shared blob disk (S3), unencrypted like other assets;
     *  served only to authenticated users. */
    private const LOGO_DIR = 'company';

    public function edit(): View
    {
        return view('settings.company.edit', ['s' => AppSettings::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
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
            'invoice_template' => ['nullable', 'string', 'in:editorial,modern,elegant'],
            'invoice_payment_methods' => ['nullable', 'string', 'max:500'],
            'invoice_payment_terms_text' => ['nullable', 'string', 'max:1000'],
            // Raster only — SVG served inline on the app origin is a stored-XSS
            // vector (embedded <script>). Logos rarely need vector.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $settings = AppSettings::current();

        $disk = BlobStore::disk();

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            if ($settings->company_logo_path) {
                $disk->delete($settings->company_logo_path);
            }
            $data['company_logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $data['company_logo_path'] = $request->file('logo')->store(self::LOGO_DIR, ['disk' => config('files.disk')]);
        }

        unset($data['logo'], $data['remove_logo']);

        $settings->update($data);

        return $this->savedSettings('company', 'settings.company.edit', 'settings.company_saved');
    }

    /** Stream the stored company logo (used by the invoice view + print/PDF). */
    public function logo(): StreamedResponse
    {
        $disk = BlobStore::disk();
        $path = AppSettings::current()->company_logo_path;
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
