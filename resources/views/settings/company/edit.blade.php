<x-layouts.app :title="__('settings.company_section')">
    <x-page-heading :title="__('settings.company_section')" :subtitle="__('settings.company_desc')" />

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6"
          x-data="{ tab: sessionStorage.getItem('companyTab') || 'info' }"
          x-init="$watch('tab', v => sessionStorage.setItem('companyTab', v))">
        @csrf
        @method('PUT')

        {{-- Tabs --}}
        <div class="flex gap-1 rounded-xl bg-black/[0.04] dark:bg-white/[0.06] p-0.5 text-sm">
            @foreach (['info' => 'company_tab_info', 'design' => 'company_tab_design', 'mail' => 'company_tab_mail'] as $key => $lk)
                <button type="button" @click="tab = '{{ $key }}'"
                        class="flex-1 rounded-lg px-3 py-1.5 font-medium transition"
                        :class="tab === '{{ $key }}' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-accent'">
                    {{ __('settings.' . $lk) }}
                </button>
            @endforeach
        </div>

        {{-- ============================ INFO ============================ --}}
        <div x-show="tab === 'info'" x-cloak class="space-y-6">
        {{-- Identity --}}
        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_identity_heading') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.company_name') }}
                    <input type="text" name="company_name" value="{{ old('company_name', $s->company_name) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    @error('company_name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.company_address') }}
                    <textarea name="company_address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">{{ old('company_address', $s->company_address) }}</textarea>
                    @error('company_address')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_email') }}
                    <input type="email" name="company_email" value="{{ old('company_email', $s->company_email) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    @error('company_email')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_phone') }}
                    <input type="text" name="company_phone" value="{{ old('company_phone', $s->company_phone) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_tax_id') }}
                    <input type="text" name="company_tax_id" value="{{ old('company_tax_id', $s->company_tax_id) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_vat_id') }}
                    <input type="text" name="company_vat_id" value="{{ old('company_vat_id', $s->company_vat_id) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.company_website') }}
                    <input type="url" name="company_website" value="{{ old('company_website', $s->company_website) }}" placeholder="https://…" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
            </div>
        </div>

        {{-- Contact persons --}}
        <div class="ll-card" x-data="{ rows: {{ Illuminate\Support\Js::from(old('company_contacts', $s->company_contacts ?: [])) }} }">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_contacts_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.company_contacts_hint') }}</p>
            <div class="mt-4 space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-start">
                        <input type="text" :name="`company_contacts[${i}][name]`" x-model="row.name" placeholder="{{ __('settings.company_contact_name') }}" class="sm:col-span-3 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        <input type="text" :name="`company_contacts[${i}][role]`" x-model="row.role" placeholder="{{ __('settings.company_contact_role') }}" class="sm:col-span-3 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        <input type="text" :name="`company_contacts[${i}][email]`" x-model="row.email" placeholder="{{ __('settings.company_contact_email') }}" class="sm:col-span-3 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        <input type="text" :name="`company_contacts[${i}][phone]`" x-model="row.phone" placeholder="{{ __('settings.company_contact_phone') }}" class="sm:col-span-2 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        <button type="button" @click="rows.splice(i, 1)" class="sm:col-span-1 flex h-9 items-center justify-center rounded-md text-gray-400 hover:bg-red-500/10 hover:text-red-500" aria-label="{{ __('common.delete') }}"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                </template>
            </div>
            <button type="button" @click="rows.push({ name: '', role: '', email: '', phone: '' })" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline"><x-icon name="plus" class="h-4 w-4" /> {{ __('settings.company_contact_add') }}</button>
        </div>

        {{-- Bank --}}
        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_bank_heading') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_bank_name') }}
                    <input type="text" name="company_bank_name" value="{{ old('company_bank_name', $s->company_bank_name) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_iban') }}
                    <input type="text" name="company_iban" value="{{ old('company_iban', $s->company_iban) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.company_bic') }}
                    <input type="text" name="company_bic" value="{{ old('company_bic', $s->company_bic) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
            </div>
        </div>

        {{-- Invoice defaults --}}
        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_invoice_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.company_invoice_hint') }}</p>
            {{-- Numbering format + start are LOCKED once the current year has invoices
                 (GoBD: the running sequence must not change mid-year). The check reads the
                 zero-knowledge invoice store client-side after the vault unlocks. --}}
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2"
                 x-data="{ locked: false, async check() {
                     try {
                         if (! this.$store.vault?.unlocked || ! window.LLInvoicesStore) { this.locked = false; return; }
                         if (! window.LLInvoicesStore.loaded) await window.LLInvoicesStore.load();
                         const y = String(new Date().getFullYear());
                         this.locked = (window.LLInvoicesStore.data.invoices || []).some((i) => ! i.trashed && String(i.issueDate || '').slice(0, 4) === y);
                     } catch (e) { this.locked = false; }
                 } }"
                 x-init="check(); $watch('$store.vault.unlocked', () => check())">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_number_format') }}
                    <input type="text" name="invoice_number_format" value="{{ old('invoice_number_format', $s->invoice_number_format ?: 'YYYY-NNNN') }}" placeholder="YYYY-NNNN" :readonly="locked" :class="locked && 'opacity-60 cursor-not-allowed'" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ __('settings.invoice_number_format_hint') }}</span>
                    @error('invoice_number_format')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_next_number') }}
                    <input type="number" name="invoice_next_number" value="{{ old('invoice_next_number', $s->invoice_next_number) }}" min="1" placeholder="1" :readonly="locked" :class="locked && 'opacity-60 cursor-not-allowed'" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ __('settings.invoice_next_number_hint') }}</span>
                    @error('invoice_next_number')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <template x-if="locked">
                  <x-alert variant="info" class="sm:col-span-2">{{ __('settings.invoice_numbering_locked') }}</x-alert>
                </template>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_default_vat_rate') }}
                    <input type="number" step="0.01" name="invoice_default_vat_rate" value="{{ old('invoice_default_vat_rate', $s->invoice_default_vat_rate ?: '19.00') }}" min="0" max="100" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    @error('invoice_default_vat_rate')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.invoice_vat_scheme') }}
                    <input type="hidden" name="invoice_vat_ist_present" value="1">
                    <input type="hidden" name="invoice_vat_ist" value="0">
                    <span class="mt-1 flex items-center gap-2">
                        <input type="checkbox" name="invoice_vat_ist" value="1" @checked(old('invoice_vat_ist', $s->invoice_vat_ist)) class="rounded border-gray-300 dark:border-gray-700 text-gray-900 focus:ring-accent">
                        <span class="text-sm">{{ __('settings.invoice_vat_ist') }}</span>
                    </span>
                    <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ __('settings.invoice_vat_ist_hint') }}</span>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_payment_terms_days') }}
                    <input type="number" name="invoice_payment_terms_days" value="{{ old('invoice_payment_terms_days', $s->invoice_payment_terms_days ?: 14) }}" min="0" max="365" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    @error('invoice_payment_terms_days')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_payment_terms_text') }}
                    <textarea name="invoice_payment_terms_text" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">{{ old('invoice_payment_terms_text', $s->invoice_payment_terms_text) }}</textarea>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_payment_methods') }}
                    <textarea name="invoice_payment_methods" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">{{ old('invoice_payment_methods', $s->invoice_payment_methods) }}</textarea>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.invoice_footer_text') }}
                    <textarea name="invoice_footer_text" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">{{ old('invoice_footer_text', $s->invoice_footer_text) }}</textarea>
                </label>
            </div>
        </div>

        </div>{{-- /info --}}

        {{-- ============================ DESIGN ============================ --}}
        <div x-show="tab === 'design'" x-cloak class="space-y-6">
        {{-- Design --}}
        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.invoice_design_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.invoice_design_hint') }}</p>
            @php
                $stored = $s->invoice_template === 'schlicht' ? 'elegant' : $s->invoice_template;
                $tpl = old('invoice_template', $stored ?: 'editorial');
            @endphp
            <div class="mt-4" x-data="{ tpl: @js($tpl) }">
                <span class="block text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_template') }}</span>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach (['editorial', 'modern', 'elegant', 'klassisch'] as $opt)
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
            <div class="mt-4">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_font') }}
                    <select name="invoice_font" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        <option value="">{{ __('settings.invoice_font_default') }}</option>
                        @foreach (config('fonts.families') as $css => $label)
                            <option value="{{ $css }}" style="font-family:{{ $css }}" @selected(old('invoice_font', $s->invoice_font) === $css)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-gray-400">{{ __('settings.invoice_font_hint') }}</span>
                </label>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_accent_color') }}
                    <span class="mt-1 flex items-center gap-2">
                        <input type="color" name="invoice_accent_color" value="{{ old('invoice_accent_color', $s->invoice_accent_color ?: '#111827') }}" class="h-9 w-14 rounded border border-gray-300 dark:border-gray-700 bg-white p-0.5">
                        <input type="text" value="{{ old('invoice_accent_color', $s->invoice_accent_color ?: '#111827') }}" oninput="this.previousElementSibling.value=this.value" class="block w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    </span>
                    @error('invoice_accent_color')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_heading_color') }}
                    <span class="mt-1 flex items-center gap-2">
                        <input type="color" name="invoice_heading_color" value="{{ old('invoice_heading_color', $s->invoice_heading_color ?: '#6b7280') }}" class="h-9 w-14 rounded border border-gray-300 dark:border-gray-700 bg-white p-0.5">
                        <input type="text" value="{{ old('invoice_heading_color', $s->invoice_heading_color ?: '#6b7280') }}" oninput="this.previousElementSibling.value=this.value" class="block w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    </span>
                    @error('invoice_heading_color')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        {{-- Logo --}}
        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.company_logo_heading') }}</h2>
            <div class="mt-4 flex items-center gap-4">
                @if ($s->company_logo_path)
                    <img src="{{ route('settings.company.logo') }}" alt="logo" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white p-1">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 dark:border-gray-700 text-gray-900 focus:ring-accent">
                        {{ __('settings.company_logo_remove') }}
                    </label>
                @endif
            </div>
            <input type="file" name="logo" accept="image/*" class="mt-3 block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700">
            @error('logo')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </div>

        </div>{{-- /design --}}

        {{-- ============================ MAIL ============================ --}}
        <div x-show="tab === 'mail'" x-cloak class="space-y-6">
        {{-- Dedicated invoice mail server (for sending invoices by e-mail) --}}
        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.invoice_mail_heading') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.invoice_mail_desc') }}</p>
            <label class="mt-4 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="invoice_mail_enabled" value="0">
                <input type="checkbox" name="invoice_mail_enabled" value="1" @checked(old('invoice_mail_enabled', $s->invoice_mail_enabled)) class="rounded border-gray-300 dark:border-gray-700 text-gray-900 focus:ring-accent">
                {{ __('settings.invoice_mail_enabled') }}
            </label>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.invoice_smtp_host') }}
                    <input type="text" name="invoice_smtp_host" value="{{ old('invoice_smtp_host', $s->invoice_smtp_host) }}" placeholder="smtp.example.com" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_smtp_port') }}
                    <input type="number" name="invoice_smtp_port" value="{{ old('invoice_smtp_port', $s->invoice_smtp_port) }}" placeholder="587" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent tabular-nums">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_smtp_encryption') }}
                    <select name="invoice_smtp_encryption" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                        @foreach(['tls' => 'STARTTLS (TLS)', 'ssl' => 'SSL', 'none' => __('settings.invoice_smtp_enc_none')] as $val => $label)
                            <option value="{{ $val }}" @selected(old('invoice_smtp_encryption', $s->invoice_smtp_encryption ?: 'tls') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_smtp_username') }}
                    <input type="text" name="invoice_smtp_username" value="{{ old('invoice_smtp_username', $s->invoice_smtp_username) }}" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_smtp_password') }}
                    <input type="password" name="invoice_smtp_password" value="" placeholder="{{ $s->invoice_smtp_password ? '••••••••' : '' }}" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    <span class="mt-1 block text-xs text-gray-400">{{ __('settings.invoice_smtp_password_hint') }}</span>
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_from_email') }}
                    <input type="email" name="invoice_from_email" value="{{ old('invoice_from_email', $s->invoice_from_email) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_from_name') }}
                    <input type="text" name="invoice_from_name" value="{{ old('invoice_from_name', $s->invoice_from_name) }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">{{ __('settings.invoice_mail_subject') }}
                    <input type="text" name="invoice_mail_subject" value="{{ old('invoice_mail_subject', $s->invoice_mail_subject) }}" placeholder="{{ __('settings.invoice_mail_subject_ph') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <div class="sm:col-span-2">
                    <span class="block text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_mail_body') }}</span>
                    @include('settings._wysiwyg', ['name' => 'invoice_mail_body', 'html' => \App\Support\HtmlMailSanitizer::clean(old('invoice_mail_body', $s->invoice_mail_body ?? ''))])
                </div>
                <div class="sm:col-span-2">
                    <span class="block text-sm text-gray-700 dark:text-gray-300">{{ __('settings.invoice_mail_signature') }}</span>
                    <span class="block text-xs text-gray-400">{{ __('settings.invoice_mail_signature_hint') }}</span>
                    @include('settings._wysiwyg', ['name' => 'invoice_mail_signature', 'html' => \App\Support\HtmlMailSanitizer::clean(old('invoice_mail_signature', $s->invoice_mail_signature ?? ''))])
                </div>
            </div>

            {{-- Placeholders --}}
            <div class="mt-4 rounded-lg bg-black/[0.03] dark:bg-white/[0.04] px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium text-gray-600 dark:text-gray-300">{{ __('settings.invoice_mail_placeholders') }}:</span>
                @foreach (['number', 'customer', 'company', 'date', 'due', 'total', 'currency'] as $ph)
                    <code class="mx-0.5 rounded bg-black/5 dark:bg-white/10 px-1 py-0.5">:{{ $ph }}</code>
                @endforeach
            </div>

            {{-- Test send --}}
            <div class="mt-4 flex flex-wrap items-center gap-3" x-data="{ to: '', busy: false, msg: '', ok: false,
                async test() {
                    this.busy = true; this.msg = '';
                    try {
                        const fd = new FormData(); if (this.to) fd.append('to', this.to);
                        const r = await fetch('{{ route('invoices.mail-test') }}', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: fd });
                        this.ok = r.ok; this.msg = r.ok ? @js(__('settings.invoice_mail_test_ok')) : (r.status === 501 ? @js(__('settings.invoice_mail_test_501')) : @js(__('settings.invoice_mail_test_failed')));
                    } catch (e) { this.ok = false; this.msg = @js(__('settings.invoice_mail_test_failed')); }
                    this.busy = false;
                } }">
                <input type="email" x-model="to" placeholder="{{ __('settings.invoice_mail_test_to_ph') }}" class="w-64 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                <x-button variant="secondary" type="button" ::disabled="busy" @click="test()">
                    <span x-show="! busy">{{ __('settings.invoice_mail_test') }}</span>
                    <span x-show="busy">{{ __('settings.invoice_mail_test_sending') }}</span>
                </x-button>
                <span x-show="msg" x-text="msg" :class="ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-xs"></span>
                <span class="text-xs text-gray-400">{{ __('settings.invoice_mail_test_note') }}</span>
            </div>
        </div>
        </div>{{-- /mail --}}

        <div class="flex justify-end">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
