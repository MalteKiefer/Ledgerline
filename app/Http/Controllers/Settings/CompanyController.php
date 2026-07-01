<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manage the global company profile (invoice sender identity).
 */
class CompanyController extends Controller
{
    public function edit(): View
    {
        return view('settings.company.edit', [
            'company' => CompanyProfile::current(),
            'countries' => Countries::options(),
            'languages' => config('finance.languages'),
            'currencies' => config('finance.currencies'),
        ]);
    }

    public function update(CompanyProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company', 'local');
        }

        unset($data['logo']);

        CompanyProfile::current()->update($data);

        return redirect()->route('settings.company.edit')->with('status', __('flash.company_saved'));
    }

    /**
     * Serve the company logo (private disk).
     */
    public function logo(): StreamedResponse
    {
        $company = CompanyProfile::current();
        $disk = Storage::disk('local');

        abort_unless(is_string($company->logo_path) && $disk->exists($company->logo_path), 404);

        return $disk->response($company->logo_path, 'logo', [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }
}
