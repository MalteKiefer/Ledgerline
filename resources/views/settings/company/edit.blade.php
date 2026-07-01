<x-layouts.app title="Company profile">
    @php
        $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">Settings</a> <span aria-hidden="true">/</span> Company
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Company profile</h1>
    <p class="mt-1 text-sm text-gray-600">Used as the sender on your invoices. Only filled fields are shown.</p>

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="mt-6 space-y-8">
        @csrf
        @method('PUT')

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Company</h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="legal_name" class="block text-sm font-medium text-gray-700">Legal name<span class="text-red-600"> *</span></label>
                    <input type="text" id="legal_name" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" required class="{{ $input }}">
                    @error('legal_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="managing_director" class="block text-sm font-medium text-gray-700">Managing director</label>
                    <input type="text" id="managing_director" name="managing_director" value="{{ old('managing_director', $company->managing_director) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="vat_id" class="block text-sm font-medium text-gray-700">VAT ID (USt-IdNr.)</label>
                    <input type="text" id="vat_id" name="vat_id" value="{{ old('vat_id', $company->vat_id) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="tax_number" class="block text-sm font-medium text-gray-700">Tax number</label>
                    <input type="text" id="tax_number" name="tax_number" value="{{ old('tax_number', $company->tax_number) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="register_court" class="block text-sm font-medium text-gray-700">Register court</label>
                    <input type="text" id="register_court" name="register_court" value="{{ old('register_court', $company->register_court) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="register_number" class="block text-sm font-medium text-gray-700">Register number</label>
                    <input type="text" id="register_number" name="register_number" value="{{ old('register_number', $company->register_number) }}" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="small_business" value="1" @checked(old('small_business', $company->small_business))
                            class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        Small business (§19 UStG) — no VAT shown on invoices
                    </label>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Address</h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="address_line1" class="block text-sm font-medium text-gray-700">Address line 1</label>
                    <input type="text" id="address_line1" name="address_line1" value="{{ old('address_line1', $company->address_line1) }}" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="address_line2" class="block text-sm font-medium text-gray-700">Address line 2</label>
                    <input type="text" id="address_line2" name="address_line2" value="{{ old('address_line2', $company->address_line2) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal code</label>
                    <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $company->city) }}" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <x-country-combobox name="country" :value="$company->country" />
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Contact &amp; bank</h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $company->email) }}" class="{{ $input }}">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $company->phone) }}" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="website" class="block text-sm font-medium text-gray-700">Website</label>
                    <input type="url" id="website" name="website" value="{{ old('website', $company->website) }}" class="{{ $input }}">
                    @error('website')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="iban" class="block text-sm font-medium text-gray-700">IBAN</label>
                    <input type="text" id="iban" name="iban" value="{{ old('iban', $company->iban) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="bic" class="block text-sm font-medium text-gray-700">BIC</label>
                    <input type="text" id="bic" name="bic" value="{{ old('bic', $company->bic) }}" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="bank_name" class="block text-sm font-medium text-gray-700">Bank name</label>
                    <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $company->bank_name) }}" class="{{ $input }}">
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Logo</h2>
            <div class="mt-3 flex items-center gap-4">
                @if ($company->logo_path)
                    <img src="{{ route('settings.company.logo') }}" alt="Company logo" class="h-16 w-auto rounded border border-gray-200 bg-white object-contain">
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                    class="text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700">
            </div>
            @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Invoice defaults</h2>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="default_language" class="block text-sm font-medium text-gray-700">Default language</label>
                    <select id="default_language" name="default_language" class="{{ $input }}">
                        @foreach ($languages as $code => $label)
                            <option value="{{ $code }}" @selected(old('default_language', $company->default_language) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="default_currency" class="block text-sm font-medium text-gray-700">Default currency</label>
                    <select id="default_currency" name="default_currency" class="{{ $input }}">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}" @selected(old('default_currency', $company->default_currency) === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="default_tax_rate" class="block text-sm font-medium text-gray-700">Default tax rate (%)</label>
                    <input type="number" min="0" max="100" id="default_tax_rate" name="default_tax_rate" value="{{ old('default_tax_rate', $company->default_tax_rate) }}" class="{{ $input }}">
                    @error('default_tax_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payment_terms_days" class="block text-sm font-medium text-gray-700">Payment terms (days)</label>
                    <input type="number" min="0" max="365" id="payment_terms_days" name="payment_terms_days" value="{{ old('payment_terms_days', $company->payment_terms_days) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="tax_display" class="block text-sm font-medium text-gray-700">Tax display</label>
                    <select id="tax_display" name="tax_display" class="{{ $input }}">
                        <option value="line" @selected(old('tax_display', $company->tax_display ?? 'line') === 'line')>Per line</option>
                        <option value="invoice" @selected(old('tax_display', $company->tax_display ?? 'line') === 'invoice')>Per invoice</option>
                    </select>
                </div>
                <div>
                    <label for="paper_size" class="block text-sm font-medium text-gray-700">Paper size</label>
                    <select id="paper_size" name="paper_size" class="{{ $input }}">
                        @foreach (config('finance.paper_sizes') as $size)
                            <option value="{{ $size }}" @selected(old('paper_size', $company->paper_size ?? 'A4') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="invoice_number_prefix" class="block text-sm font-medium text-gray-700">Invoice number prefix</label>
                    <input type="text" id="invoice_number_prefix" name="invoice_number_prefix" value="{{ old('invoice_number_prefix', $company->invoice_number_prefix) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="invoice_number_next" class="block text-sm font-medium text-gray-700">Next invoice number (start)</label>
                    <input type="number" min="1" id="invoice_number_next" name="invoice_number_next" value="{{ old('invoice_number_next', $company->invoice_number_next) }}" class="{{ $input }}">
                    @error('invoice_number_next')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="invoice_footer_text" class="block text-sm font-medium text-gray-700">Invoice footer text</label>
                    <textarea id="invoice_footer_text" name="invoice_footer_text" rows="3" class="{{ $input }}">{{ old('invoice_footer_text', $company->invoice_footer_text) }}</textarea>
                </div>
            </div>
        </section>

        <div>
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Save company profile</button>
        </div>
    </form>
</x-layouts.app>
