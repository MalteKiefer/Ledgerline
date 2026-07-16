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
            'currency' => 'EUR',
        ]),
        labelsByLang: @js(['de' => __('invoices', [], 'de'), 'en' => __('invoices', [], 'en')]),
     }, {
        deleteConfirm: @js(__('invoices.delete_confirm')),
        statusDraft: @js(__('invoices.status_draft')),
        statusSent: @js(__('invoices.status_sent')),
        statusPaid: @js(__('invoices.status_paid')),
     })">

    {{-- Zero-knowledge gate: invoices decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center">
        <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
           x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <button type="button" @click="$dispatch('vault-panel')"
            class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
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
            <input type="search" x-model.debounce.250ms="query" placeholder="{{ __('invoices.search') }}" class="w-full max-w-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            <select x-model="filterStatus" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
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
                        <button type="button" @click="open(inv)" title="{{ __('common.edit') }}" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                        <button type="button" @click="printInvoice(inv)" title="{{ __('invoices.print') }}" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="printer" class="h-4 w-4" /></button>
                        <button type="button" @click="trash(inv)" title="{{ __('invoices.trash') }}" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="trash" class="h-4 w-4" /></button>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        {{-- ===================== EDITOR ===================== --}}
        <div x-show="view === 'edit' && current" x-cloak @input="saveSoon()">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <button type="button" @click="backToList()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="arrow-left" class="h-4 w-4" /></button>
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
            <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
              <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.customer') }}</h2>
                <button type="button" @click="openCustomerPicker()" class="text-xs font-medium text-gray-700 dark:text-gray-300 underline">{{ __('invoices.choose_customer') }}</button>
              </div>
              <div class="mt-3 space-y-2">
                <input type="text" x-model="current.customer.name" placeholder="{{ __('invoices.customer_name') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <input type="text" x-model="current.customer.attn" placeholder="{{ __('invoices.attn') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <textarea x-model="current.customer.address" rows="3" placeholder="{{ __('invoices.customer_address') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                <input type="email" x-model="current.customer.email" placeholder="{{ __('invoices.customer_email') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <input type="text" x-model="current.customer.vatId" placeholder="{{ __('invoices.customer_vat') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
              </div>
            </div>

            {{-- Dates --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.col_date') }}</h2>
              <div class="mt-3 space-y-3">
                <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.issue_date') }}
                  <input type="date" x-model="current.issueDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
                <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.due_date') }}
                  <input type="date" x-model="current.dueDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.language') }}
                    <select x-model="current.lang" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                      <option value="de">Deutsch</option>
                      <option value="en">English</option>
                    </select>
                  </label>
                  <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.currency') }}
                    <select x-model="current.currency" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                      <template x-for="c in currencyOptions" :key="c"><option :value="c" x-text="c"></option></template>
                    </select>
                  </label>
                </div>
              </div>
            </div>

            {{-- Totals summary --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
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
          <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.lines') }}</h2>
              <button type="button" @click="addLine()" class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300 underline"><x-icon name="plus" class="h-3.5 w-3.5" />{{ __('invoices.add_line') }}</button>
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
                      <td class="py-1 pr-2"><input type="text" x-model="l.desc" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></td>
                      <td class="py-1 px-2"><input type="number" step="0.01" x-model.number="l.qty" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-gray-500 focus:ring-gray-500"></td>
                      <td class="py-1 px-2"><input type="text" x-model="l.unit" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></td>
                      <td class="py-1 px-2"><input type="number" step="0.01" x-model.number="l.unitPrice" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-gray-500 focus:ring-gray-500"></td>
                      <td class="py-1 px-2"><input type="number" step="0.01" x-model.number="l.vatRate" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-gray-500 focus:ring-gray-500"></td>
                      <td class="py-1 px-2 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="fmtMoney(lineNet(l))"></td>
                      <td class="py-1 pl-2 text-right"><button type="button" @click="removeLine(i)" title="{{ __('invoices.remove') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          {{-- Note / footer --}}
          <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.note') }}
              <textarea x-model="current.note" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
            </label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('invoices.footer') }}
              <textarea x-model="current.footer" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
            </label>
          </div>

          <div class="mt-6 flex justify-end">
            <button type="button" @click="remove(current)" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950"><x-icon name="trash" class="h-4 w-4" />{{ __('invoices.delete') }}</button>
          </div>
        </div>

        {{-- ===================== CUSTOMER PICKER ===================== --}}
        <div x-show="customerPicker" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeCustomerPicker()">
          <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeCustomerPicker()"></div>
          <div class="relative w-full max-w-md rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-xl">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.picker_title') }}</h2>
            <input type="search" x-model="custQuery" placeholder="{{ __('invoices.picker_search') }}" class="mt-3 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
              <template x-if="! custSuggestions().length">
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('invoices.picker_empty') }}</p>
              </template>
              <template x-for="c in custSuggestions()" :key="c.id">
                <button type="button" @click="pickCustomer(c)" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800">
                  <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="_custName(c) || (c.org || '—')"></span>
                </button>
              </template>
            </div>
          </div>
        </div>

        {{-- ===================== PRINT / PDF SHEET ===================== --}}
        <template x-if="_printing">
          <div id="invoice-print" class="bg-white text-gray-900" style="padding:40px; font-size:12.5px; line-height:1.5; color:#1f2937;">
            {{-- Header: sender + title/meta --}}
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:24px;">
              <div>
                <template x-if="company.logo"><img :src="company.logo" alt="" style="max-height:60px; margin-bottom:14px;"></template>
                <div style="font-weight:700; font-size:15px;" x-text="company.name"></div>
                <div style="white-space:pre-line; color:#4b5563;" x-text="company.address"></div>
                <div style="color:#4b5563;" x-text="[company.email, company.phone].filter(Boolean).join(' · ')"></div>
                <div style="color:#4b5563; margin-top:6px;" x-show="company.vat_id" x-text="pl('vat_id_label') + ': ' + company.vat_id"></div>
              </div>
              <div style="text-align:right; min-width:220px;">
                <div style="font-size:26px; font-weight:800; letter-spacing:.02em;" :style="'color:' + company.accent" x-text="pl('print_title')"></div>
                <table style="margin-top:10px; margin-left:auto; border-collapse:collapse;">
                  <tr><td style="padding:1px 10px 1px 0; text-align:right;" :style="'color:' + company.heading" x-text="pl('invoice_number')"></td><td style="text-align:right; font-weight:600;" class="tabular-nums" x-text="_printing.number || '—'"></td></tr>
                  <tr><td style="padding:1px 10px 1px 0; text-align:right;" :style="'color:' + company.heading" x-text="pl('invoice_date')"></td><td style="text-align:right;" class="tabular-nums" x-text="_printing.issueDate"></td></tr>
                  <tr><td style="padding:1px 10px 1px 0; text-align:right;" :style="'color:' + company.heading" x-text="pl('due')"></td><td style="text-align:right;" class="tabular-nums" x-text="_printing.dueDate"></td></tr>
                </table>
              </div>
            </div>

            <div style="height:2px; margin:20px 0;" :style="'background:' + company.accent"></div>

            {{-- Bill to --}}
            <div>
              <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:.08em; font-weight:600;" :style="'color:' + company.heading" x-text="pl('bill_to')"></div>
              <div style="font-weight:700; margin-top:4px;" x-text="_printing.customer?.name"></div>
              <div style="color:#4b5563;" x-show="_printing.customer?.attn" x-text="_printing.customer?.attn"></div>
              <div style="white-space:pre-line; color:#4b5563;" x-text="_printing.customer?.address"></div>
              <div style="color:#4b5563;" x-show="_printing.customer?.email" x-text="_printing.customer?.email"></div>
              <div style="color:#4b5563;" x-show="_printing.customer?.vatId" x-text="pl('vat_id_label') + ': ' + _printing.customer?.vatId"></div>
            </div>

            {{-- Line items --}}
            <table style="width:100%; margin-top:26px; border-collapse:collapse;">
              <thead>
                <tr style="text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#fff;" :style="'background:' + company.accent">
                  <th style="padding:8px 10px;" x-text="pl('line_desc')"></th>
                  <th style="padding:8px 10px; text-align:right; white-space:nowrap;" x-text="pl('line_qty')"></th>
                  <th style="padding:8px 10px; text-align:right; white-space:nowrap;" x-text="pl('line_price')"></th>
                  <th style="padding:8px 10px; text-align:right; white-space:nowrap;" x-text="pl('amount')"></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="(l, i) in _printing.lines" :key="i">
                  <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:7px 10px;"><span x-text="l.desc"></span></td>
                    <td style="padding:7px 10px; text-align:right; white-space:nowrap;" class="tabular-nums" x-text="l.qty + (l.unit ? ' ' + l.unit : '')"></td>
                    <td style="padding:7px 10px; text-align:right; white-space:nowrap;" class="tabular-nums" x-text="fmtMoney(l.unitPrice, _printing.currency, _printing.lang)"></td>
                    <td style="padding:7px 10px; text-align:right; white-space:nowrap;" class="tabular-nums" x-text="fmtMoney(lineNet(l), _printing.currency, _printing.lang)"></td>
                  </tr>
                </template>
              </tbody>
            </table>

            {{-- Totals --}}
            <div style="display:flex; justify-content:flex-end; margin-top:18px;">
              <table style="min-width:260px;">
                <tr><td style="padding:3px 10px; color:#4b5563;" x-text="pl('subtotal')"></td><td style="padding:3px 10px; text-align:right;" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).net, _printing.currency, _printing.lang)"></td></tr>
                <template x-for="rate in vatRatesOf(_printing)" :key="rate">
                  <tr><td style="padding:3px 10px; color:#4b5563;" x-text="pl('vat_at').replace(':rate', rate)"></td><td style="padding:3px 10px; text-align:right;" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).vatByRate[rate], _printing.currency, _printing.lang)"></td></tr>
                </template>
                <tr style="font-weight:800; font-size:14px;" :style="'color:' + company.accent + '; border-top:2px solid ' + company.accent"><td style="padding:6px 10px;" x-text="pl('gross')"></td><td style="padding:6px 10px; text-align:right;" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).gross, _printing.currency, _printing.lang)"></td></tr>
              </table>
            </div>

            {{-- Tax breakdown --}}
            <div style="display:flex; justify-content:flex-end; margin-top:8px;" x-show="vatRatesOf(_printing).length">
              <table style="min-width:260px; font-size:10.5px; color:#6b7280;">
                <tr style="text-transform:uppercase; letter-spacing:.05em;">
                  <td style="padding:2px 10px;" x-text="pl('tax_heading')"></td>
                  <td style="padding:2px 10px; text-align:right;" x-text="pl('taxable')"></td>
                  <td style="padding:2px 10px; text-align:right;" x-text="pl('tax_amount')"></td>
                </tr>
                <template x-for="rate in vatRatesOf(_printing)" :key="rate">
                  <tr>
                    <td style="padding:2px 10px;" class="tabular-nums" x-text="rate + '%'"></td>
                    <td style="padding:2px 10px; text-align:right;" class="tabular-nums" x-text="fmtMoney((computeTotals(_printing).vatByRate[rate]||0) / (rate/100), _printing.currency, _printing.lang)"></td>
                    <td style="padding:2px 10px; text-align:right;" class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).vatByRate[rate], _printing.currency, _printing.lang)"></td>
                  </tr>
                </template>
              </table>
            </div>

            {{-- Notes --}}
            <div style="margin-top:26px;" x-show="_printing.note">
              <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:.08em; font-weight:600;" :style="'color:' + company.heading" x-text="pl('notes_heading')"></div>
              <div style="white-space:pre-line; color:#4b5563; margin-top:3px;" x-text="_printing.note"></div>
            </div>

            {{-- Footer: 3 columns --}}
            <div style="margin-top:36px; padding-top:14px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; font-size:10.5px; color:#4b5563;" :style="'border-top:1px solid ' + company.accent">
              <div x-show="company.payment_terms_text || _printing.footer">
                <div style="text-transform:uppercase; letter-spacing:.06em; font-weight:600;" :style="'color:' + company.heading" x-text="pl('payment_terms_heading')"></div>
                <div style="white-space:pre-line; margin-top:3px;" x-text="company.payment_terms_text || _printing.footer"></div>
              </div>
              <div x-show="company.payment_methods">
                <div style="text-transform:uppercase; letter-spacing:.06em; font-weight:600;" :style="'color:' + company.heading" x-text="pl('payment_methods_heading')"></div>
                <div style="white-space:pre-line; margin-top:3px;" x-text="company.payment_methods"></div>
              </div>
              <div x-show="company.bank_name || company.iban">
                <div style="text-transform:uppercase; letter-spacing:.06em; font-weight:600;" :style="'color:' + company.heading" x-text="pl('bank_details')"></div>
                <div style="margin-top:3px;" x-text="company.bank_name"></div>
                <div x-text="company.iban ? 'IBAN: ' + company.iban : ''"></div>
                <div x-text="company.bic ? 'BIC: ' + company.bic : ''"></div>
              </div>
            </div>

            <div style="margin-top:16px; text-align:center; font-size:9.5px; color:#9ca3af;" x-text="[company.name, company.address ? company.address.replace(/\n/g, ', ') : '', company.email, company.phone].filter(Boolean).join(' · ')"></div>
          </div>
        </template>
      </div>
    </template>
  </div>

  {{-- Print isolation: only the invoice sheet prints. --}}
  <style>
    #invoice-print { display: none; }
    @media print {
      body * { visibility: hidden !important; }
      #invoice-print, #invoice-print * { visibility: visible !important; }
      #invoice-print { display: block !important; position: absolute; left: 0; top: 0; width: 100%; }
    }
  </style>
</x-layouts.app>
