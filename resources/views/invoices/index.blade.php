@php $s = \App\Models\AppSettings::current(); @endphp
<x-layouts.app :title="__('invoices.title')">
  <div x-data="invoices({
        token: '{{ csrf_token() }}',
        company: @js([
            'name' => $s->company_name,
            'address' => $s->company_address,
            'email' => $s->company_email,
            'phone' => $s->company_phone,
            'tax_id' => $s->company_tax_id,
            'vat_id' => $s->company_vat_id,
            'iban' => $s->company_iban,
            'bic' => $s->company_bic,
            'bank_name' => $s->company_bank_name,
            'logo' => $s->company_logo_path ? route('settings.company.logo') : null,
            'number_format' => $s->invoice_number_format ?: 'YYYY-NNNN',
            'next_number' => $s->invoice_next_number,
            'default_vat_rate' => $s->invoice_default_vat_rate,
            'payment_terms_days' => $s->invoice_payment_terms_days,
            'footer_text' => $s->invoice_footer_text,
            'payment_terms_text' => $s->invoice_payment_terms_text,
            'payment_methods' => $s->invoice_payment_methods,
            'accent' => $s->invoice_accent_color ?: '#111827',
            'heading' => $s->invoice_heading_color ?: '#6b7280',
            'template' => $s->invoice_template ?: 'editorial',
            'currency' => 'EUR',
        ]),
        labelsByLang: @js(['de' => __('invoices', [], 'de'), 'en' => __('invoices', [], 'en')]),
     }, {
        deleteConfirm: @js(__('invoices.delete_confirm')),
        statusDraft: @js(__('invoices.status_draft')),
        statusSent: @js(__('invoices.status_sent')),
        statusPaid: @js(__('invoices.status_paid')),
        csvImported: @js(__('invoices.csv_imported')),
        csvBadFormat: @js(__('invoices.csv_bad_format')),
     })">

    {{-- Zero-knowledge gate: invoices decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 max-w-md ll-card !p-8 text-center">
        <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
           x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <button type="button" @click="$dispatch('vault-panel')"
            class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md ll-accent px-4 py-2 text-sm font-medium hover:brightness-105">
          <x-icon name="lock-open" class="h-4 w-4" />
          <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
        </button>
      </div>
    </template>

    <template x-if="state === 'error'">
      <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('invoices.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        {{-- ===================== LIST ===================== --}}
        <div x-show="view === 'list'">
          <x-page-heading :title="__('invoices.title')">
            <x-slot:actions>
              <x-button variant="primary" @click="newInvoice()"><x-icon name="plus" class="mr-1.5 h-4 w-4" />{{ __('invoices.new') }}</x-button>
            </x-slot:actions>
          </x-page-heading>

          @unless ($s->company_name)
            <p class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
              {{ __('invoices.company_missing') }} <a href="{{ route('settings.company.edit') }}" class="font-medium underline">{{ __('settings.company_section') }}</a>
            </p>
          @endunless

          <div class="mt-6 flex flex-wrap items-center gap-3">
            <input type="search" x-model.debounce.250ms="query" placeholder="{{ __('invoices.search') }}" class="w-full max-w-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
            <select x-model="filterStatus" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
              <option value="">{{ __('invoices.filter_all') }}</option>
              <option value="draft">{{ __('invoices.status_draft') }}</option>
              <option value="sent">{{ __('invoices.status_sent') }}</option>
              <option value="paid">{{ __('invoices.status_paid') }}</option>
            </select>
          </div>

          <template x-if="! filtered.length">
            <div class="mx-auto mt-10 flex max-w-md flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
              <x-icon name="document-text" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
              <p class="mt-3 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.empty_title') }}</p>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('invoices.empty_hint') }}</p>
            </div>
          </template>

          <div x-show="filtered.length" class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
              <thead class="bg-gray-50 dark:bg-gray-900/50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <tr>
                  <th class="px-4 py-2">{{ __('invoices.col_number') }}</th>
                  <th class="px-4 py-2">{{ __('invoices.col_customer') }}</th>
                  <th class="px-4 py-2">{{ __('invoices.col_date') }}</th>
                  <th class="px-4 py-2 text-right">{{ __('invoices.col_total') }}</th>
                  <th class="px-4 py-2">{{ __('invoices.col_status') }}</th>
                  <th class="px-4 py-2 text-right">{{ __('invoices.col_actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <template x-for="inv in filtered" :key="inv.id">
                  <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100 tabular-nums" x-text="inv.number || @js(__('invoices.draft_label'))"></td>
                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300" x-text="inv.customer?.name || '—'"></td>
                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 tabular-nums" x-text="inv.issueDate"></td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(computeTotals(inv).gross, inv.currency)"></td>
                    <td class="px-4 py-2">
                      <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300" x-text="statusLabel(inv.status)"></span>
                    </td>
                    <td class="px-4 py-2">
                      <div class="flex items-center justify-end gap-1">
                        <button type="button" @click="open(inv)" title="{{ __('common.edit') }}" class="rounded p-1.5 text-gray-500 hover:bg-accent/5"><x-icon name="pencil" class="h-4 w-4" /></button>
                        <button type="button" @click="printInvoice(inv)" title="{{ __('invoices.print') }}" class="rounded p-1.5 text-gray-500 hover:bg-accent/5"><x-icon name="printer" class="h-4 w-4" /></button>
                        <button type="button" @click="trash(inv)" title="{{ __('invoices.trash') }}" class="rounded p-1.5 text-gray-500 hover:bg-accent/5"><x-icon name="trash" class="h-4 w-4" /></button>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        {{-- ===================== EDITOR ===================== --}}
        <template x-if="view === 'edit' && current">
        <div x-cloak @input="saveSoon()">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <button type="button" @click="backToList()" class="rounded-lg p-2 text-gray-500 hover:bg-accent/5"><x-icon name="arrow-left" class="h-4 w-4" /></button>
              <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums" x-text="current?.number || @js(__('invoices.status_draft'))"></h1>
              <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300" x-text="statusLabel(current?.status)"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <x-button variant="secondary" @click="printInvoice(current)"><x-icon name="printer" class="mr-1.5 h-4 w-4" />{{ __('invoices.print') }}</x-button>
              <x-button variant="secondary" x-show="current?.status === 'draft'" @click="finalize(current)">{{ __('invoices.finalize') }}</x-button>
              <x-button variant="secondary" x-show="current?.status === 'sent'" @click="markPaid(current)">{{ __('invoices.mark_paid') }}</x-button>
            </div>
          </div>

          <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Customer --}}
            <div class="ll-card">
              <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.customer') }}</h2>
                <button type="button" @click="openCustomerPicker()" class="text-xs font-medium text-gray-700 dark:text-gray-300 underline">{{ __('invoices.choose_customer') }}</button>
              </div>
              <div class="mt-3 space-y-2">
                <input type="text" x-model="current.customer.name" placeholder="{{ __('invoices.customer_name') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                <input type="text" x-model="current.customer.attn" placeholder="{{ __('invoices.attn') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                <textarea x-model="current.customer.address" rows="3" placeholder="{{ __('invoices.customer_address') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent"></textarea>
                <input type="email" x-model="current.customer.email" placeholder="{{ __('invoices.customer_email') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                <input type="text" x-model="current.customer.vatId" placeholder="{{ __('invoices.customer_vat') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
              </div>
            </div>

            {{-- Dates --}}
            <div class="ll-card">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.col_date') }}</h2>
              <div class="mt-3 space-y-3">
                <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.issue_date') }}
                  <input type="date" x-model="current.issueDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.due_date') }}
                  <input type="date" x-model="current.dueDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.language') }}
                    <select x-model="current.lang" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                      <option value="de">Deutsch</option>
                      <option value="en">English</option>
                    </select>
                  </label>
                  <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.currency') }}
                    <select x-model="current.currency" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
                      <template x-for="c in currencyOptions" :key="c"><option :value="c" x-text="c"></option></template>
                    </select>
                  </label>
                </div>
              </div>
            </div>

            {{-- Totals summary --}}
            <div class="ll-card">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.gross') }}</h2>
              <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ __('invoices.net') }}</dt><dd class="tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(totals.net)"></dd></div>
                <template x-for="rate in vatRatesOf(current)" :key="rate">
                  <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400" x-text="@js(__('invoices.vat_at')).replace(':rate', rate)"></dt><dd class="tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(totals.vatByRate[rate])"></dd></div>
                </template>
                <div class="flex justify-between border-t border-gray-200 dark:border-gray-800 pt-1.5 font-semibold"><dt class="text-gray-900 dark:text-gray-100">{{ __('invoices.gross') }}</dt><dd class="tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(totals.gross)"></dd></div>
              </dl>
            </div>
          </div>

          {{-- Line items --}}
          <div class="mt-6 ll-card">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.lines') }}</h2>
              <div class="flex items-center gap-4">
                <button type="button" @click="$refs.csv.click()" :title="'{{ __('invoices.csv_hint') }}'" class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 underline"><x-icon name="arrow-up-tray" class="h-3.5 w-3.5" />{{ __('invoices.csv_import') }}</button>
                <input x-ref="csv" type="file" accept=".csv,text/csv" class="hidden" @change="importClockify($event.target.files); $event.target.value = ''">
                <button type="button" @click="addLine()" class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 underline"><x-icon name="plus" class="h-3.5 w-3.5" />{{ __('invoices.add_line') }}</button>
              </div>
            </div>
            <div class="mt-3 overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                  <tr>
                    <th class="py-1 pr-2">{{ __('invoices.line_desc') }}</th>
                    <th class="py-1 px-2 w-20 text-right">{{ __('invoices.line_qty') }}</th>
                    <th class="py-1 px-2 w-24">{{ __('invoices.line_unit') }}</th>
                    <th class="py-1 px-2 w-28 text-right">{{ __('invoices.line_price') }}</th>
                    <th class="py-1 px-2 w-20 text-right">{{ __('invoices.line_vat') }}</th>
                    <th class="py-1 px-2 w-28 text-right">{{ __('invoices.net') }}</th>
                    <th class="py-1 pl-2 w-8"></th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="(l, i) in current.lines" :key="i">
                    <tr>
                      <td class="py-1 pr-2"><input type="text" x-model="l.desc" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2"><input type="number" step="0.01" x-model.number="l.qty" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2"><input type="text" x-model="l.unit" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2"><input type="number" step="0.01" x-model.number="l.unitPrice" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2"><input type="number" step="0.01" x-model.number="l.vatRate" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="fmtMoney(lineNet(l))"></td>
                      <td class="py-1 pl-2 text-right"><button type="button" @click="removeLine(i)" title="{{ __('invoices.remove') }}" class="rounded p-1 text-gray-400 hover:bg-accent/5 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          {{-- Note / footer --}}
          <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.note') }}
              <textarea x-model="current.note" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent"></textarea>
            </label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.footer') }}
              <textarea x-model="current.footer" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent"></textarea>
            </label>
          </div>

          <div class="mt-6 flex justify-end">
            <button type="button" @click="remove(current)" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950"><x-icon name="trash" class="h-4 w-4" />{{ __('invoices.delete') }}</button>
          </div>
        </div>
        </template>

        {{-- ===================== CUSTOMER PICKER ===================== --}}
        <div x-show="customerPicker" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeCustomerPicker()">
          <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeCustomerPicker()"></div>
          <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.picker_title') }}</h2>
            <input type="search" x-model="custQuery" placeholder="{{ __('invoices.picker_search') }}" class="mt-3 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
            <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
              <template x-if="! custSuggestions().length">
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('invoices.picker_empty') }}</p>
              </template>
              <template x-for="c in custSuggestions()" :key="c.id">
                <button type="button" @click="pickCustomer(c)" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-accent/5">
                  <span class="text-sm font-medium text-accent" x-text="_custName(c) || (c.org || '—')"></span>
                </button>
              </template>
            </div>
          </div>
        </div>

        {{-- ===================== PRINT / PDF SHEET ===================== --}}
        {{-- Teleported to <body> so print CSS can hide the app and leave only this. --}}
        <template x-teleport="body">
          <div id="invoice-print" style="background:#fff; color:#1f2937;">
            {{-- ---------- MODERN (accent band + cards) ---------- --}}
            <template x-if="_printing && tpl === 'modern'">
              <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:10.5px; line-height:1.5; color:#1f2937;">
                <div style="color:#fff; padding:22px 16mm 20px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px;" :style="'background:' + company.accent">
                  <div>
                    <template x-if="company.logo"><img :src="company.logo" alt="" style="max-height:40px; margin-bottom:8px;"></template>
                    <div style="font-weight:800; font-size:16px; letter-spacing:-.01em;" x-text="company.name"></div>
                    <div style="opacity:.85; font-size:9.5px; margin-top:2px;" x-text="[company.address ? company.address.replace(/\n/g, ' · ') : '', company.email, company.phone].filter(Boolean).join(' · ')"></div>
                  </div>
                  <div style="text-align:right; white-space:nowrap;">
                    <div style="font-size:26px; font-weight:800; letter-spacing:.02em; line-height:1; text-transform:uppercase;" x-text="pl('print_title')"></div>
                    <div style="opacity:.9; margin-top:4px;" class="tabular-nums" x-text="pl('invoice_number') + ' ' + (_printing.number || '—')"></div>
                  </div>
                </div>
                <div style="padding:22px 16mm 24px;">
                  <div style="display:flex; gap:14px; align-items:stretch;">
                    <div style="flex:1; background:#f5f6fb; border-radius:12px; padding:12px 14px;">
                      <div style="font-size:8px; text-transform:uppercase; letter-spacing:.1em; font-weight:700;" :style="'color:' + company.heading" x-text="pl('bill_to')"></div>
                      <div style="font-weight:700; font-size:12px; margin-top:4px;" x-text="_printing.customer?.name"></div>
                      <div style="color:#4b5563;" x-show="_printing.customer?.attn" x-text="_printing.customer?.attn"></div>
                      <div style="color:#4b5563; white-space:pre-line;" x-text="_printing.customer?.address"></div>
                      <div style="color:#4b5563;" x-show="_printing.customer?.email" x-text="_printing.customer?.email"></div>
                      <div style="color:#4b5563;" x-show="_printing.customer?.vatId" x-text="pl('vat_id_label') + ': ' + _printing.customer?.vatId"></div>
                    </div>
                    <div style="width:200px; background:#f5f6fb; border-radius:12px; padding:12px 14px;">
                      <div style="display:flex; justify-content:space-between; padding:2px 0;"><span :style="'color:' + company.heading" x-text="pl('invoice_date')"></span><span class="tabular-nums" style="font-weight:600;" x-text="_printing.issueDate"></span></div>
                      <div style="display:flex; justify-content:space-between; padding:2px 0;"><span :style="'color:' + company.heading" x-text="pl('due')"></span><span class="tabular-nums" style="font-weight:600;" x-text="_printing.dueDate"></span></div>
                      <div style="display:flex; justify-content:space-between; padding:2px 0;" x-show="company.vat_id"><span :style="'color:' + company.heading" x-text="pl('vat_id_label')"></span><span class="tabular-nums" x-text="company.vat_id"></span></div>
                    </div>
                  </div>
                  <table style="width:100%; margin-top:22px; border-collapse:collapse;">
                    <thead><tr style="text-align:left; font-size:8.5px; text-transform:uppercase; letter-spacing:.07em; font-weight:700;" :style="'color:' + company.heading + '; border-bottom:2px solid ' + company.accent">
                      <th style="padding:0 8px 8px 0;" x-text="pl('line_desc')"></th>
                      <th style="padding:0 8px 8px; text-align:right;" x-text="pl('line_qty')"></th>
                      <th style="padding:0 8px 8px; text-align:right;" x-text="pl('line_price')"></th>
                      <th style="padding:0 0 8px 8px; text-align:right;" x-text="pl('amount')"></th>
                    </tr></thead>
                    <tbody>
                      <template x-for="(l, i) in _printing.lines" :key="i">
                        <tr style="border-bottom:1px solid #eef0f4;">
                          <td style="padding:9px 8px 9px 0; font-weight:500; vertical-align:top;" x-text="l.desc"></td>
                          <td style="padding:9px 8px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums" x-text="fmtQty(l.qty, _printing.lang) + (l.unit ? ' ' + l.unit : '')"></td>
                          <td style="padding:9px 8px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums" x-text="fmtMoney(l.unitPrice, _printing.currency, _printing.lang)"></td>
                          <td style="padding:9px 0 9px 8px; text-align:right; white-space:nowrap; font-weight:600; vertical-align:top;" class="tabular-nums" x-text="fmtMoney(lineNet(l), _printing.currency, _printing.lang)"></td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                  <div style="display:flex; justify-content:flex-end; margin-top:18px;">
                    <div style="width:250px;">
                      <div style="display:flex; justify-content:space-between; padding:3px 12px; color:#6b7280;"><span x-text="pl('subtotal')"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).net, _printing.currency, _printing.lang)"></span></div>
                      <template x-for="rate in vatRatesOf(_printing)" :key="rate">
                        <div style="display:flex; justify-content:space-between; padding:3px 12px; color:#6b7280;"><span x-text="pl('vat_at').replace(':rate', rate)"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).vatByRate[rate], _printing.currency, _printing.lang)"></span></div>
                      </template>
                      <div style="display:flex; justify-content:space-between; padding:10px 12px; margin-top:6px; color:#fff; border-radius:10px; font-weight:800; font-size:13px;" :style="'background:' + company.accent"><span x-text="pl('gross')"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).gross, _printing.currency, _printing.lang)"></span></div>
                    </div>
                  </div>
                  <div style="margin-top:20px;" x-show="_printing.note">
                    <div style="font-size:8px; text-transform:uppercase; letter-spacing:.08em; font-weight:700;" :style="'color:' + company.heading" x-text="pl('notes_heading')"></div>
                    <div style="white-space:pre-line; color:#4b5563; margin-top:2px;" x-text="_printing.note"></div>
                  </div>
                  <div style="margin-top:28px; padding-top:12px; border-top:1px solid #eef0f4; display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; font-size:9px; color:#4b5563;">
                    <div x-show="company.payment_terms_text"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:8px;" :style="'color:' + company.heading" x-text="pl('payment_terms_heading')"></div><div style="white-space:pre-line;" x-text="company.payment_terms_text"></div></div>
                    <div x-show="company.payment_methods"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:8px;" :style="'color:' + company.heading" x-text="pl('payment_methods_heading')"></div><div style="white-space:pre-line;" x-text="company.payment_methods"></div></div>
                    <div x-show="company.bank_name || company.iban"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:8px;" :style="'color:' + company.heading" x-text="pl('bank_details')"></div><div x-text="[company.bank_name, company.iban ? 'IBAN ' + company.iban : '', company.bic ? 'BIC ' + company.bic : ''].filter(Boolean).join(' · ')"></div></div>
                  </div>
                  <div style="margin-top:12px; text-align:center; font-size:9px; color:#6b7280; white-space:pre-line;" x-show="_printing.footer || company.footer_text" x-text="_printing.footer || company.footer_text"></div>
                </div>
              </div>
            </template>

            {{-- ---------- ELEGANT (serif + minimal) ---------- --}}
            <template x-if="_printing && tpl === 'elegant'">
              <div style="font-family:Georgia,'Times New Roman',serif; font-size:10.5px; line-height:1.55; color:#2b2b2b; padding:20mm;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; border-bottom:1px solid #222; padding-bottom:10px;">
                  <div style="font-size:16px; font-weight:700; letter-spacing:.01em;" x-text="company.name"></div>
                  <div style="font-size:17px; letter-spacing:.3em; text-transform:uppercase;" :style="'color:' + company.accent" x-text="pl('print_title')"></div>
                </div>
                <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#777; font-size:8.5px; margin-top:6px; letter-spacing:.02em;" x-text="[company.address ? company.address.replace(/\n/g, ' · ') : '', company.email, company.phone, company.vat_id ? pl('vat_id_label') + ' ' + company.vat_id : ''].filter(Boolean).join(' · ')"></div>
                <div style="display:flex; justify-content:space-between; gap:24px; margin-top:26px;">
                  <div>
                    <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:8px; text-transform:uppercase; letter-spacing:.16em; color:#9a9a9a;" x-text="pl('bill_to')"></div>
                    <div style="font-weight:700; font-size:12.5px; margin-top:3px;" x-text="_printing.customer?.name"></div>
                    <div style="color:#555;" x-show="_printing.customer?.attn" x-text="_printing.customer?.attn"></div>
                    <div style="color:#555; white-space:pre-line;" x-text="_printing.customer?.address"></div>
                    <div style="color:#555;" x-show="_printing.customer?.email" x-text="_printing.customer?.email"></div>
                    <div style="color:#555;" x-show="_printing.customer?.vatId" x-text="pl('vat_id_label') + ' ' + _printing.customer?.vatId"></div>
                  </div>
                  <table style="font-size:10px; border-collapse:collapse; height:fit-content;">
                    <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:right; padding:1px 16px 1px 0; color:#9a9a9a; letter-spacing:.04em;" x-text="pl('invoice_number')"></td><td style="text-align:right; font-weight:700;" class="tabular-nums" x-text="_printing.number || '—'"></td></tr>
                    <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:right; padding:1px 16px 1px 0; color:#9a9a9a; letter-spacing:.04em;" x-text="pl('invoice_date')"></td><td style="text-align:right;" class="tabular-nums" x-text="_printing.issueDate"></td></tr>
                    <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:right; padding:1px 16px 1px 0; color:#9a9a9a; letter-spacing:.04em;" x-text="pl('due')"></td><td style="text-align:right;" class="tabular-nums" x-text="_printing.dueDate"></td></tr>
                  </table>
                </div>
                <table style="width:100%; margin-top:28px; border-collapse:collapse;">
                  <thead><tr style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:left; font-size:8px; text-transform:uppercase; letter-spacing:.14em; color:#9a9a9a; border-bottom:1px solid #cfcfcf;">
                    <th style="padding:0 6px 7px 0; font-weight:600;" x-text="pl('line_desc')"></th>
                    <th style="padding:0 6px 7px; text-align:right; font-weight:600;" x-text="pl('line_qty')"></th>
                    <th style="padding:0 6px 7px; text-align:right; font-weight:600;" x-text="pl('line_price')"></th>
                    <th style="padding:0 0 7px 6px; text-align:right; font-weight:600;" x-text="pl('amount')"></th>
                  </tr></thead>
                  <tbody>
                    <template x-for="(l, i) in _printing.lines" :key="i">
                      <tr style="border-bottom:1px solid #ededed;">
                        <td style="padding:9px 6px 9px 0; vertical-align:top;" x-text="l.desc"></td>
                        <td style="padding:9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums" x-text="fmtQty(l.qty, _printing.lang) + (l.unit ? ' ' + l.unit : '')"></td>
                        <td style="padding:9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums" x-text="fmtMoney(l.unitPrice, _printing.currency, _printing.lang)"></td>
                        <td style="padding:9px 0 9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums" x-text="fmtMoney(lineNet(l), _printing.currency, _printing.lang)"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
                <div style="display:flex; justify-content:flex-end; margin-top:18px;">
                  <table style="min-width:250px; border-collapse:collapse;">
                    <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding:3px 6px; color:#777;" x-text="pl('subtotal')"></td><td style="padding:3px 0 3px 6px; text-align:right;" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).net, _printing.currency, _printing.lang)"></td></tr>
                    <template x-for="rate in vatRatesOf(_printing)" :key="rate"><tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding:3px 6px; color:#777;" x-text="pl('vat_at').replace(':rate', rate)"></td><td style="padding:3px 0 3px 6px; text-align:right;" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).vatByRate[rate], _printing.currency, _printing.lang)"></td></tr></template>
                    <tr style="border-top:1px solid #222;"><td style="padding:7px 6px; letter-spacing:.1em; text-transform:uppercase;" :style="'color:' + company.accent" x-text="pl('gross')"></td><td style="padding:7px 0 7px 6px; text-align:right; font-weight:700; font-size:13px;" :style="'color:' + company.accent" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).gross, _printing.currency, _printing.lang)"></td></tr>
                  </table>
                </div>
                <div style="margin-top:34px; text-align:center; font-style:italic; color:#555; white-space:pre-line;" x-show="_printing.note || _printing.footer || company.footer_text" x-text="_printing.note || _printing.footer || company.footer_text"></div>
                <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; margin-top:20px; padding-top:10px; border-top:1px solid #ededed; text-align:center; font-size:8.5px; color:#8a8a8a; letter-spacing:.02em;" x-text="[company.payment_terms_text, company.payment_methods, company.bank_name, company.iban ? 'IBAN ' + company.iban : '', company.bic ? 'BIC ' + company.bic : ''].filter(Boolean).join(' · ')"></div>
              </div>
            </template>

            {{-- ---------- EDITORIAL (single-ink, accent rule) ---------- --}}
            <template x-if="_printing && tpl === 'editorial'">
              <div class="ie" :style="'--ac:' + company.accent">
                <div class="ie-page">
                  <div class="ie-header">
                    <div class="ie-brand">
                      <template x-if="company.logo"><div class="ie-logo"><img :src="company.logo" alt=""></div></template>
                      <template x-if="! company.logo"><div class="ie-brand-text"><div class="ie-co-name" x-text="company.name"></div></div></template>
                    </div>
                    <div class="ie-doc-meta">
                      <div class="ie-doc-kind" x-text="pl('print_title')"></div>
                      <div class="ie-doc-no num" x-text="_printing.number || '—'"></div>
                    </div>
                  </div>
                  <div class="ie-meta-grid">
                    <div class="ie-meta-cell"><div class="ie-m-lbl" x-text="pl('invoice_date')"></div><div class="ie-m-val num" x-text="_printing.issueDate"></div></div>
                    <div class="ie-meta-cell"><div class="ie-m-lbl" x-text="pl('due')"></div><div class="ie-m-val num" x-text="_printing.dueDate"></div></div>
                    <div class="ie-meta-cell"><div class="ie-m-lbl" x-text="pl('status_label')"></div><div class="ie-m-val"><span class="ie-pill" :class="'ie-' + _printing.status" x-text="statusLabel(_printing.status)"></span></div></div>
                  </div>
                  <div class="ie-parties">
                    <div class="ie-party">
                      <div class="ie-p-lbl" x-text="pl('invoice_from')"></div>
                      <div class="ie-p-name" x-text="company.name"></div>
                      <template x-for="(ln, i) in [...(company.address || '').split('\n'), [company.email, company.phone].filter(Boolean).join(' · '), company.vat_id ? pl('vat_id_label') + ' ' + company.vat_id : ''].filter(Boolean)" :key="i"><div class="ie-p-line" x-text="ln"></div></template>
                    </div>
                    <div class="ie-party">
                      <div class="ie-p-lbl" x-text="pl('bill_to')"></div>
                      <div class="ie-p-name" x-text="_printing.customer?.name"></div>
                      <template x-for="(ln, i) in [_printing.customer?.attn, ...((_printing.customer?.address || '').split('\n')), _printing.customer?.email, _printing.customer?.vatId ? pl('vat_id_label') + ' ' + _printing.customer.vatId : ''].filter(Boolean)" :key="i"><div class="ie-p-line" x-text="ln"></div></template>
                    </div>
                  </div>
                  <div class="ie-tbl-wrap">
                    <table>
                      <thead><tr>
                        <th x-text="pl('line_desc')"></th>
                        <th class="r" x-text="pl('line_qty')"></th>
                        <th class="r" x-text="pl('line_price')"></th>
                        <th class="r" x-text="pl('amount')"></th>
                      </tr></thead>
                      <tbody>
                        <template x-for="(l, i) in _printing.lines" :key="i">
                          <tr>
                            <td><div class="ie-d-title" x-text="l.desc"></div></td>
                            <td class="r num" x-text="fmtQty(l.qty, _printing.lang) + (l.unit ? ' ' + l.unit : '')"></td>
                            <td class="r num" x-text="fmtMoney(l.unitPrice, _printing.currency, _printing.lang)"></td>
                            <td class="r num ie-amt" x-text="fmtMoney(lineNet(l), _printing.currency, _printing.lang)"></td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                  <div class="ie-sum-area"><div class="ie-sum">
                    <div class="ie-sr"><span class="l" x-text="pl('subtotal')"></span><span class="v num" x-text="fmtMoney(computeTotals(_printing).net, _printing.currency, _printing.lang)"></span></div>
                    <template x-for="rate in vatRatesOf(_printing)" :key="rate">
                      <div class="ie-sr"><span class="l" x-text="pl('vat_at').replace(':rate', rate)"></span><span class="v num" x-text="fmtMoney(computeTotals(_printing).vatByRate[rate], _printing.currency, _printing.lang)"></span></div>
                    </template>
                    <div class="ie-grand"><span class="ie-gl" x-text="pl('gross')"></span><span class="ie-gv num" x-text="fmtMoney(computeTotals(_printing).gross, _printing.currency, _printing.lang)"></span></div>
                  </div></div>
                  <div class="ie-notice" x-show="_printing.footer || company.footer_text" x-text="_printing.footer || company.footer_text"></div>
                  <div class="ie-notes-area" x-show="_printing.note">
                    <div class="ie-n-lbl" x-text="pl('notes_heading')"></div>
                    <div class="ie-note-text" x-text="_printing.note"></div>
                  </div>
                  <div class="ie-pay-area" x-show="company.payment_terms_text || company.payment_methods || company.bank_name || company.iban">
                    <div class="ie-pay-grid">
                      <div x-show="company.payment_terms_text"><div class="ie-pc-lbl" x-text="pl('payment_terms_heading')"></div><div class="ie-pc-val" x-text="company.payment_terms_text"></div></div>
                      <div x-show="company.payment_methods"><div class="ie-pc-lbl" x-text="pl('payment_methods_heading')"></div><div class="ie-pc-val" x-text="company.payment_methods"></div></div>
                      <div x-show="company.bank_name || company.iban"><div class="ie-pc-lbl" x-text="pl('bank_details')"></div><div class="ie-pc-val"><span x-text="company.bank_name"></span><template x-if="company.iban"><span><br x-show="company.bank_name">IBAN: <span x-text="company.iban"></span></span></template><template x-if="company.bic"><span><br>BIC: <span x-text="company.bic"></span></span></template></div></div>
                    </div>
                  </div>
                </div>
                <div class="ie-foot"><strong x-text="company.name"></strong><span x-text="[company.address ? ' · ' + company.address.replace(/\n/g, ', ') : '', company.email ? ' · ' + company.email : '', company.phone ? ' · ' + company.phone : ''].join('')"></span></div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </template>
  </div>

  {{-- Editorial template styles (scoped; only render in print). --}}
  <style>
    #invoice-print .ie { font-family:'Inter','SF Pro Text',system-ui,-apple-system,sans-serif; color:#313a4a; background:#fff; font-size:10px; line-height:1.55; --ink:#0b1220; --body:#313a4a; --soft:#5d6878; --faint:#97a1b1; --hair:#e6eaef; --wash:#f6f8fb; }
    #invoice-print .ie .num { font-variant-numeric:tabular-nums; }
    #invoice-print .ie-page { padding:46px 56px 78px; }
    #invoice-print .ie-header { display:flex; justify-content:space-between; align-items:flex-end; padding-bottom:20px; margin-bottom:26px; border-bottom:1px solid var(--ink); position:relative; }
    #invoice-print .ie-header::after { content:""; position:absolute; left:0; bottom:-1px; width:96px; height:2px; background:var(--ac); }
    #invoice-print .ie-brand { display:flex; align-items:center; gap:16px; }
    #invoice-print .ie-logo img { height:52px; display:block; }
    #invoice-print .ie-co-name { font-size:14px; font-weight:600; color:var(--ink); letter-spacing:-0.2px; }
    #invoice-print .ie-doc-meta { text-align:right; }
    #invoice-print .ie-doc-kind { font-size:9px; font-weight:600; letter-spacing:3.5px; text-transform:uppercase; color:var(--faint); }
    #invoice-print .ie-doc-no { font-size:28px; font-weight:600; color:var(--ink); letter-spacing:-0.8px; margin-top:6px; line-height:1; }
    #invoice-print .ie-meta-grid { display:grid; grid-template-columns:repeat(3,1fr); margin-bottom:26px; border-top:1px solid var(--hair); border-bottom:1px solid var(--hair); }
    #invoice-print .ie-meta-cell { padding:11px 18px 11px 0; border-right:1px solid var(--hair); }
    #invoice-print .ie-meta-cell:last-child { border-right:none; padding-right:0; }
    #invoice-print .ie-meta-cell:not(:first-child) { padding-left:18px; }
    #invoice-print .ie-m-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); margin-bottom:5px; }
    #invoice-print .ie-m-val { font-size:11px; font-weight:600; color:var(--ink); font-variant-numeric:tabular-nums; }
    #invoice-print .ie-pill { display:inline-block; padding:2px 10px; border-radius:2px; font-size:8px; font-weight:700; letter-spacing:1px; text-transform:uppercase; background:var(--ink); color:#fff; }
    #invoice-print .ie-pill.ie-paid { background:#0f7a4d; }
    #invoice-print .ie-pill.ie-draft { background:var(--faint); }
    #invoice-print .ie-parties { display:grid; grid-template-columns:1fr 1fr; gap:56px; margin-bottom:30px; }
    #invoice-print .ie-p-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.6px; text-transform:uppercase; color:var(--faint); padding-bottom:8px; margin-bottom:14px; border-bottom:1px solid var(--hair); }
    #invoice-print .ie-p-name { font-size:15px; font-weight:600; color:var(--ink); margin-bottom:8px; letter-spacing:-0.2px; line-height:1.25; }
    #invoice-print .ie-p-line { font-size:9.5px; color:var(--soft); line-height:1.85; }
    #invoice-print .ie-tbl-wrap { margin-bottom:22px; }
    #invoice-print .ie table { width:100%; border-collapse:collapse; }
    #invoice-print .ie thead th { padding:9px 0; font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); text-align:left; border-bottom:1.5px solid var(--ink); border-top:1px solid var(--hair); }
    #invoice-print .ie thead th.r { text-align:right; }
    #invoice-print .ie thead th:not(:first-child) { padding-left:16px; }
    #invoice-print .ie tbody tr { page-break-inside:avoid; }
    #invoice-print .ie tbody td { padding:11px 0; vertical-align:top; border-bottom:1px solid var(--hair); font-size:10px; }
    #invoice-print .ie tbody td:not(:first-child) { padding-left:16px; }
    #invoice-print .ie td.r { text-align:right; font-variant-numeric:tabular-nums; }
    #invoice-print .ie-d-title { font-weight:600; color:var(--ink); font-size:10.5px; line-height:1.45; }
    #invoice-print .ie-amt { font-weight:600; color:var(--ink); }
    #invoice-print .ie-sum-area { display:flex; justify-content:flex-end; margin-bottom:26px; }
    #invoice-print .ie-sum { width:340px; }
    #invoice-print .ie-sr { display:flex; justify-content:space-between; padding:8px 0; font-size:10px; border-bottom:1px solid var(--hair); }
    #invoice-print .ie-sr .l { color:var(--soft); }
    #invoice-print .ie-sr .v { font-variant-numeric:tabular-nums; color:var(--ink); font-weight:500; }
    #invoice-print .ie-grand { display:flex; justify-content:space-between; align-items:baseline; padding:14px 0 8px; border-top:2px solid var(--ink); margin-top:6px; }
    #invoice-print .ie-gl { font-size:9.5px; font-weight:600; text-transform:uppercase; letter-spacing:2.4px; color:var(--ink); }
    #invoice-print .ie-gv { font-size:26px; font-weight:600; color:var(--ink); letter-spacing:-0.6px; font-variant-numeric:tabular-nums; line-height:1; }
    #invoice-print .ie-notes-area { margin-bottom:20px; }
    #invoice-print .ie-n-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); margin-bottom:10px; }
    #invoice-print .ie-note-text { font-size:10px; color:var(--soft); line-height:1.7; max-width:480px; white-space:pre-line; }
    #invoice-print .ie-notice { font-size:8.5px; color:var(--faint); margin-bottom:22px; line-height:1.65; max-width:520px; white-space:pre-line; }
    #invoice-print .ie-pay-area { margin-top:26px; }
    #invoice-print .ie-pay-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:32px; padding-top:18px; border-top:1px solid var(--hair); }
    #invoice-print .ie-pc-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); margin-bottom:8px; }
    #invoice-print .ie-pc-val { font-size:9.5px; color:var(--ink); line-height:1.75; font-variant-numeric:tabular-nums; white-space:pre-line; }
    #invoice-print .ie-foot { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:7.5px; color:var(--faint); padding:14px 64px; line-height:1.8; border-top:1px solid var(--hair); background:#fff; letter-spacing:0.2px; }
    #invoice-print .ie-foot strong { color:var(--ink); font-weight:600; }
  </style>

  {{-- Print isolation: the sheet is teleported to <body>, so we hide every other
       body child and print only it — no phantom trailing blank page. --}}
  <style>
    #invoice-print { display: none; }
    @media print {
      @page { size: A4; margin: 0; }
      html, body { height: auto !important; background: #fff !important; }
      body > *:not(#invoice-print) { display: none !important; }
      #invoice-print { display: block !important; }
      /* Keep accent backgrounds/colours in print — browsers drop them otherwise. */
      #invoice-print, #invoice-print * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
  </style>
</x-layouts.app>
