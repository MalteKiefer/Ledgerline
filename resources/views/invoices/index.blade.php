@php $s = \App\Models\UserSetting::for(auth()->id()); @endphp
<x-layouts.app :title="__('messages.nav.finance')">
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
        uploadUrl: '{{ url('/invoices/upload') }}',
        rawBase: '{{ url('/invoices/raw') }}',
        reconcileUrl: '{{ url('/invoices/blobs/reconcile') }}',
        iconUrl: '{{ url('/passwords/icon') }}',
     }, {
        deleteConfirm: @js(__('invoices.delete_confirm')),
        statusDraft: @js(__('invoices.status_draft')),
        statusSent: @js(__('invoices.status_sent')),
        statusPaid: @js(__('invoices.status_paid')),
        csvImported: @js(__('invoices.csv_imported')),
        csvBadFormat: @js(__('invoices.csv_bad_format')),
        importSummaryLabel: @js(__('invoices.import_summary_label')),
        importDone: @js(__('invoices.import_done')),
        importFailed: @js(__('invoices.import_load_failed')),
        trashConfirm: @js(__('invoices.trash_confirm')),
        paperlessWarn: @js(__('files.paperless_decrypt_warn')),
        pay_invalid: @js(__('invoices.pay_invalid')),
        pay_delete_confirm: @js(__('invoices.pay_delete_confirm')),
        pay_type_bank: @js(__('invoices.pay_type_bank')),
        pay_type_card: @js(__('invoices.pay_type_card')),
        pay_type_paypal: @js(__('invoices.pay_type_paypal')),
        pay_type_cash: @js(__('invoices.pay_type_cash')),
        pay_type_other: @js(__('invoices.pay_type_other')),
        stmt_read_failed: @js(__('invoices.stmt_read_failed')),
        stmt_unknown: @js(__('invoices.stmt_unknown')),
        stmt_imported: @js(__('invoices.stmt_imported')),
        txf_date: @js(__('invoices.txf_date')),
        txf_valueDate: @js(__('invoices.txf_valueDate')),
        txf_amount: @js(__('invoices.txf_amount')),
        txf_purpose: @js(__('invoices.txf_purpose')),
        txf_counterparty: @js(__('invoices.txf_counterparty')),
        txf_iban: @js(__('invoices.txf_iban')),
        txf_bic: @js(__('invoices.txf_bic')),
        txf_bookingText: @js(__('invoices.txf_bookingText')),
        txf_eref: @js(__('invoices.txf_eref')),
     })">

    {{-- Zero-knowledge gate: invoices decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    {{-- Shared Paperless transfer modal (send a receipt to Paperless) --}}
    @include('_paperless_modal')

    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 max-w-md ll-card !p-8 text-center">
        <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
           x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <x-button variant="primary" class="mt-5" icon="lock-open" @click="$dispatch('vault-panel')">
          <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
        </x-button>
      </div>
    </template>

    <template x-if="state === 'error'">
      <p class="mx-auto mt-16 max-w-md rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('invoices.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        {{-- ===================== FINANCE HUB (tabs) ===================== --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.nav.finance') }}</h1>
          <div class="-mx-1 max-w-full overflow-x-auto px-1 pb-1">
            <div class="inline-flex rounded-xl bg-black/[0.04] dark:bg-white/10 p-0.5 text-sm font-medium">
              @php $tabs = ['dashboard' => 'tab_dashboard', 'invoices' => 'tab_invoices', 'payments' => 'tab_payments', 'receipts' => 'tab_receipts', 'stats' => 'tab_stats', 'settings' => 'tab_settings']; @endphp
              @foreach ($tabs as $key => $lbl)
                <button type="button" @click="setSection('{{ $key }}')"
                  class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 transition-colors"
                  :class="section === '{{ $key }}' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-accent'">
                  {{ __('invoices.'.$lbl) }}
                </button>
              @endforeach
            </div>
          </div>
        </div>

        {{-- ===================== DASHBOARD ===================== --}}
        <div x-show="section === 'dashboard'" class="mt-6">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('invoices.dash_intro') }}</p>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Income: received this year --}}
            <div class="ll-card">
              <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <span class="ll-chip h-6 w-6 rounded-lg" style="background:#59ad6b"><x-icon name="arrow-trending-up" class="h-3.5 w-3.5 text-white" /></span>{{ __('invoices.paid_total') }}
              </div>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(financeStats.paidYear)"></p>
              <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('invoices.income_this_year') }} · ' + financeStats.year"></p>
            </div>
            {{-- Outstanding this year --}}
            <div class="ll-card">
              <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <span class="ll-chip h-6 w-6 rounded-lg" style="background:#d9a441"><x-icon name="clock" class="h-3.5 w-3.5 text-white" /></span>{{ __('invoices.outstanding_total') }}
              </div>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(financeStats.outstandingYear)"></p>
              <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500" x-text="financeStats.countYear + ' {{ __('invoices.invoice_count') }}'"></p>
            </div>
            {{-- Income all-time --}}
            <div class="ll-card">
              <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <span class="ll-chip h-6 w-6 rounded-lg" style="background:#7066f5"><x-icon name="banknotes" class="h-3.5 w-3.5 text-white" /></span>{{ __('invoices.income') }}
              </div>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(financeStats.paidAll)"></p>
              <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.paid_total') }}</p>
            </div>
            {{-- Expenses (coming soon) --}}
            <div class="ll-card opacity-70">
              <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <span class="ll-chip h-6 w-6 rounded-lg" style="background:#6b7280"><x-icon name="arrow-trending-down" class="h-3.5 w-3.5 text-white" /></span>{{ __('invoices.expenses') }}
              </div>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-300 dark:text-gray-600">—</p>
              <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.expenses_soon') }}</p>
            </div>
          </div>

          {{-- VAT advance return (Umsatzsteuer-Voranmeldung) for the current year --}}
          <div class="ll-card mt-4 !p-0 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
              <span class="ll-chip h-7 w-7 rounded-lg" style="background:#e2915a"><x-icon name="receipt-percent" class="h-4 w-4 text-white" /></span>
              <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.vat_title') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500" x-text="vatReturn.year + ' · ' + vatReturn.count + ' {{ __('invoices.invoice_count') }}'"></p>
              </div>
            </div>
            {{-- Net / VAT / gross totals --}}
            <div class="grid grid-cols-1 divide-y divide-black/[0.06] dark:divide-white/10 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
              <div class="px-5 py-4">
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.vat_net') }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(vatReturn.net)"></p>
              </div>
              <div class="px-5 py-4">
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.vat_owed') }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-accent" x-text="fmtMoney(vatReturn.vat)"></p>
              </div>
              <div class="px-5 py-4">
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.vat_gross') }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(vatReturn.gross)"></p>
              </div>
            </div>
            {{-- Breakdown by rate --}}
            <template x-if="vatReturn.byRate.length">
              <div class="border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.vat_by_rate') }}</p>
                <table class="w-full text-sm">
                  <thead class="text-left text-xs text-gray-400 dark:text-gray-500">
                    <tr><th class="pb-1 pr-3 font-normal">{{ __('invoices.vat_rate') }}</th><th class="pb-1 pr-3 font-normal text-right">{{ __('invoices.vat_net') }}</th><th class="pb-1 font-normal text-right">{{ __('invoices.vat_owed') }}</th></tr>
                  </thead>
                  <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                    <template x-for="b in vatReturn.byRate" :key="b.rate">
                      <tr class="text-gray-800 dark:text-gray-200">
                        <td class="py-1.5 pr-3 tabular-nums" x-text="b.rate + '%'"></td>
                        <td class="py-1.5 pr-3 text-right tabular-nums" x-text="fmtMoney(b.net)"></td>
                        <td class="py-1.5 text-right tabular-nums" x-text="fmtMoney(b.vat)"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </template>
            {{-- Quarterly breakdown --}}
            <div class="border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
              <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.vat_by_quarter') }}</p>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <template x-for="q in vatReturn.quarters" :key="q.q">
                  <div class="rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                    <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'Q' + q.q"></p>
                    <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(q.vat)"></p>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500" x-text="fmtMoney(q.net) + ' {{ __('invoices.vat_net_short') }}'"></p>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>

        {{-- ===================== RECEIPTS (document manager) ===================== --}}
        <div x-show="section === 'receipts'" class="mt-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <input type="search" x-model.debounce.200ms="receiptQuery" placeholder="{{ __('invoices.receipts_search') }}" class="w-full max-w-xs rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
            <div class="flex items-center gap-3">
              <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('invoices.receipts_count') }}'.replace(':n', allReceipts.length)"></p>
              <template x-if="unrecognisedReceipts">
                <x-button variant="secondary" size="sm" icon="arrow-path" ::disabled="reanalyzeBusy" @click="reanalyzeAllReceipts()">
                  <span x-text="'{{ __('invoices.receipts_reanalyze_all') }}'.replace(':n', unrecognisedReceipts)"></span>
                </x-button>
              </template>
              <template x-if="(transactions || []).length">
                <span>
                  <input type="file" x-ref="autoReceipt" accept="application/pdf,image/*" multiple class="hidden" @change="uploadReceiptsAuto($event.target.files); $event.target.value = ''">
                  <x-button variant="primary" size="sm" icon="arrow-up-tray" ::disabled="autoUploadBusy" @click="$refs.autoReceipt.click()">
                    <span x-show="! autoUploadBusy">{{ __('invoices.receipts_add') }}</span>
                    <span x-show="autoUploadBusy">{{ __('invoices.receipts_uploading') }}</span>
                  </x-button>
                </span>
              </template>
            </div>
          </div>

          {{-- Assignment: receipts that could not be auto-matched by amount --}}
          <div x-show="receiptAssign.length" x-cloak class="fixed inset-0 z-[1130] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-gray-900/50"></div>
            <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="receiptAssign.length">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="(receiptAssign[0].up.name || '{{ __('invoices.receipt') }}')"></h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      <span x-text="'{{ __('invoices.assign_intro') }}'.replace(':n', receiptAssign.length)"></span>
                      <template x-if="receiptAssign[0].total != null"><span> · <span x-text="fmtMoney(receiptAssign[0].total)"></span></span></template>
                    </p>
                  </div>
                  <div class="min-h-0 flex-1 overflow-auto px-2 py-2">
                    {{-- amount matches first (if any), else searchable --}}
                    <template x-if="receiptAssign[0].cands.length">
                      <div class="px-2 pb-1 pt-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ __('invoices.assign_by_amount') }}</div>
                    </template>
                    <template x-for="cand in receiptAssign[0].cands" :key="'m'+cand.id">
                      <button type="button" @click="assignPending(0, cand)" class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left hover:bg-accent/5">
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200"><span x-text="cand.date"></span> · <span x-text="cand.counterparty || cand.purpose || '—'"></span></span>
                        <span class="shrink-0 text-sm tabular-nums" :class="cand.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'" x-text="fmtMoney(cand.amount, cand.currency)"></span>
                      </button>
                    </template>
                    <div class="px-3 pb-1 pt-2"><input type="search" x-model.debounce.200ms="assignQuery" placeholder="{{ __('invoices.receipt_relink_search') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></div>
                    <template x-for="cand in assignCandidates()" :key="'a'+cand.id">
                      <button type="button" @click="assignPending(0, cand)" class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left hover:bg-accent/5">
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300"><span x-text="cand.date"></span> · <span x-text="cand.counterparty || cand.purpose || '—'"></span></span>
                        <span class="shrink-0 text-xs tabular-nums text-gray-400" x-text="fmtMoney(cand.amount, cand.currency)"></span>
                      </button>
                    </template>
                  </div>
                  <div class="flex items-center justify-between gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <x-button variant="secondary" size="sm" @click="dropPending(0)">{{ __('invoices.assign_skip') }}</x-button>
                    <span class="text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('invoices.assign_remaining') }}'.replace(':n', receiptAssign.length)"></span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <template x-if="! filteredReceipts.length">
            <x-empty-state icon="paper-clip" class="mt-6">{{ __('invoices.receipts_docs_empty') }}</x-empty-state>
          </template>
          <template x-if="filteredReceipts.length">
            <div class="ll-card !p-0 mt-4 overflow-hidden">
              <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="doc in filteredReceipts" :key="doc.r.id">
                  <button type="button" @click="openReceiptDoc(doc)" class="group flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent/5">
                    <span class="ll-chip h-9 w-9 rounded-xl shrink-0" :style="{ background: doc.r.kind === 'invoice' ? '#7066f5' : '#3fae9f' }"><x-icon name="document" class="h-4.5 w-4.5 text-white" /></span>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="doc.r.name || '{{ __('invoices.receipt') }}'"></p>
                      <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        <span x-text="doc.tx.date"></span> · <span x-text="doc.tx.counterparty || doc.tx.purpose || '—'"></span> · <span class="tabular-nums" x-text="fmtMoney(doc.tx.amount, doc.tx.currency)"></span>
                      </p>
                    </div>
                    <template x-if="doc.r.category"><x-badge variant="gray"><span x-text="doc.r.category"></span></x-badge></template>
                    <template x-if="doc.r.contactId || doc.r.partnerId"><x-icon name="user" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" /></template>
                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                  </button>
                </template>
              </div>
            </div>
          </template>

          {{-- Receipt document detail (metadata, link, notes, tags, category, contact) --}}
          <div x-show="receiptDoc" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeReceiptDoc()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeReceiptDoc()"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="receiptDoc">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="flex items-center gap-2.5 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <span class="ll-chip h-8 w-8 rounded-xl shrink-0" :style="{ background: receiptDoc.r.kind === 'invoice' ? '#7066f5' : '#3fae9f' }"><x-icon name="document" class="h-4.5 w-4.5 text-white" /></span>
                    <p class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="receiptDoc.r.name || '{{ __('invoices.receipt') }}'"></p>
                    <x-icon-button name="arrow-up-right" tone="gray" size="sm" @click="openReceipt(receiptDoc.r)" :aria-label="__('invoices.receipt_open_tab')" />
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeReceiptDoc()" :aria-label="__('common.close')" />
                  </div>
                  <div class="min-h-0 flex-1 space-y-4 overflow-auto px-5 py-4">
                    {{-- Linkage --}}
                    <div class="rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2.5">
                      <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.receipt_linked') }}</p>
                      <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">
                        <span x-text="receiptDoc.tx.date"></span> · <span x-text="receiptDoc.tx.counterparty || receiptDoc.tx.purpose || '—'"></span> · <span class="tabular-nums" x-text="fmtMoney(receiptDoc.tx.amount, receiptDoc.tx.currency)"></span>
                      </p>
                      <button type="button" @click="relinkQuery=''; receiptRelink = ! receiptRelink" class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-accent hover:underline">
                        <x-icon name="link" class="h-3 w-3" />{{ __('invoices.receipt_relink') }}
                      </button>
                      <template x-if="receiptRelink">
                        <div class="mt-2">
                          <input type="search" x-model.debounce.200ms="relinkQuery" placeholder="{{ __('invoices.receipt_relink_search') }}" class="w-full rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                          <div class="mt-1 max-h-40 overflow-auto rounded-lg border border-black/[0.06] dark:border-white/10">
                            <template x-for="cand in relinkCandidates" :key="cand.id">
                              <button type="button" @click="relinkReceiptTo(cand)" class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-xs hover:bg-accent/5">
                                <span class="truncate text-gray-700 dark:text-gray-200"><span x-text="cand.date"></span> · <span x-text="cand.counterparty || cand.purpose || '—'"></span></span>
                                <span class="shrink-0 tabular-nums text-gray-400" x-text="fmtMoney(cand.amount, cand.currency)"></span>
                              </button>
                            </template>
                          </div>
                        </div>
                      </template>
                    </div>

                    {{-- Category (with suggestions) --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_category') }}</label>
                      <input type="text" x-model="receiptDoc.r.category" @change="saveReceiptDoc()" list="receiptCats" placeholder="{{ __('invoices.receipt_category_ph') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      <datalist id="receiptCats"><template x-for="c in allCategories" :key="c"><option :value="c"></option></template></datalist>
                    </div>

                    {{-- Tags (badge chips, like the other modules) --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_tags') }}</label>
                      <x-tag-field commit="saveReceiptDoc()" placeholder="{{ __('invoices.receipt_tags_ph') }}" />
                    </div>

                    {{-- Business partner (a contact, or a standalone partner) --}}
                    <div x-data="{ open: false }">
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_partner') }}</label>
                      <template x-if="receiptDoc.r.contactId || receiptDoc.r.partnerId">
                        <div class="flex items-center gap-2 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                          <x-icon name="user" class="h-4 w-4 text-gray-400" />
                          <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="receiptPartnerName(receiptDoc.r) || '—'"></span>
                          <x-icon-button name="x-mark" tone="gray" size="sm" @click="setReceiptPartner(null)" :aria-label="__('common.delete')" />
                        </div>
                      </template>
                      <template x-if="! (receiptDoc.r.contactId || receiptDoc.r.partnerId)">
                        <div class="relative">
                          <input type="search" x-model="receiptDoc.r.partnerQuery" @focus="open = true" placeholder="{{ __('invoices.receipt_partner_ph') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                          <div x-show="open && partnerOptions().length" @click.outside="open = false" class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-lg">
                            <template x-for="o in partnerOptions()" :key="o.kind + o.id">
                              <button type="button" @click="setReceiptPartner(o); open = false" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-accent/5">
                                <span class="min-w-0 truncate" x-text="o.name"></span>
                                <span class="shrink-0 text-[10px] uppercase tracking-wide text-gray-400" x-text="o.kind === 'contact' ? '{{ __('invoices.partner_contact') }}' : '{{ __('invoices.partner_partner') }}'"></span>
                              </button>
                            </template>
                          </div>
                        </div>
                      </template>
                    </div>

                    {{-- Notes --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_note') }}</label>
                      <textarea x-model="receiptDoc.r.note" @change="saveReceiptDoc()" rows="3" placeholder="{{ __('invoices.receipt_note_ph') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                    </div>
                    <template x-if="receiptDoc.r.locked">
                      <x-alert variant="info">{{ __('invoices.receipt_locked_hint') }}</x-alert>
                    </template>
                  </div>
                  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                      <x-button variant="secondary" size="sm" icon="pencil" @click="renameReceiptDoc()">{{ __('invoices.receipt_rename') }}</x-button>
                      <template x-if="receiptDoc.r.kind !== 'invoice'">
                        <x-button variant="secondary" size="sm" icon="arrow-path" @click="reanalyzeReceipt(receiptDoc)">{{ __('invoices.receipt_reanalyze') }}</x-button>
                      </template>
                      <template x-if="receiptDoc.r.blob && $store.paperless.configured">
                        <x-button variant="secondary" size="sm" icon="share" @click="sendReceiptToPaperless(receiptDoc)">{{ __('paperless.send_to_paperless') }}</x-button>
                      </template>
                    </div>
                    <template x-if="! receiptDoc.r.locked">
                      <x-button variant="danger" size="sm" icon="trash" @click="deleteReceiptDoc()">{{ __('common.delete') }}</x-button>
                    </template>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        {{-- ===================== STATISTICS ===================== --}}
        <div x-show="section === 'stats'" class="mt-6">
          <template x-if="! statsKpis.count && statsYear === {{ (int) date('Y') }}">
            <x-empty-state icon="chart-bar">{{ __('invoices.stats_empty') }}</x-empty-state>
          </template>
          <template x-if="statsKpis.count || statsYear !== {{ (int) date('Y') }}">
            <div>
              {{-- Year selector --}}
              <div class="mb-4 flex items-center gap-2">
                <label class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stats_year') }}</label>
                <select x-model.number="statsYear" class="rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] px-2 py-1 text-sm">
                  <template x-for="y in statsYears" :key="y"><option :value="y" x-text="y"></option></template>
                </select>
              </div>

              {{-- KPI row --}}
              <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="ll-card">
                  <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_revenue') }}</p>
                  <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(statsKpis.net)"></p>
                  <template x-if="statsKpis.growthPct !== null">
                    <p class="mt-0.5 flex items-center gap-1 text-xs" :class="statsKpis.growthPct >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                      <x-icon ::name="statsKpis.growthPct >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" class="h-3.5 w-3.5" />
                      <span x-text="(statsKpis.growthPct >= 0 ? '+' : '') + statsKpis.growthPct + '% {{ __('invoices.stat_vs_prev') }}'"></span>
                    </p>
                  </template>
                </div>
                <div class="ll-card">
                  <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_invoices') }}</p>
                  <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="statsKpis.count"></p>
                </div>
                <div class="ll-card">
                  <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_avg') }}</p>
                  <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(statsKpis.avg)"></p>
                </div>
                <div class="ll-card">
                  <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_customers') }}</p>
                  <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="statsKpis.customers"></p>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Monthly revenue bars --}}
                <div class="ll-card">
                  <p class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.stat_monthly') }}</p>
                  <div class="space-y-1.5">
                    <template x-for="m in statsMonths" :key="m.month">
                      <div class="flex items-center gap-2">
                        <span class="w-8 shrink-0 text-xs text-gray-400 dark:text-gray-500" x-text="monthLabel(m.month)"></span>
                        <div class="h-4 flex-1 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                          <div class="h-full ll-accent" :style="{ width: Math.round(m.net / statsMonthPeak * 100) + '%' }"></div>
                        </div>
                        <span class="w-24 shrink-0 text-right text-xs tabular-nums text-gray-600 dark:text-gray-300" x-text="m.net ? fmtMoney(m.net) : '—'"></span>
                      </div>
                    </template>
                  </div>
                </div>

                {{-- Revenue by customer --}}
                <div class="ll-card">
                  <p class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.stat_by_customer') }}</p>
                  <template x-if="! statsCustomers.length"><p class="text-sm text-gray-400 dark:text-gray-500">—</p></template>
                  <div class="space-y-2">
                    <template x-for="(c, i) in statsCustomers" :key="c.name">
                      <div>
                        <div class="flex items-baseline justify-between gap-2">
                          <span class="truncate text-sm text-gray-800 dark:text-gray-200" x-text="c.name" :title="c.name"></span>
                          <span class="shrink-0 text-sm tabular-nums text-gray-600 dark:text-gray-300" x-text="fmtMoney(c.net)"></span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                          <div class="h-full ll-accent" :style="{ width: Math.round(c.net / (statsCustomers[0]?.net || 1) * 100) + '%' }"></div>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
              </div>

              {{-- VAT by quarter for the selected year --}}
              <div class="ll-card mt-4">
                <p class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="'{{ __('invoices.vat_by_quarter') }} · ' + statsVat.year"></p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  <template x-for="q in statsVat.quarters" :key="q.q">
                    <div class="rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                      <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'Q' + q.q"></p>
                      <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(q.vat)"></p>
                      <p class="text-[11px] text-gray-400 dark:text-gray-500" x-text="fmtMoney(q.net) + ' {{ __('invoices.vat_net_short') }}'"></p>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </template>
        </div>

        {{-- ===================== SETTINGS (partners + categories) — iOS grouped lists ===================== --}}
        <div x-show="section === 'settings'" class="mt-6 mx-auto max-w-2xl">
          {{-- Business partners --}}
          <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.partners_title') }}</h2>
          <p class="mb-2 px-1 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.partners_intro') }}</p>
          <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            <template x-for="p in sortedPartners" :key="p.id">
              <div class="group flex items-center gap-3 px-4 py-3 hover:bg-accent/5">
                <span class="ll-chip h-9 w-9 shrink-0" style="--chip: #6b7280"><x-icon name="user" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="p.name"></p>
                  <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="[p.category, p.note].filter(Boolean).join(' · ') || '—'"></p>
                </div>
                <div class="flex shrink-0 items-center gap-1 md:opacity-0 md:group-hover:opacity-100">
                  <x-icon-button name="pencil" tone="gray" size="sm" @click="editPartner(p)" :aria-label="__('common.edit')" />
                  <x-icon-button name="trash" tone="red" size="sm" @click="removePartner(p)" :aria-label="__('common.delete')" />
                </div>
              </div>
            </template>
            {{-- Add row --}}
            <button type="button" @click="newPartner()" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent/5">
              <span class="ll-chip h-9 w-9 shrink-0" style="--chip: #7066f5"><x-icon name="plus" class="h-5 w-5" /></span>
              <span class="text-sm font-medium text-accent">{{ __('invoices.partner_add') }}</span>
            </button>
          </div>

          {{-- Categories --}}
          <h2 class="mb-2 mt-7 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.cats_title') }}</h2>
          <p class="mb-2 px-1 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.cats_intro') }}</p>
          <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            {{-- Built-in default categories (not removable) --}}
            <template x-for="c in receiptCatSuggestions" :key="'def-'+c">
              <div class="flex items-center gap-3 px-4 py-2.5">
                <span class="ll-chip h-8 w-8 rounded-lg shrink-0" style="--chip: #e2915a"><x-icon name="hashtag" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="c"></span>
                <x-badge variant="gray">{{ __('invoices.cats_default') }}</x-badge>
              </div>
            </template>
            {{-- Custom categories (removable) --}}
            <template x-for="c in (financeCategories || [])" :key="c.name">
              <div class="group flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5">
                <span class="ll-chip h-8 w-8 rounded-lg shrink-0" style="--chip: #59ad6b"><x-icon name="hashtag" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="c.name"></span>
                <x-icon-button name="trash" tone="red" size="sm" class="md:opacity-0 md:group-hover:opacity-100" @click="removeFinanceCategory(c)" :aria-label="__('common.delete')" />
              </div>
            </template>
            {{-- Add row --}}
            <form @submit.prevent="addFinanceCategory(newCategoryName)" class="flex items-center gap-3 px-4 py-2.5">
              <span class="ll-chip h-8 w-8 rounded-lg shrink-0" style="--chip: #7066f5"><x-icon name="plus" class="h-4 w-4" /></span>
              <input type="text" x-model="newCategoryName" placeholder="{{ __('invoices.cats_add_ph') }}" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
              <x-button variant="secondary" size="sm" type="submit" ::disabled="! newCategoryName.trim()">{{ __('invoices.cats_add') }}</x-button>
            </form>
          </div>

          {{-- Partner editor modal --}}
          <div x-show="partnerEditing" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cancelPartner()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelPartner()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="partnerEditing">
                <div>
                  <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.partner_add') }}</h3>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="cancelPartner()" :aria-label="__('common.close')" />
                  </div>
                  <div class="space-y-3 px-5 py-4">
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_name') }} <span class="text-red-500">*</span></label>
                      <input type="text" x-model="partnerEditing.name" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_category') }}</label>
                      <input type="text" x-model="partnerEditing.category" list="receiptCats" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_note') }}</label>
                      <textarea x-model="partnerEditing.note" rows="2" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                    </div>
                  </div>
                  <div class="flex items-center justify-end gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <x-button variant="secondary" @click="cancelPartner()">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="savePartner()">{{ __('common.save') }}</x-button>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        {{-- ===================== PAYMENT METHODS ===================== --}}
        <div x-show="section === 'payments'" class="mt-6">

          {{-- ---- LIST VIEW ---- --}}
          <div x-show="payView === 'list'">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('invoices.pay_intro') }}</p>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
              <x-button variant="primary" icon="plus" @click="open = ! open">{{ __('invoices.pay_add') }}</x-button>
              <div x-show="open" x-cloak x-transition class="absolute right-0 z-50 mt-2 w-52 overflow-hidden rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
                <template x-for="t in payTypeOptions" :key="t.type">
                  <button type="button" @click="newPayment(t.type); open = false" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-accent/5">
                    <span class="ll-chip h-7 w-7 rounded-lg" :style="{ background: payTint(t.type) }">@include('invoices._payment_icon', ['expr' => 't.type', 'cls' => 'h-4 w-4 text-white'])</span>
                    <span x-text="payTypeLabel(t.type)"></span>
                  </button>
                </template>
              </div>
            </div>
          </div>

          {{-- Empty state --}}
          <template x-if="! sortedPayments.length">
            <x-empty-state icon="wallet">{{ __('invoices.pay_empty') }}</x-empty-state>
          </template>

          {{-- List (iOS grouped). Bank accounts open a statement/transaction view. --}}
          <template x-if="sortedPayments.length">
            <div class="ll-card !p-0 mt-4 overflow-hidden">
              <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="pm in sortedPayments" :key="pm.id">
                  <div class="group flex items-center gap-3 px-4 py-3 hover:bg-accent/5"
                       :class="pm.type === 'bank' && 'cursor-pointer'"
                       @click="pm.type === 'bank' && openAccount(pm)">
                    <template x-if="payIconSrc(pm)"><img :src="payIconSrc(pm)" alt="" class="h-9 w-9 shrink-0 rounded-xl bg-white object-contain p-0.5 ring-1 ring-black/[0.06] dark:ring-white/10"></template>
                    <template x-if="! payIconSrc(pm)"><span class="ll-chip h-9 w-9 rounded-xl shrink-0" :style="{ background: payTint(pm.type) }">@include('invoices._payment_icon', ['expr' => 'pm.type', 'cls' => 'h-4.5 w-4.5 text-white'])</span></template>
                    <div class="min-w-0 flex-1">
                      <p class="flex items-center gap-2 truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                        <span class="truncate" x-text="pm.label"></span>
                        <template x-if="pm.business"><x-badge variant="accent">{{ __('invoices.pay_business') }}</x-badge></template>
                      </p>
                      <p class="truncate text-xs text-gray-500 dark:text-gray-400 tabular-nums" x-text="paySubtitle(pm) || payTypeLabel(pm.type)"></p>
                    </div>
                    <template x-if="pm.type === 'bank' && accountTxCount(pm)">
                      <span class="shrink-0 text-right text-sm font-medium tabular-nums" :class="accountBalance(pm) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-200'" x-text="fmtMoney(accountBalance(pm))"></span>
                    </template>
                    <div class="flex shrink-0 items-center gap-1 md:opacity-0 md:group-hover:opacity-100" @click.stop>
                      <x-icon-button name="pencil" tone="gray" size="sm" @click="editPayment(pm)" :aria-label="__('common.edit')" />
                      <x-icon-button name="trash" tone="red" size="sm" @click="removePayment(pm)" :aria-label="__('common.delete')" />
                    </div>
                    <template x-if="pm.type === 'bank'"><x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" /></template>
                  </div>
                </template>
              </div>
            </div>
          </template>
          </div>

          {{-- ---- ACCOUNT DETAIL VIEW (statement + transactions) ---- --}}
          <div x-show="payView === 'account'" x-cloak>
            <template x-if="payAccount">
              <div>
                <button type="button" @click="backToPayments()" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-accent dark:text-gray-400">
                  <x-icon name="chevron-left" class="h-4 w-4" />{{ __('invoices.pay_title') }}
                </button>

                {{-- Account header --}}
                <div class="ll-card">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                      <template x-if="payIconSrc(payAccount)"><img :src="payIconSrc(payAccount)" alt="" class="h-11 w-11 rounded-2xl bg-white object-contain p-1 ring-1 ring-black/[0.06] dark:ring-white/10"></template>
                      <template x-if="! payIconSrc(payAccount)"><span class="ll-chip h-11 w-11 rounded-2xl" :style="{ background: payTint(payAccount.type) }">@include('invoices._payment_icon', ['expr' => 'payAccount.type', 'cls' => 'h-5 w-5 text-white'])</span></template>
                      <div>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="payAccount.label"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums" x-text="paySubtitle(payAccount)"></p>
                      </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                      <template x-if="unlinkedIncomeCount">
                        <x-button variant="secondary" size="sm" icon="link" @click="rematchAll()">{{ __('invoices.match_run') }}</x-button>
                      </template>
                      <template x-if="accountReceiptTotal(payAccount)">
                        <x-button variant="secondary" size="sm" icon="arrow-down-tray" ::disabled="exportBusy" @click="downloadAllReceipts(payAccount)">
                          <span x-show="! exportBusy">{{ __('invoices.export_all') }}</span>
                          <span x-show="exportBusy" x-text="'{{ __('invoices.export_progress') }}'.replace(':done', exportDone).replace(':total', exportTotal)"></span>
                        </x-button>
                      </template>
                      <input type="file" x-ref="stmtFile" accept=".csv,.txt,.sta,.mt940,text/csv,text/plain" class="hidden" @change="importStatement($event.target.files); $event.target.value = ''">
                      <x-button variant="secondary" size="sm" icon="arrow-up-tray" @click="$refs.stmtFile.click()">{{ __('invoices.stmt_import') }}</x-button>
                    </div>
                  </div>
                  {{-- Balance + income/expense + business toggle --}}
                  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                      <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.acct_balance') }}</p>
                      <p class="mt-0.5 text-xl font-semibold tabular-nums" :class="accountBalance(payAccount) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'" x-text="fmtMoney(accountBalance(payAccount))"></p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.acct_income') }}</p>
                      <p class="mt-0.5 text-xl font-semibold tabular-nums text-green-600 dark:text-green-400" x-text="fmtMoney(accountIncome)"></p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.acct_expense') }}</p>
                      <p class="mt-0.5 text-xl font-semibold tabular-nums text-red-600 dark:text-red-400" x-text="fmtMoney(accountExpense)"></p>
                    </div>
                  </div>
                  {{-- Missing-document hint (non-private bookings without a receipt/invoice) --}}
                  <template x-if="documentableTx.length">
                    <div class="mt-4">
                      <template x-if="missingReceipts">
                        <div class="inline-flex items-center gap-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 px-3 py-1.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                          <x-icon name="paper-clip" class="h-4 w-4" />
                          <span x-text="'{{ __('invoices.receipts_missing') }}'.replace(':n', missingReceipts).replace(':total', documentableTx.length)"></span>
                        </div>
                      </template>
                      <template x-if="! missingReceipts">
                        <div class="inline-flex items-center gap-2 rounded-xl bg-green-50 dark:bg-green-950/40 px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-400">
                          <x-icon name="check-circle" class="h-4 w-4" />{{ __('invoices.receipts_complete') }}
                        </div>
                      </template>
                    </div>
                  </template>
                  <label class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" :checked="payAccount.business" @change="toggleBusiness(payAccount)" class="rounded">
                    {{ __('invoices.pay_business_set') }}
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.pay_business_hint') }}</span>
                  </label>
                </div>

                {{-- VAT summary from the categorised bookings (for the USt calculation) --}}
                <template x-if="accountVat.income.length || accountVat.expense.length || accountVat.undecided">
                  <div class="ll-card mt-6">
                    <div class="flex items-center gap-2">
                      <span class="ll-chip h-7 w-7 rounded-lg" style="background:#e2915a"><x-icon name="receipt-percent" class="h-4 w-4 text-white" /></span>
                      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.vat_summary_title') }}</h3>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                      <div class="rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.vat_output') }}</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-green-600 dark:text-green-400" x-text="fmtMoney(accountVat.outputVat)"></p>
                      </div>
                      <div class="rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.vat_input') }}</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-700 dark:text-gray-200" x-text="fmtMoney(accountVat.inputVat)"></p>
                      </div>
                      <div class="rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.vat_payable') }}</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-accent" x-text="fmtMoney(accountVat.payable)"></p>
                      </div>
                    </div>
                    <p x-show="accountVat.undecided" class="mt-3 text-xs text-amber-600 dark:text-amber-400" x-text="'{{ __('invoices.vat_undecided') }}'.replace(':n', accountVat.undecided)"></p>
                    <p x-show="accountVat.privateSum" class="mt-1 text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('invoices.vat_private') }}'.replace(':sum', fmtMoney(accountVat.privateSum))"></p>
                  </div>
                </template>

                {{-- Transactions --}}
                <template x-if="! accountTx.length">
                  <x-empty-state icon="banknotes" class="mt-6 py-14">{{ __('invoices.acct_no_tx') }}</x-empty-state>
                </template>
                <template x-if="accountTx.length">
                  <div class="ll-card !p-0 mt-6 overflow-hidden overflow-x-auto">
                    <table class="min-w-full text-sm">
                      <thead class="border-b border-black/[0.06] dark:border-white/10 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <tr>
                          <th class="px-4 py-3">{{ __('invoices.col_date') }}</th>
                          <th class="px-4 py-3">{{ __('invoices.tx_type') }}</th>
                          <th class="px-4 py-3">{{ __('invoices.tx_counterparty') }}</th>
                          <th class="px-4 py-3">{{ __('invoices.tx_purpose') }}</th>
                          <th class="px-4 py-3 text-right">{{ __('invoices.col_total') }}</th>
                          <th class="px-4 py-3">{{ __('invoices.tx_vat') }}</th>
                          <th class="px-4 py-3 text-center">{{ __('invoices.tx_receipt') }}</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="tx in pagedAccountTx" :key="tx.id">
                          <tr class="hover:bg-accent/5">
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-gray-500 dark:text-gray-400" x-text="tx.date"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400" x-text="txTypeLabel(tx)"></td>
                            <td class="max-w-[16rem] px-4 py-2.5">
                              <p class="truncate text-gray-800 dark:text-gray-200" x-text="tx.counterparty || '—'" :title="tx.counterparty"></p>
                              <p x-show="tx.iban && ! tx.invoiceId" class="truncate text-xs text-gray-400 dark:text-gray-500 tabular-nums" x-text="tx.iban"></p>
                              <button type="button" x-show="tx.invoiceId" @click="openInvoiceById(tx.invoiceId)" class="inline-flex items-center gap-1 text-xs font-medium text-accent hover:underline">
                                <x-icon name="link" class="h-3 w-3" /><span x-text="'{{ __('invoices.match_invoice') }}'.replace(':n', tx.invoiceNumber || '')"></span>
                              </button>
                            </td>
                            <td class="max-w-[22rem] truncate px-4 py-2.5 text-gray-500 dark:text-gray-400" x-text="tx.purpose" :title="tx.purpose"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium tabular-nums" :class="tx.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'" x-text="fmtMoney(tx.amount, tx.currency)"></td>
                            {{-- VAT category: a compact styled select; unselected shown in soft amber. --}}
                            <td class="whitespace-nowrap px-4 py-2.5">
                              <div class="relative inline-flex items-center">
                                <select @change="setVatCat(tx, $event.target.value)"
                                  class="appearance-none rounded-lg border-0 py-1 pl-2.5 pr-6 text-xs font-medium focus:ring-2 focus:ring-accent"
                                  :class="tx.vatCat ? 'bg-black/[0.04] dark:bg-white/10 text-gray-700 dark:text-gray-200' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400'">
                                  <option value="" :selected="! tx.vatCat">{{ __('invoices.vatcat_none') }}</option>
                                  <template x-for="c in vatCats" :key="c">
                                    <option :value="c" :selected="tx.vatCat === c" x-text="vatCatLabel(c)"></option>
                                  </template>
                                </select>
                                <x-icon name="chevron-down" class="pointer-events-none absolute right-1.5 h-3 w-3 text-gray-400" />
                              </div>
                            </td>
                            {{-- Receipts: on every booking (income too). Paperclip + count; opens the panel. --}}
                            <td class="whitespace-nowrap px-4 py-2.5 text-center">
                              <button type="button" @click="openReceipts(tx)"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                                :class="receiptCount(tx) ? 'text-accent hover:bg-accent/10' : 'text-gray-400 hover:bg-black/[0.04] dark:hover:bg-white/10'"
                                :title="receiptCount(tx) ? '{{ __('invoices.tx_receipt_count') }}'.replace(':n', receiptCount(tx)) : '{{ __('invoices.tx_receipt_add') }}'">
                                <x-icon name="paper-clip" class="h-4 w-4" />
                                <span x-show="receiptCount(tx)" x-text="receiptCount(tx)"></span>
                              </button>
                            </td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                    {{-- Pagination --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black/[0.06] dark:border-white/10 px-4 py-2.5 text-sm">
                      <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ __('invoices.per_page') }}</span>
                        <template x-for="n in [10, 25]" :key="n">
                          <button type="button" @click="setTxPerPage(n)" class="rounded-md px-2 py-1 font-medium transition-colors" :class="txPerPage === n ? 'bg-accent/10 text-accent' : 'text-gray-500 hover:text-accent dark:text-gray-400'" x-text="n"></button>
                        </template>
                      </div>
                      <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span x-text="'{{ __('invoices.page_of') }}'.replace(':p', txPage).replace(':n', txPageCount)"></span>
                        <x-icon-button name="chevron-left" tone="gray" size="sm" ::disabled="txPage <= 1" @click="txGoto(txPage - 1)" aria-label="prev" />
                        <x-icon-button name="chevron-right" tone="gray" size="sm" ::disabled="txPage >= txPageCount" @click="txGoto(txPage + 1)" aria-label="next" />
                      </div>
                    </div>
                  </div>
                </template>

              </div>
            </template>
          </div>

          {{-- ---- STATEMENT IMPORT WIZARD ---- --}}
          <div x-show="stmt" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cancelStatement()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelStatement()"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="stmt">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.stmt_title') }}</h3>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="cancelStatement()" :aria-label="__('common.close')" />
                  </div>

                  {{-- Column mapping (unknown CSV) --}}
                  <template x-if="stmt.stage === 'map'">
                    <div class="min-h-0 flex-1 overflow-auto px-5 py-4">
                      <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('invoices.stmt_map_hint') }}</p>
                      <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <template x-for="f in txFields" :key="f">
                          <label class="text-sm text-gray-700 dark:text-gray-300">
                            <span x-text="txFieldLabel(f)"></span><span x-show="f === 'date' || f === 'amount'" class="text-red-500">*</span>
                            <select x-model="stmt.mapping[f]" class="mt-1 block w-full rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                              <option value="">—</option>
                              <template x-for="h in stmt.header" :key="h"><option :value="h" x-text="h"></option></template>
                            </select>
                          </label>
                        </template>
                      </div>
                      <div class="mt-5 flex items-center justify-end gap-3">
                        <x-button variant="secondary" @click="cancelStatement()">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" ::disabled="! stmtMapReady()" @click="applyStmtMapping()">{{ __('invoices.stmt_map_apply') }}</x-button>
                      </div>
                    </div>
                  </template>

                  {{-- Preview + confirm --}}
                  <template x-if="stmt.stage === 'preview'">
                    <div class="flex min-h-0 flex-1 flex-col">
                      <div class="border-b border-black/[0.06] dark:border-white/10 px-5 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                        <span x-text="stmt.format + ' · '"></span>
                        <span x-text="'{{ __('invoices.stmt_summary') }}'.replace(':new', stmt.fresh.length).replace(':dupes', stmt.dupes)"></span>
                        <span x-show="stmt.updates && stmt.updates.length" x-text="' · ' + '{{ __('invoices.stmt_updated') }}'.replace(':n', stmt.updates ? stmt.updates.length : 0)"></span>
                      </div>
                      <div class="min-h-0 flex-1 overflow-auto px-5 py-3">
                        <template x-if="! stmt.fresh.length"><p class="py-8 text-center text-sm text-gray-400 dark:text-gray-500" x-text="(stmt.updates && stmt.updates.length) ? '{{ __('invoices.stmt_only_updates') }}'.replace(':n', stmt.updates.length) : '{{ __('invoices.stmt_nothing_new') }}'"></p></template>
                        <table x-show="stmt.fresh.length" class="w-full text-sm">
                          <thead class="text-left text-xs text-gray-400 dark:text-gray-500">
                            <tr><th class="pb-2 pr-3">{{ __('invoices.col_date') }}</th><th class="pb-2 pr-3">{{ __('invoices.tx_counterparty') }}</th><th class="pb-2 pr-3">{{ __('invoices.tx_purpose') }}</th><th class="pb-2 text-right">{{ __('invoices.col_total') }}</th></tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                            <template x-for="(tx, i) in stmt.fresh" :key="i">
                              <tr>
                                <td class="whitespace-nowrap py-2 pr-3 tabular-nums text-gray-500 dark:text-gray-400" x-text="tx.date"></td>
                                <td class="max-w-[12rem] truncate py-2 pr-3 text-gray-800 dark:text-gray-200" x-text="tx.counterparty || '—'"></td>
                                <td class="max-w-[18rem] truncate py-2 pr-3 text-gray-500 dark:text-gray-400" x-text="tx.purpose"></td>
                                <td class="whitespace-nowrap py-2 text-right tabular-nums" :class="tx.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'" x-text="fmtMoney(tx.amount, tx.currency)"></td>
                              </tr>
                            </template>
                          </tbody>
                        </table>
                      </div>
                      <div class="flex items-center justify-end gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                        <x-button variant="secondary" @click="cancelStatement()">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" ::disabled="! stmt.fresh.length && ! (stmt.updates && stmt.updates.length)" @click="confirmStatementImport()">
                          <span x-text="'{{ __('invoices.stmt_confirm') }}'.replace(':n', stmt.fresh.length)"></span>
                        </x-button>
                      </div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </div>

          {{-- ---- RECEIPTS PANEL (Belege for a transaction) ---- --}}
          <div x-show="receiptTx" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeReceipts()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeReceipts()"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="receiptTx">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="flex items-start justify-between gap-3 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <div class="min-w-0">
                      <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.receipts_title') }}</h3>
                      <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="(receiptTx.counterparty || receiptTx.purpose || '') + ' · ' + fmtMoney(receiptTx.amount, receiptTx.currency) + ' · ' + receiptTx.date"></p>
                    </div>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeReceipts()" :aria-label="__('common.close')" />
                  </div>
                  <div class="min-h-0 flex-1 overflow-auto px-5 py-4" x-data="{ drag: false }"
                       @dragover.prevent="drag = true" @dragenter.prevent="drag = true" @dragleave.prevent="drag = false"
                       @drop.prevent="drag = false; uploadReceipts($event.dataTransfer.files)">
                    {{-- Existing receipts --}}
                    <template x-if="receiptCount(receiptTx)">
                      <div class="space-y-2">
                        <template x-for="(r, ri) in receiptTx.receipts" :key="ri">
                          <div class="flex items-center gap-3 rounded-xl border border-black/[0.06] dark:border-white/10 px-3 py-2">
                            <span class="ll-chip h-8 w-8 rounded-lg shrink-0" :style="{ background: r.locked ? '#7066f5' : '#3fae9f' }"><x-icon name="document" class="h-4 w-4 text-white" /></span>
                            <button type="button" @click="openReceipt(r)" class="min-w-0 flex-1 truncate text-left text-sm text-gray-800 dark:text-gray-200 hover:text-accent" x-text="r.name || '{{ __('invoices.receipt') }}'" :title="r.name"></button>
                            <template x-if="r.locked"><x-badge variant="accent">{{ __('invoices.receipt_from_invoice') }}</x-badge></template>
                            <x-icon-button name="arrow-down-tray" tone="gray" size="sm" @click="openReceipt(r)" :aria-label="__('invoices.receipt')" />
                            <template x-if="! r.locked"><x-icon-button name="trash" tone="red" size="sm" @click="removeReceipt(receiptTx, r)" :aria-label="__('common.delete')" /></template>
                          </div>
                        </template>
                      </div>
                    </template>
                    {{-- Drop zone / empty state --}}
                    <div class="mt-3 rounded-2xl border-2 border-dashed px-4 py-8 text-center text-sm transition-colors"
                         :class="drag ? 'border-accent bg-accent/5 text-accent' : 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500'">
                      <x-icon name="arrow-up-tray" class="mx-auto h-6 w-6" />
                      <p class="mt-2" x-text="drag ? '{{ __('invoices.receipts_drop') }}' : '{{ __('invoices.receipts_dnd') }}'"></p>
                    </div>
                  </div>
                  <div class="flex items-center justify-between gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <span x-show="receiptBusy" class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"><x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('invoices.receipts_uploading') }}</span>
                    <span x-show="! receiptBusy"></span>
                    <div class="flex items-center gap-2">
                      {{-- Link an issued invoice to an incoming payment --}}
                      <template x-if="receiptTx.amount > 0 && ! receiptTx.invoiceId">
                        <x-button variant="secondary" size="sm" icon="link" @click="openInvoicePicker(receiptTx)">{{ __('invoices.match_link') }}</x-button>
                      </template>
                      <input type="file" x-ref="receiptFile" accept="application/pdf,image/*" multiple class="hidden" @change="uploadReceipts($event.target.files); $event.target.value = ''">
                      <x-button variant="primary" size="sm" icon="arrow-up-tray" ::disabled="receiptBusy" @click="$refs.receiptFile.click()">{{ __('invoices.receipts_add') }}</x-button>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {{-- ---- RECEIPT PREVIEW (quick look, decrypted client-side) ---- --}}
          <div x-show="receiptPreview" x-cloak class="fixed inset-0 z-[1140] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeReceiptPreview()">
            <div class="absolute inset-0 bg-gray-900/70" @click="closeReceiptPreview()"></div>
            <div class="relative flex h-[90vh] w-full max-w-4xl flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="receiptPreview">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="flex items-center gap-3 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <x-icon name="document" class="h-5 w-5 shrink-0 text-gray-400" />
                    <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="receiptPreview.name" :title="receiptPreview.name"></p>
                    <x-icon-button name="arrow-up-right" tone="gray" size="sm" @click="openReceiptInTab()" :aria-label="__('invoices.receipt_open_tab')" />
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeReceiptPreview()" :aria-label="__('common.close')" />
                  </div>
                  <div class="min-h-0 flex-1 overflow-auto bg-gray-50 dark:bg-black/40">
                    <template x-if="previewIsImage">
                      <div class="flex h-full w-full items-center justify-center p-3"><img :src="receiptPreview.url" alt="" class="max-h-full max-w-full object-contain"></div>
                    </template>
                    <template x-if="previewIsPdf">
                      <iframe :src="receiptPreview.url" class="h-full w-full" title="receipt"></iframe>
                    </template>
                    <template x-if="! previewIsImage && ! previewIsPdf">
                      <div class="flex h-full flex-col items-center justify-center gap-3 p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        <x-icon name="document" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <p>{{ __('invoices.receipt_no_preview') }}</p>
                        <x-button variant="secondary" size="sm" icon="arrow-up-right" @click="openReceiptInTab()">{{ __('invoices.receipt_open_tab') }}</x-button>
                      </div>
                    </template>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {{-- ---- INVOICE PICKER (link an issued invoice to an income booking) ---- --}}
          <div x-show="invoicePicker" x-cloak class="fixed inset-0 z-[1110] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="invoicePicker = null">
            <div class="absolute inset-0 bg-gray-900/50" @click="invoicePicker = null"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.match_pick_title') }}</h3>
                <x-icon-button name="x-mark" tone="gray" size="sm" @click="invoicePicker = null" :aria-label="__('common.close')" />
              </div>
              <div class="min-h-0 flex-1 overflow-auto px-2 py-2">
                <template x-if="! pickerInvoices.length"><p class="px-3 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('invoices.match_none') }}</p></template>
                <template x-for="pi in pickerInvoices" :key="pi.id">
                  <button type="button" @click="linkInvoice(invoicePicker, pi)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-accent/5">
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"><span x-text="pi.number"></span> · <span x-text="pi.customer?.name || '—'"></span></p>
                      <p class="text-xs text-gray-400 dark:text-gray-500 tabular-nums" x-text="pi.issueDate + ' · ' + fmtMoney(computeTotals(pi).gross, pi.currency)"></p>
                    </div>
                    <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium" :class="pi.status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400' : 'bg-black/[0.04] text-gray-500 dark:bg-white/10 dark:text-gray-300'" x-text="pi.status === 'paid' ? '{{ __('invoices.status_paid') }}' : '{{ __('invoices.status_sent') }}'"></span>
                  </button>
                </template>
              </div>
            </div>
          </div>

          {{-- Editor modal --}}
          <div x-show="payEditing" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cancelPayment()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelPayment()"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="payEditing">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="flex items-center gap-2.5 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <span class="ll-chip h-8 w-8 rounded-xl" :style="{ background: payTint(payEditing.type) }">@include('invoices._payment_icon', ['expr' => 'payEditing.type', 'cls' => 'h-4.5 w-4.5 text-white'])</span>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="payIsNew ? payTypeLabel(payEditing.type) : payEditing.label"></h3>
                    <x-icon-button name="x-mark" tone="gray" size="sm" class="ml-auto" @click="cancelPayment()" :aria-label="__('common.close')" />
                  </div>
                  <div class="min-h-0 flex-1 space-y-3 overflow-auto px-5 py-4">
                    {{-- Common: label + holder --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_label') }} <span class="text-red-500">*</span></label>
                      <input type="text" x-model="payEditing.label" placeholder="{{ __('invoices.pay_label_ph') }}" :class="payErr('label') && 'ring-2 ring-red-400 border-red-400'" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      <p x-show="payErr('label')" class="mt-1 text-xs text-red-500">{{ __('invoices.pay_required') }}</p>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_holder') }}</label>
                      <input type="text" x-model="payEditing.holder" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                    </div>

                    {{-- Bank fields --}}
                    <template x-if="payEditing.type === 'bank'">
                      <div class="space-y-3">
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_iban') }} <span class="text-red-500">*</span></label>
                          <input type="text" x-model="payEditing.iban" placeholder="DE00 0000 0000 0000 0000 00" :class="payErr('iban') && 'ring-2 ring-red-400 border-red-400'" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm font-mono tabular-nums">
                          <p x-show="payErr('iban')" class="mt-1 text-xs text-red-500">{{ __('invoices.pay_iban_or_acct') }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                          <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_bic') }}</label>
                            <input type="text" x-model="payEditing.bic" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                          </div>
                          <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_bank_name') }}</label>
                            <input type="text" x-model="payEditing.bankName" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                          </div>
                        </div>
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_account_no') }}</label>
                          <input type="text" x-model="payEditing.accountNumber" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm tabular-nums">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_url') }}</label>
                          <input type="url" x-model="payEditing.url" placeholder="https://meine-bank.de" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                          <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.pay_url_hint') }}</p>
                        </div>
                      </div>
                    </template>

                    {{-- Card fields --}}
                    <template x-if="payEditing.type === 'card'">
                      <div class="space-y-3">
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_card_number') }} <span class="text-red-500">*</span></label>
                          <input type="text" inputmode="numeric" x-model="payEditing.cardNumber" @input="payCardInput()" placeholder="•••• •••• •••• ••••" :class="payErr('cardNumber') && 'ring-2 ring-red-400 border-red-400'" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm font-mono tabular-nums">
                          <p x-show="payErr('cardNumber')" class="mt-1 text-xs text-red-500">{{ __('invoices.pay_required') }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                          <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_card_network') }}</label>
                            <select x-model="payEditing.cardNetwork" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                              <option value="visa">Visa</option>
                              <option value="mastercard">Mastercard</option>
                              <option value="amex">Amex</option>
                              <option value="other">{{ __('invoices.pay_type_other') }}</option>
                            </select>
                          </div>
                          <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_card_expiry') }}</label>
                            <input type="text" x-model="payEditing.cardExpiry" placeholder="MM/YY" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm tabular-nums">
                          </div>
                        </div>
                      </div>
                    </template>

                    {{-- PayPal fields --}}
                    <template x-if="payEditing.type === 'paypal'">
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_email') }} <span class="text-red-500">*</span></label>
                        <input type="email" x-model="payEditing.email" placeholder="name@example.com" :class="payErr('email') && 'ring-2 ring-red-400 border-red-400'" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                        <p x-show="payErr('email')" class="mt-1 text-xs text-red-500">{{ __('invoices.pay_required') }}</p>
                      </div>
                    </template>

                    {{-- Note (all types) --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.pay_note') }}</label>
                      <textarea x-model="payEditing.note" rows="2" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                    </div>
                  </div>
                  <div class="flex items-center justify-end gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <x-button variant="secondary" @click="cancelPayment()">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="savePayment()">{{ __('common.save') }}</x-button>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        {{-- ===================== INVOICES TAB (list + editor) ===================== --}}
        <div x-show="section === 'invoices'" class="mt-6">
        {{-- ===================== LIST ===================== --}}
        <div x-show="view === 'list'">
          <div class="flex flex-wrap items-center justify-end gap-3">
            <input type="file" x-ref="pdfImport" accept="application/pdf,.pdf" multiple class="hidden" @change="importPdfs($event.target.files); $event.target.value = ''">
            <x-button variant="secondary" @click="$refs.pdfImport.click()" icon="arrow-up-tray">{{ __('invoices.import_pdf') }}</x-button>
            <x-button variant="primary" @click="newInvoice()"><x-icon name="plus" class="mr-1.5 h-4 w-4" />{{ __('invoices.new') }}</x-button>
          </div>

          {{-- GoBD: unique, gapless numbers. If a concurrent finalize on two devices ever
               produced a duplicate number, alert the owner prominently to correct it. --}}
          <template x-if="duplicateNumbers.length">
            <x-alert variant="error" class="mt-4">
              <p class="font-semibold">{{ __('invoices.dup_warning_title') }}</p>
              <p class="mt-0.5 text-xs" x-text="'{{ __('invoices.dup_warning_body') }} ' + duplicateNumbers.join(', ')"></p>
            </x-alert>
          </template>

          @unless ($s->company_name)
            <p class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
              {{ __('invoices.company_missing') }} <a href="{{ route('settings.company.edit') }}" class="font-medium underline">{{ __('settings.company_section') }}</a>
            </p>
          @endunless

          {{-- Numbering cycle (per year): next number + a locked/reset control. --}}
          <div class="ll-card mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="ll-chip h-8 w-8 rounded-xl" style="background:#6b7280"><x-icon name="hashtag" class="h-4.5 w-4.5 text-white" /></span>
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ __('invoices.cycle_title') }} · <span x-text="currentYear"></span>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  <span x-text="'{{ __('invoices.cycle_next') }}: '"></span><span class="tabular-nums font-medium text-gray-700 dark:text-gray-300" x-text="nextNumberPreview"></span>
                  <span x-show="numberingLocked" class="ml-1 text-gray-400 dark:text-gray-500" x-text="'· ' + '{{ __('invoices.cycle_locked') }}'.replace(':n', currentYearInvoices.length)"></span>
                </p>
              </div>
            </div>
            <x-button variant="danger" size="sm" icon="arrow-path" ::disabled="! numberingLocked" @click="resetYearCycle()">
              <span x-text="'{{ __('invoices.cycle_reset') }}'.replace(':year', currentYear)"></span>
            </x-button>
          </div>

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
            <x-empty-state icon="banknotes" class="mt-10 py-16">
              <span class="block text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.empty_title') }}</span>
              <span class="mt-1 block text-sm">{{ __('invoices.empty_hint') }}</span>
            </x-empty-state>
          </template>

          <div x-show="filtered.length" class="ll-card !p-0 mt-4 overflow-hidden overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="border-b border-black/[0.06] dark:border-white/10 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <tr>
                  <th class="px-4 py-3">{{ __('invoices.col_number') }}</th>
                  <th class="px-4 py-3">{{ __('invoices.col_customer') }}</th>
                  <th class="px-4 py-3">{{ __('invoices.col_date') }}</th>
                  <th class="px-4 py-3 text-right">{{ __('invoices.col_total') }}</th>
                  <th class="px-4 py-3">{{ __('invoices.col_status') }}</th>
                  <th class="px-4 py-3 text-right">{{ __('invoices.col_actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="inv in filtered" :key="inv.id">
                  <tr class="group cursor-pointer transition-colors hover:bg-accent/5" @click="open(inv)">
                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100 tabular-nums" x-text="inv.number || @js(__('invoices.draft_label'))"></td>
                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300" x-text="inv.customer?.name || '—'"></td>
                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 tabular-nums" x-text="inv.issueDate"></td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(computeTotals(inv).gross, inv.currency)"></td>
                    <td class="px-4 py-2.5">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{ 'bg-green-500/15 text-green-600 dark:text-green-400': inv.status === 'paid', 'bg-accent/15 text-accent': inv.status === 'sent', 'bg-gray-500/15 text-gray-500 dark:text-gray-400': inv.status === 'draft' }"
                        x-text="statusLabel(inv.status)"></span>
                    </td>
                    <td class="px-4 py-2.5" @click.stop>
                      <div class="flex items-center justify-end gap-1">
                        <x-icon-button name="pencil" size="sm" @click="open(inv)" title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}" />
                        <x-icon-button name="printer" size="sm" @click="printInvoice(inv)" title="{{ __('invoices.print') }}" aria-label="{{ __('invoices.print') }}" />
                        <x-icon-button name="trash" tone="red" size="sm" @click="trash(inv)" title="{{ __('invoices.trash') }}" aria-label="{{ __('invoices.trash') }}" />
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
              <x-icon-button name="arrow-left" @click="backToList()" aria-label="{{ __('common.back') }}" />
              <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums" x-text="current?.number || @js(__('invoices.status_draft'))"></h1>
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="{ 'bg-green-500/15 text-green-600 dark:text-green-400': current?.status === 'paid', 'bg-accent/15 text-accent': current?.status === 'sent', 'bg-gray-500/15 text-gray-500 dark:text-gray-400': current?.status === 'draft' }"
                x-text="statusLabel(current?.status)"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <x-button variant="secondary" x-show="current?.imported && current?.pdf" @click="openOriginalPdf(current)"><x-icon name="document-text" class="mr-1.5 h-4 w-4" />{{ __('invoices.open_original') }}</x-button>
              <x-button variant="secondary" x-show="! current?.imported || ! current?.pdf" @click="printInvoice(current)"><x-icon name="printer" class="mr-1.5 h-4 w-4" />{{ __('invoices.print') }}</x-button>
              <x-button variant="secondary" @click="downloadZugferd(current)" icon="arrow-down-tray" title="{{ __('invoices.zugferd_hint') }}">{{ __('invoices.zugferd') }}</x-button>
              <x-button variant="secondary" x-show="! current?.imported && current?.status === 'draft'" @click="finalize(current)">{{ __('invoices.finalize') }}</x-button>
              <x-button variant="secondary" x-show="! current?.imported && current?.status === 'sent'" @click="markPaid(current)">{{ __('invoices.mark_paid') }}</x-button>
            </div>
          </div>

          {{-- Imported invoices are an immutable record (GoBD): read-only + original PDF. --}}
          <template x-if="current?.imported">
            <x-alert variant="info" class="mt-4 flex items-start gap-2">
              <x-icon name="lock-closed" class="mt-0.5 h-4 w-4 shrink-0" />
              <span>{{ __('invoices.imported_readonly') }}</span>
            </x-alert>
          </template>

          <fieldset :disabled="current?.imported" class="contents">

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
                      <td class="py-1 pl-2 text-right"><x-icon-button name="x-mark" size="sm" @click="removeLine(i)" title="{{ __('invoices.remove') }}" aria-label="{{ __('invoices.remove') }}" /></td>
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
          </fieldset>{{-- /read-only fieldset --}}

          <div class="mt-6 flex justify-end">
            <x-button variant="danger" size="sm" icon="trash" @click="remove(current)">{{ __('invoices.delete') }}</x-button>
          </div>
        </div>
        </template>
        </div>{{-- /section invoices --}}

        {{-- ===================== CUSTOMER PICKER ===================== --}}
        <div x-show="customerPicker" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeCustomerPicker()">
          <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeCustomerPicker()"></div>
          <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.picker_title') }}</h2>
            <input type="search" x-model="custQuery" placeholder="{{ __('invoices.picker_search') }}" class="mt-3 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
            <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
              <template x-if="! custSuggestions().length">
                <x-empty-state class="py-6">{{ __('invoices.picker_empty') }}</x-empty-state>
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

    {{-- ===================== PDF IMPORT REVIEW ===================== --}}
    <template x-teleport="body">
      <div x-show="importReview" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="! importReview?.running && ! importReview?.saving && cancelImport()">
        <div class="absolute inset-0 bg-gray-900/50" @click="! importReview?.running && ! importReview?.saving && cancelImport()"></div>
        <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.import_title') }}</h3>
            <x-icon-button name="x-mark" tone="gray" size="sm" @click="cancelImport()" x-show="! importReview?.running && ! importReview?.saving" aria-label="{{ __('common.close') }}" />
          </div>

          {{-- Parsing progress --}}
          <template x-if="importReview?.running">
            <div class="flex items-center justify-center gap-3 px-5 py-16 text-sm text-gray-500 dark:text-gray-400">
              <x-icon name="arrow-path" class="h-5 w-5 animate-spin" />
              <span x-text="'{{ __('invoices.import_parsing') }}'.replace(':done', importReview.done).replace(':total', importReview.total)"></span>
            </div>
          </template>

          {{-- Saving / upload progress --}}
          <template x-if="importReview?.saving">
            <div class="flex flex-col items-center justify-center gap-4 px-5 py-16">
              <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                <x-icon name="arrow-path" class="h-5 w-5 animate-spin" />
                <span x-text="'{{ __('invoices.import_saving') }}'.replace(':done', importReview.saved).replace(':total', importReview.saveTotal)"></span>
              </div>
              <div class="h-2 w-64 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full ll-accent transition-all" :style="{ width: (importReview.saveTotal ? Math.round(importReview.saved / importReview.saveTotal * 100) : 0) + '%' }"></div>
              </div>
            </div>
          </template>

          {{-- Review list --}}
          <template x-if="importReview && ! importReview.running && ! importReview.saving">
            <div class="flex min-h-0 flex-1 flex-col">
              <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                <span x-text="'{{ __('invoices.import_summary') }}'.replace(':n', importReview.items.length)"></span>
                <span x-show="importReview.failed" class="text-red-600 dark:text-red-400" x-text="' · ' + '{{ __('invoices.import_failed_n') }}'.replace(':n', importReview.failed)"></span>
              </div>
              <div class="min-h-0 flex-1 overflow-auto px-5 py-3">
                <table class="w-full text-sm">
                  <thead class="text-left text-xs text-gray-400 dark:text-gray-500">
                    <tr>
                      <th class="pb-2 pr-2"></th>
                      <th class="pb-2 pr-3">{{ __('invoices.col_number') }}</th>
                      <th class="pb-2 pr-3">{{ __('invoices.col_date') }}</th>
                      <th class="pb-2 pr-3">{{ __('invoices.col_customer') }}</th>
                      <th class="pb-2 pl-3 text-right">{{ __('invoices.col_total') }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                    <template x-for="row in importReview.items" :key="row.id">
                      <tr :class="row._warnings.length ? 'text-amber-700 dark:text-amber-400' : 'text-gray-800 dark:text-gray-200'">
                        <td class="py-2 pr-2"><input type="checkbox" x-model="row.selected" class="rounded"></td>
                        <td class="py-2 pr-3 tabular-nums" x-text="row.number || '—'"></td>
                        <td class="py-2 pr-3 tabular-nums" x-text="row.issueDate || '—'"></td>
                        <td class="max-w-[16rem] truncate py-2 pr-3" x-text="row.customer.name || '—'" :title="row.customer.name"></td>
                        <td class="py-2 pl-3 text-right tabular-nums" x-text="fmtMoney((row._parsedGross ?? computeTotals(row).gross), row.currency, 'de')"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
              <div class="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.import_hint') }}</span>
                <div class="flex items-center gap-3">
                  <x-button variant="secondary" @click="cancelImport()">{{ __('common.cancel') }}</x-button>
                  <x-button variant="primary" @click="confirmImport()" ::disabled="! importSelectedCount">
                    <span x-text="'{{ __('invoices.import_confirm') }}'.replace(':n', importSelectedCount)"></span>
                  </x-button>
                </div>
              </div>
            </div>
          </template>
        </div>
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
