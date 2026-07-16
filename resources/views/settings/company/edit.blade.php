<x-layouts.app :title="__('settings.company_section')">
    <x-page-heading :title="__('settings.company_section')" :subtitle="__('settings.company_desc')" />

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        {{-- Identity --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_identity_heading') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.company_name') }}
                    <input type="text" name="company_name" value="{{ old('company_name', $s->company_name) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('company_name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.company_address') }}
                    <textarea name="company_address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">{{ old('company_address', $s->company_address) }}</textarea>
                    @error('company_address')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_email') }}
                    <input type="email" name="company_email" value="{{ old('company_email', $s->company_email) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('company_email')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_phone') }}
                    <input type="text" name="company_phone" value="{{ old('company_phone', $s->company_phone) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_tax_id') }}
                    <input type="text" name="company_tax_id" value="{{ old('company_tax_id', $s->company_tax_id) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_vat_id') }}
                    <input type="text" name="company_vat_id" value="{{ old('company_vat_id', $s->company_vat_id) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
            </div>
        </div>

        {{-- Bank --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_bank_heading') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_bank_name') }}
                    <input type="text" name="company_bank_name" value="{{ old('company_bank_name', $s->company_bank_name) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_iban') }}
                    <input type="text" name="company_iban" value="{{ old('company_iban', $s->company_iban) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_bic') }}
                    <input type="text" name="company_bic" value="{{ old('company_bic', $s->company_bic) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
            </div>
        </div>

        {{-- Invoice defaults --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_invoice_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.company_invoice_hint') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_number_format') }}
                    <input type="text" name="invoice_number_format" value="{{ old('invoice_number_format', $s->invoice_number_format ?: 'YYYY-NNNN') }}" placeholder="YYYY-NNNN" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ __('settings.invoice_number_format_hint') }}</span>
                    @error('invoice_number_format')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_next_number') }}
                    <input type="number" name="invoice_next_number" value="{{ old('invoice_next_number', $s->invoice_next_number) }}" min="1" placeholder="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ __('settings.invoice_next_number_hint') }}</span>
                    @error('invoice_next_number')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_default_vat_rate') }}
                    <input type="number" step="0.01" name="invoice_default_vat_rate" value="{{ old('invoice_default_vat_rate', $s->invoice_default_vat_rate ?: '19.00') }}" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('invoice_default_vat_rate')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_payment_terms_days') }}
                    <input type="number" name="invoice_payment_terms_days" value="{{ old('invoice_payment_terms_days', $s->invoice_payment_terms_days ?: 14) }}" min="0" max="365" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    @error('invoice_payment_terms_days')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_payment_terms_text') }}
                    <textarea name="invoice_payment_terms_text" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">{{ old('invoice_payment_terms_text', $s->invoice_payment_terms_text) }}</textarea>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_payment_methods') }}
                    <textarea name="invoice_payment_methods" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">{{ old('invoice_payment_methods', $s->invoice_payment_methods) }}</textarea>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.invoice_footer_text') }}
                    <textarea name="invoice_footer_text" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">{{ old('invoice_footer_text', $s->invoice_footer_text) }}</textarea>
                </label>
            </div>
        </div>

        {{-- Design --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.invoice_design_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.invoice_design_hint') }}</p>
            @php $tpl = old('invoice_template', in_array($s->invoice_template, ['elegant', 'schlicht'], true) ? 'elegant' : 'modern'); @endphp
            <div class="mt-4" x-data="{ tpl: @js($tpl) }">
                <span class="block text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_template') }}</span>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach (['modern', 'elegant'] as $opt)
                        <label class="relative flex cursor-pointer flex-col rounded-lg border p-3 text-sm"
                               :class="tpl === @js($opt) ? 'border-gray-900 dark:border-gray-100 ring-1 ring-gray-900 dark:ring-gray-100' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" name="invoice_template" value="{{ $opt }}" x-model="tpl" class="sr-only">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('settings.invoice_template_' . $opt) }}</span>
                            <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.invoice_template_' . $opt . '_hint') }}</span>
                        </label>
                    @endforeach
                </div>
                @error('invoice_template')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_accent_color') }}
                    <span class="mt-1 flex items-center gap-2">
                        <input type="color" name="invoice_accent_color" value="{{ old('invoice_accent_color', $s->invoice_accent_color ?: '#111827') }}" class="h-9 w-14 rounded border border-gray-300 dark:border-gray-700 bg-white p-0.5">
                        <input type="text" value="{{ old('invoice_accent_color', $s->invoice_accent_color ?: '#111827') }}" oninput="this.previousElementSibling.value=this.value" class="block w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </span>
                    @error('invoice_accent_color')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_heading_color') }}
                    <span class="mt-1 flex items-center gap-2">
                        <input type="color" name="invoice_heading_color" value="{{ old('invoice_heading_color', $s->invoice_heading_color ?: '#6b7280') }}" class="h-9 w-14 rounded border border-gray-300 dark:border-gray-700 bg-white p-0.5">
                        <input type="text" value="{{ old('invoice_heading_color', $s->invoice_heading_color ?: '#6b7280') }}" oninput="this.previousElementSibling.value=this.value" class="block w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </span>
                    @error('invoice_heading_color')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        {{-- Logo --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_logo_heading') }}</h2>
            <div class="mt-4 flex items-center gap-4">
                @if ($s->company_logo_path)
                    <img src="{{ route('settings.company.logo') }}" alt="logo" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white p-1">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 dark:border-gray-700 text-gray-900 focus:ring-gray-500">
                        {{ __('settings.company_logo_remove') }}
                    </label>
                @endif
            </div>
            <input type="file" name="logo" accept="image/*" class="mt-3 block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700">
            @error('logo')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </div>

        <div class="flex justify-end">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
