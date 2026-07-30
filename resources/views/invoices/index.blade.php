@php
    $s = \App\Models\UserSetting::for(auth()->id());
    // Daily-refreshed EUR FX rates (X→EUR) for fuzzy amount suggestions; config default until the first fetch.
    $fxRates = \Illuminate\Support\Facades\Cache::get(\App\Console\Commands\FetchExchangeRates::CACHE_KEY)['rates'] ?? config('finance.fx_default');
@endphp
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
        fxRates: @js($fxRates),
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
        status_draft: @js(__('invoices.status_draft')),
        version_reason_title: @js(__('invoices.version_reason_title')),
        version_reason_ph: @js(__('invoices.version_reason_ph')),
        version_reason_required: @js(__('invoices.version_reason_required')),
        version_saved: @js(__('invoices.version_saved')),
        version_failed: @js(__('invoices.version_failed')),
        version_finalized: @js(__('invoices.version_finalized')),
        version_paid: @js(__('invoices.version_paid')),
        version_sent: @js(__('invoices.version_sent')),
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
        receipt: @js(__('invoices.receipt')),
        invoice_word: @js(__('invoices.invoice_word')),
        receipt_dupes_skipped: @js(__('invoices.receipt_dupes_skipped')),
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
              @php $tabs = ['dashboard' => 'tab_dashboard', 'invoices' => 'tab_invoices', 'payments' => 'tab_payments', 'receipts' => 'tab_receipts', 'projects' => 'tab_projects', 'partners' => 'tab_partners', 'stats' => 'tab_stats', 'settings' => 'tab_settings']; @endphp
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

        {{-- Global business/private scope — filters every data tab consistently --}}
        <div x-show="section !== 'settings'" class="mt-4 flex items-center gap-2">
          <span class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.scope_label') }}</span>
          <div class="inline-flex rounded-xl bg-black/[0.04] dark:bg-white/5 p-0.5">
            @php $scopes = ['all' => 'project_scope_all', 'business' => 'project_kind_business', 'private' => 'project_kind_private']; @endphp
            @foreach ($scopes as $sk => $slbl)
              <button type="button" @click="setFinanceScope('{{ $sk }}')" class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="financeScope === '{{ $sk }}' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 dark:text-gray-400'">{{ __('invoices.'.$slbl) }}</button>
            @endforeach
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
        <div x-show="section === 'receipts'" class="mt-6 relative" x-data="{ drag: false }"
             @dragover.prevent="drag = true" @dragenter.prevent="drag = true"
             @dragleave.prevent="if ($event.target === $el) drag = false"
             @drop.prevent="drag = false; if ($event.dataTransfer?.files?.length && (transactions || []).length) uploadReceiptsAuto($event.dataTransfer.files)">
          {{-- Drop receipts anywhere on the tab to upload + auto-match them by amount --}}
          <div x-show="drag" x-cloak class="pointer-events-none absolute inset-0 z-40 flex items-center justify-center rounded-2xl border-2 border-dashed border-accent bg-accent/10">
            <span class="rounded-xl bg-white/90 px-4 py-2 text-sm font-medium text-accent shadow dark:bg-[#1c1c1e]/90">{{ __('invoices.receipts_drop_hint') }}</span>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <input type="search" x-model.debounce.200ms="receiptQuery" @input="recPage = 1" placeholder="{{ __('invoices.receipts_search') }}" class="w-full max-w-xs rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
            <div class="flex items-center gap-3">
              <p class="text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('invoices.receipts_count') }}'.replace(':n', allReceipts.length)"></p>
              <template x-if="allReceipts.length">
                <x-button variant="secondary" size="sm" icon="arrow-path" ::disabled="reanalyzeBusy" @click="reanalyzeAllReceipts(true)">
                  <span x-show="! reanalyzeBusy">{{ __('invoices.receipts_rescan_all') }}</span>
                  <span x-show="reanalyzeBusy" x-text="reanalyzeProgress + ' / ' + reanalyzeTotal"></span>
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
            <div class="relative flex h-[80vh] max-h-[80vh] w-full max-w-[75vw] flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="receiptAssign.length">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <h3 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100" x-text="(receiptAssign[0]?.up?.name || '{{ __('invoices.receipt') }}')"></h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      <span x-text="'{{ __('invoices.assign_intro') }}'.replace(':n', receiptAssign.length)"></span>
                      <template x-if="receiptAssign[0]?.total != null"><span> · <span x-text="fmtMoney(receiptAssign[0]?.total)"></span></span></template>
                    </p>
                  </div>
                  <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                    {{-- Inline preview of the receipt being assigned (so it can be matched by eye) --}}
                    <div class="min-h-0 shrink-0 border-b md:border-b-0 md:border-r border-black/[0.06] dark:border-white/10 md:w-1/2">
                      <div class="h-56 w-full overflow-hidden bg-gray-50 dark:bg-[#111] md:h-full">
                        <template x-if="assignPreview && assignPreviewIsPdf">
                          <iframe :src="assignPreview.url" class="h-full w-full" title="preview"></iframe>
                        </template>
                        <template x-if="assignPreview && assignPreviewIsImage">
                          <div class="flex h-full w-full items-center justify-center p-2"><img :src="assignPreview.url" class="max-h-full max-w-full object-contain" alt="preview"></div>
                        </template>
                        <template x-if="! assignPreview">
                          <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">{{ __('invoices.assign_preview_loading') }}</div>
                        </template>
                      </div>
                    </div>
                    {{-- Candidate bookings: sticky search on top, then amount-matches OR search results --}}
                    <div class="flex min-h-0 flex-1 flex-col md:w-1/2">
                      <div class="border-b border-black/[0.06] dark:border-white/10 p-2">
                        <input type="search" x-model.debounce.200ms="assignQuery" placeholder="{{ __('invoices.assign_search_ph') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                      <div class="min-h-0 flex-1 overflow-auto px-2 py-2">
                        {{-- No query → amount matches (auto-suggested by the receipt total) --}}
                        <template x-if="! assignQuery.trim()">
                          <div>
                            <template x-if="receiptAssign[0]?.cands?.length">
                              <div class="px-2 pb-1 pt-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ __('invoices.assign_by_amount') }}</div>
                            </template>
                            <template x-for="cand in (receiptAssign[0]?.cands || [])" :key="'m'+cand.id">
                              <button type="button" @click="assignPending(0, cand)" class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left hover:bg-accent/5">
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200"><span x-text="cand.date"></span> · <span x-text="cand.counterparty || cand.purpose || '—'"></span></span>
                                <span class="shrink-0 text-sm tabular-nums" :class="cand.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'" x-text="fmtMoney(cand.amount, cand.currency)"></span>
                              </button>
                            </template>
                            <template x-if="! receiptAssign[0]?.cands?.length">
                              <div class="px-3 py-6 text-center text-xs text-gray-400">{{ __('invoices.assign_search_hint') }}</div>
                            </template>
                          </div>
                        </template>
                        {{-- Query → search results --}}
                        <template x-if="assignQuery.trim()">
                          <div>
                            <template x-for="cand in assignCandidates()" :key="'a'+cand.id">
                              <button type="button" @click="assignPending(0, cand)" class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left hover:bg-accent/5">
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300"><span x-text="cand.date"></span> · <span x-text="cand.counterparty || cand.purpose || '—'"></span></span>
                                <span class="shrink-0 text-xs tabular-nums text-gray-400" x-text="fmtMoney(cand.amount, cand.currency)"></span>
                              </button>
                            </template>
                            <template x-if="! assignCandidates().length">
                              <div class="px-3 py-4 text-center text-xs text-gray-400">{{ __('invoices.assign_no_match') }}</div>
                            </template>
                          </div>
                        </template>
                      </div>
                    </div>
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
                <template x-for="doc in pagedReceipts" :key="doc.r.id">
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
              @include('invoices._pagination', ['page' => 'recPage', 'perPage' => 'recPerPage', 'pageCount' => 'recPageCount', 'setPerPage' => 'setRecPerPage', 'goto' => 'recGoto'])
            </div>
          </template>

          {{-- Receipt document detail (metadata, link, notes, tags, category, contact) --}}
          <div x-show="receiptDoc" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeReceiptDoc()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeReceiptDoc()"></div>
            <div class="relative flex h-[75vh] max-h-[75vh] w-full max-w-[75vw] flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="receiptDoc">
                <div class="flex min-h-0 flex-1 flex-col">
                  <div class="flex items-center gap-2.5 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <span class="ll-chip h-8 w-8 rounded-xl shrink-0" :style="{ background: receiptDoc.r.kind === 'invoice' ? '#7066f5' : '#3fae9f' }"><x-icon name="document" class="h-4.5 w-4.5 text-white" /></span>
                    <p class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="receiptDoc.r.name || '{{ __('invoices.receipt') }}'"></p>
                    {{-- Actions in a 3-dot menu next to the title --}}
                    <div class="relative shrink-0" x-data="{ menu: false }" @keydown.escape.window="menu = false">
                      <x-icon-button name="ellipsis" tone="gray" size="sm" @click="menu = ! menu" title="{{ __('invoices.receipt_actions') }}" aria-label="{{ __('invoices.receipt_actions') }}" ::aria-expanded="menu" />
                      <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-30 mt-1 w-52 overflow-hidden rounded-xl border border-black/[0.08] dark:border-white/10 bg-white dark:bg-[#1c1c1e] py-1 shadow-xl">
                        <button type="button" @click="menu = false; openReceipt(receiptDoc.r)" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-accent/5 hover:text-accent">
                          <x-icon name="arrow-up-right" class="h-4 w-4" />{{ __('invoices.receipt_open_tab') }}
                        </button>
                        <button type="button" @click="menu = false; renameReceiptDoc()" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-accent/5 hover:text-accent">
                          <x-icon name="pencil" class="h-4 w-4" />{{ __('invoices.receipt_rename') }}
                        </button>
                        <template x-if="receiptDoc.r.kind !== 'invoice'">
                          <button type="button" @click="menu = false; reanalyzeReceipt(receiptDoc)" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-accent/5 hover:text-accent">
                            <x-icon name="arrow-path" class="h-4 w-4" />{{ __('invoices.receipt_reanalyze') }}
                          </button>
                        </template>
                        <template x-if="receiptDoc.r.blob && $store.paperless.configured">
                          <button type="button" @click="menu = false; sendReceiptToPaperless(receiptDoc)" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-accent/5 hover:text-accent">
                            <x-icon name="share" class="h-4 w-4" />{{ __('paperless.send_to_paperless') }}
                          </button>
                        </template>
                        <template x-if="! receiptDoc.r.locked">
                          <button type="button" @click="menu = false; deleteReceiptDoc()" class="flex w-full items-center gap-2.5 border-t border-black/[0.06] dark:border-white/10 px-3 py-2 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-500/10">
                            <x-icon name="trash" class="h-4 w-4" />{{ __('common.delete') }}
                          </button>
                        </template>
                      </div>
                    </div>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeReceiptDoc()" :aria-label="__('common.close')" />
                  </div>
                  {{-- Two-pane: document preview | info sidebar --}}
                  <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                    <div class="min-h-0 shrink-0 border-b border-black/[0.06] dark:border-white/10 md:w-1/2 md:border-b-0 md:border-r">
                      <div class="h-64 w-full bg-gray-50 dark:bg-[#111] md:h-full">
                        <template x-if="docPreview && docPreviewIsPdf"><iframe :src="docPreview.url" class="h-full w-full" title="preview"></iframe></template>
                        <template x-if="docPreview && docPreviewIsImage"><div class="flex h-full w-full items-center justify-center p-2"><img :src="docPreview.url" class="max-h-full max-w-full object-contain" alt="preview"></div></template>
                        <template x-if="! docPreview">
                          <div class="flex h-full w-full items-center justify-center p-4 text-center text-xs text-gray-400">
                            <span x-show="receiptDoc?.r?.blob">{{ __('invoices.assign_preview_loading') }}</span>
                            <span x-show="! receiptDoc?.r?.blob" x-cloak>{{ __('invoices.receipt_no_preview') }}</span>
                          </div>
                        </template>
                      </div>
                    </div>
                    <div class="min-h-0 flex-1 space-y-4 overflow-auto px-5 py-4 md:w-1/2">
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

                    {{-- Tax rate (VAT) — stored on the linked booking; the receipt's detected rate is shown as a hint --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_vat_label') }}</label>
                      <select x-model="receiptDoc.tx.vatCat" @change="_save()" class="w-full appearance-none rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                        <option value="">{{ __('invoices.vatcat_none') }}</option>
                        <option value="19">{{ __('invoices.vatcat_19') }}</option>
                        <option value="16">{{ __('invoices.vatcat_16') }}</option>
                        <option value="7">{{ __('invoices.vatcat_7') }}</option>
                        <option value="0">{{ __('invoices.vatcat_0') }}</option>
                        <option value="private">{{ __('invoices.vatcat_private') }}</option>
                      </select>
                      <template x-if="receiptDoc.r.vat">
                        <p class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                          <span>{{ __('invoices.receipt_vat_detected') }}</span>
                          <x-badge variant="accent"><span x-text="receiptDoc.r.vat + ' %'"></span></x-badge>
                        </p>
                      </template>
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

                    {{-- Project (bundle this receipt under a cost project) --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.receipt_project') }}</label>
                      <select @change="setReceiptProject($event.target.value)" class="w-full appearance-none rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                        <option value="" :selected="! receiptDoc.r.projectId">{{ __('invoices.project_none') }}</option>
                        <template x-for="o in projectRows" :key="o.project.id">
                          <option :value="o.project.id" :selected="receiptDoc.r.projectId === o.project.id" x-text="('— '.repeat(o.depth)) + o.project.name"></option>
                        </template>
                      </select>
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
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        {{-- ===================== PROJECTS (nestable cost bundles) ===================== --}}
        <div x-show="section === 'projects'" class="mt-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('invoices.projects_intro') }}</p>
            <x-button variant="primary" size="sm" icon="plus" @click="newProject()">{{ __('invoices.project_add') }}</x-button>
          </div>

          {{-- Business vs private split (feeds the statistics view differently) --}}
          <template x-if="projects.length">
            <div class="mt-4 grid grid-cols-2 gap-3">
              <div class="ll-card">
                <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ __('invoices.project_business_total') }}</p>
                <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(projectKindSummary.business)"></p>
              </div>
              <div class="ll-card">
                <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ __('invoices.project_private_total') }}</p>
                <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(projectKindSummary.private)"></p>
              </div>
            </div>
          </template>


          <template x-if="! projects.length">
            <x-empty-state icon="folder" class="mt-6">{{ __('invoices.project_empty') }}</x-empty-state>
          </template>

          <template x-if="projects.length">
            <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-start">
              {{-- Tree --}}
              <div class="ll-card !p-0 overflow-hidden md:w-1/3">
                <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                  <template x-for="row in pagedProjectRows" :key="row.project.id">
                    <button type="button" @click="openProjectDetail(row.project.id)"
                      class="group flex w-full items-center gap-2 py-2.5 pr-3 text-left hover:bg-accent/5"
                      :class="openProjectId === row.project.id ? 'bg-accent/10' : ''"
                      :style="{ paddingLeft: (12 + row.depth * 18) + 'px' }">
                      <x-icon name="folder" class="h-4 w-4 shrink-0 text-gray-400" />
                      <span class="min-w-0 flex-1 truncate text-sm text-gray-900 dark:text-gray-100" x-text="row.project.name"></span>
                      <template x-if="effectiveKind(row.project.id) === 'private'"><x-badge variant="gray">{{ __('invoices.project_kind_private') }}</x-badge></template>
                      <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="fmtMoney(projectTotal(row.project.id))"></span>
                    </button>
                  </template>
                </div>
                <template x-if="scopedProjectRows.length > projPerPage">
                  @include('invoices._pagination', ['page' => 'projPage', 'perPage' => 'projPerPage', 'pageCount' => 'projPageCount', 'setPerPage' => 'setProjPerPage', 'goto' => 'projGoto'])
                </template>
                <template x-if="! scopedProjectRows.length">
                  <p class="px-4 py-6 text-center text-xs text-gray-400">{{ __('invoices.project_scope_empty') }}</p>
                </template>
              </div>

              {{-- Detail --}}
              <div class="md:flex-1">
                <template x-if="! openProject">
                  <x-empty-state class="py-10">{{ __('invoices.project_pick') }}</x-empty-state>
                </template>
                <template x-if="openProject">
                  <div class="space-y-4">
                    <div class="ll-card flex items-center justify-between gap-3">
                      <div class="min-w-0">
                        <div class="flex items-center gap-2">
                          <h2 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100" x-text="openProject?.name"></h2>
                          <template x-if="effectiveKind(openProject?.id) === 'private'"><x-badge variant="gray">{{ __('invoices.project_kind_private') }}</x-badge></template>
                          <template x-if="openProject && effectiveKind(openProject.id) !== 'private'"><x-badge variant="accent">{{ __('invoices.project_kind_business') }}</x-badge></template>
                        </div>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="openProject?.note"></p>
                      </div>
                      <div class="flex shrink-0 items-center gap-3">
                        <div class="text-right">
                          <p class="text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(projectTotal(openProject?.id))"></p>
                          <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ __('invoices.project_total') }}</p>
                        </div>
                        <x-icon-button name="pencil" tone="gray" size="sm" @click="editProject(openProject)" :aria-label="__('common.edit')" />
                        <x-icon-button name="trash" tone="red" size="sm" @click="removeProject(openProject)" :aria-label="__('common.delete')" />
                      </div>
                    </div>

                    {{-- Sub-projects --}}
                    <div class="ll-card !p-0 overflow-hidden">
                      <div class="flex items-center justify-between px-4 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('invoices.project_subprojects') }}</h3>
                        <x-button variant="secondary" size="sm" icon="plus" @click="newProject(openProject.id)">{{ __('invoices.project_sub_add') }}</x-button>
                      </div>
                      <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="sp in pagedSubs" :key="sp.id">
                          <button type="button" @click="openProjectDetail(sp.id)" class="flex w-full items-center gap-2 px-4 py-2.5 text-left hover:bg-accent/5">
                            <x-icon name="folder" class="h-4 w-4 text-gray-400" />
                            <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="sp.name"></span>
                            <template x-if="effectiveKind(sp.id) === 'private'"><x-badge variant="gray">{{ __('invoices.project_kind_private') }}</x-badge></template>
                            <span class="text-xs tabular-nums text-gray-500" x-text="fmtMoney(projectTotal(sp.id))"></span>
                          </button>
                        </template>
                        <template x-if="! projectSubs(openProject?.id).length">
                          <p class="px-4 py-3 text-xs text-gray-400">{{ __('invoices.project_no_subs') }}</p>
                        </template>
                        <template x-if="projectSubs(openProject?.id).length > subPerPage">
                          @include('invoices._pagination', ['page' => 'subPage', 'perPage' => 'subPerPage', 'pageCount' => 'subPageCount', 'setPerPage' => 'setSubPerPage', 'goto' => 'subGoto'])
                        </template>
                      </div>
                    </div>

                    {{-- Manual "hand" expenses --}}
                    <div class="ll-card !p-0 overflow-hidden">
                      <div class="flex items-center justify-between px-4 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('invoices.project_expenses') }}</h3>
                        <x-button variant="secondary" size="sm" icon="plus" @click="newExpense(openProject.id)">{{ __('invoices.project_expense_add') }}</x-button>
                      </div>
                      <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="ex in pagedExpenses" :key="ex.id">
                          <div class="group flex items-center gap-3 px-4 py-2.5">
                            <div class="min-w-0 flex-1">
                              <p class="truncate text-sm text-gray-800 dark:text-gray-200" x-text="ex.note || '{{ __('invoices.project_expense') }}'"></p>
                              <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="[ex.date, expenseAccountName(ex.account)].filter(Boolean).join(' · ') || '—'"></p>
                            </div>
                            <template x-if="ex.category"><x-badge variant="gray"><span x-text="ex.category"></span></x-badge></template>
                            <span class="shrink-0 text-sm tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(ex.amount)"></span>
                            <div class="flex shrink-0 items-center gap-1 md:opacity-0 md:group-hover:opacity-100">
                              <x-icon-button name="pencil" tone="gray" size="sm" @click="editExpense(openProject, ex)" :aria-label="__('common.edit')" />
                              <x-icon-button name="trash" tone="red" size="sm" @click="removeExpense(openProject, ex)" :aria-label="__('common.delete')" />
                            </div>
                          </div>
                        </template>
                        <template x-if="! (openProject?.expenses || []).length">
                          <p class="px-4 py-3 text-xs text-gray-400">{{ __('invoices.project_no_expenses') }}</p>
                        </template>
                        <template x-if="(openProject?.expenses || []).length > expPerPage">
                          @include('invoices._pagination', ['page' => 'expPage', 'perPage' => 'expPerPage', 'pageCount' => 'expPageCount', 'setPerPage' => 'setExpPerPage', 'goto' => 'expGoto'])
                        </template>
                      </div>
                    </div>

                    {{-- Bundled receipts --}}
                    <div class="ll-card !p-0 overflow-hidden">
                      <div class="flex items-center justify-between px-4 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('invoices.project_receipts') }}</h3>
                        <x-button variant="secondary" size="sm" icon="plus" @click="openReceiptPicker()">{{ __('invoices.project_receipt_add') }}</x-button>
                      </div>
                      <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="d in pagedProjectReceipts" :key="d.r.id">
                          <button type="button" @click="openReceiptDoc(d)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-accent/5">
                            <x-icon name="document" class="h-4 w-4 text-gray-400" />
                            <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="d.r.name || '{{ __('invoices.receipt') }}'"></span>
                            <template x-if="d.r.category"><x-badge variant="gray"><span x-text="d.r.category"></span></x-badge></template>
                            <span class="text-xs tabular-nums text-gray-500" x-text="fmtMoney(d.r.total != null ? d.r.total : Math.abs(d.tx.amount || 0))"></span>
                          </button>
                        </template>
                        <template x-if="! projectReceiptList(openProject?.id).length">
                          <p class="px-4 py-3 text-xs text-gray-400">{{ __('invoices.project_no_receipts') }}</p>
                        </template>
                        <template x-if="projectReceiptList(openProject?.id).length > prcPerPage">
                          @include('invoices._pagination', ['page' => 'prcPage', 'perPage' => 'prcPerPage', 'pageCount' => 'prcPageCount', 'setPerPage' => 'setPrcPerPage', 'goto' => 'prcGoto'])
                        </template>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </template>

          {{-- Project create/edit modal --}}
          <div x-show="projectEditing" x-cloak class="fixed inset-0 z-[1140] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cancelProject()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelProject()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
              <template x-if="projectEditing">
                <div class="space-y-3">
                  <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="projectEditing?.id ? '{{ __('invoices.project_edit') }}' : '{{ __('invoices.project_add') }}'"></h3>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" x-model="projectEditing.name" @keydown.enter="saveProject()" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_kind') }}</label>
                    {{-- A root project sets its type; a sub-project always inherits the parent's. --}}
                    <template x-if="! projectEditing?.parentId">
                      <div class="inline-flex rounded-xl bg-black/[0.04] dark:bg-white/5 p-0.5">
                        <button type="button" @click="projectEditing.kind = 'business'" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="projectEditing?.kind !== 'private' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 dark:text-gray-400'">{{ __('invoices.project_kind_business') }}</button>
                        <button type="button" @click="projectEditing.kind = 'private'" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="projectEditing?.kind === 'private' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 dark:text-gray-400'">{{ __('invoices.project_kind_private') }}</button>
                      </div>
                    </template>
                    <template x-if="projectEditing?.parentId">
                      <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <x-icon name="lock-closed" class="h-3.5 w-3.5" />
                        <span x-text="'{{ __('invoices.project_kind_inherited') }}'.replace(':kind', projectKindLabel(effectiveKind(projectEditing.parentId)))"></span>
                      </p>
                    </template>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_parent') }}</label>
                    <select x-model="projectEditing.parentId" class="w-full appearance-none rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      <option value="">{{ __('invoices.project_parent_none') }}</option>
                      <template x-for="o in projectOptions(projectEditing?.id)" :key="o.project.id">
                        <option :value="o.project.id" x-text="('— '.repeat(o.depth)) + o.project.name"></option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_note') }}</label>
                    <textarea x-model="projectEditing.note" rows="2" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                  </div>
                  <div class="flex justify-end gap-2 pt-1">
                    <x-button variant="secondary" size="sm" @click="cancelProject()">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" size="sm" ::disabled="! projectEditing?.name?.trim()" @click="saveProject()">{{ __('common.save') }}</x-button>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {{-- Manual expense modal --}}
          <div x-show="expenseEditing" x-cloak class="fixed inset-0 z-[1140] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cancelExpense()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelExpense()"></div>
            <div class="relative w-full max-w-sm rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
              <template x-if="expenseEditing">
                <div class="space-y-3">
                  <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="expenseEditing?.id ? '{{ __('invoices.project_expense_edit') }}' : '{{ __('invoices.project_expense_add') }}'"></h3>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_expense_amount') }} <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" x-model.number="expenseEditing.amount" @keydown.enter="saveExpense()" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_expense_account') }}</label>
                    <select @change="expenseEditing.account = $event.target.value" class="w-full appearance-none rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      <option value="" :selected="! expenseEditing?.account">{{ __('invoices.project_expense_account_none') }}</option>
                      <template x-for="pm in sortedPayments" :key="pm.id">
                        <option :value="pm.id" :selected="expenseEditing?.account === pm.id" x-text="pm.label"></option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_expense_cat') }}</label>
                    <select @change="expenseEditing.category = $event.target.value" class="w-full appearance-none rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      <option value="" :selected="! expenseEditing?.category">{{ __('invoices.project_expense_cat_none') }}</option>
                      <template x-for="c in allCategories" :key="c">
                        <option :value="c" :selected="expenseEditing?.category === c" x-text="c"></option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_expense_date') }}</label>
                    <input type="date" x-model="expenseEditing.date" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.project_expense_note') }}</label>
                    <input type="text" x-model="expenseEditing.note" @keydown.enter="saveExpense()" placeholder="{{ __('invoices.project_expense_note_ph') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                  </div>
                  <div class="flex justify-end gap-2 pt-1">
                    <x-button variant="secondary" size="sm" @click="cancelExpense()">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" size="sm" @click="saveExpense()">{{ __('common.save') }}</x-button>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {{-- Receipt picker: bundle existing receipts into the open project --}}
          <div x-show="receiptPicker" x-cloak class="fixed inset-0 z-[1140] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeReceiptPicker()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeReceiptPicker()"></div>
            <div class="relative flex h-[75vh] max-h-[75vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <div class="flex items-center gap-2.5 border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                <p class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.project_pick_title') }}</p>
                <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeReceiptPicker()" :aria-label="__('common.close')" />
              </div>
              <div class="border-b border-black/[0.06] dark:border-white/10 p-2">
                <input type="search" x-model.debounce.200ms="receiptPickerQuery" placeholder="{{ __('invoices.project_pick_search') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
              </div>
              <div class="min-h-0 flex-1 overflow-auto px-2 py-2">
                <template x-for="d in pickerReceipts()" :key="d.r.id">
                  <button type="button" @click="toggleReceiptToProject(d)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left hover:bg-accent/5">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border" :class="d.r.projectId === openProjectId ? 'border-accent bg-accent text-white' : 'border-gray-300 dark:border-gray-600'">
                      <template x-if="d.r.projectId === openProjectId"><x-icon name="check" class="h-3.5 w-3.5" /></template>
                    </span>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm text-gray-800 dark:text-gray-200" x-text="d.r.name || '{{ __('invoices.receipt') }}'"></p>
                      <p class="truncate text-xs text-gray-500 dark:text-gray-400"><span x-text="d.tx.date"></span> · <span x-text="d.tx.counterparty || d.tx.purpose || '—'"></span></p>
                    </div>
                    <template x-if="d.r.projectId && d.r.projectId !== openProjectId">
                      <x-badge variant="warning"><span x-text="projectName(d.r.projectId)"></span></x-badge>
                    </template>
                    <span class="shrink-0 text-xs tabular-nums text-gray-500" x-text="fmtMoney(d.r.total != null ? d.r.total : Math.abs(d.tx.amount || 0))"></span>
                  </button>
                </template>
                <template x-if="! pickerReceipts().length">
                  <p class="px-3 py-6 text-center text-xs text-gray-400">{{ __('invoices.project_pick_none') }}</p>
                </template>
              </div>
              <div class="flex justify-end border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                <x-button variant="primary" size="sm" @click="closeReceiptPicker()">{{ __('invoices.project_pick_done') }}</x-button>
              </div>
            </div>
          </div>
        </div>

        {{-- ===================== BUSINESS PARTNERS ===================== --}}
        <div x-show="section === 'partners'" class="mt-6">
          {{-- LIST / TABLE --}}
          <div x-show="partnersView === 'list'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="relative max-w-xs flex-1">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" x-model="partnerSearch" @input="parPage = 1" placeholder="{{ __('invoices.partners_search') }}" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] pl-9 text-sm focus:border-accent focus:ring-accent">
              </div>
              <x-button variant="primary" icon="plus" @click="newPartner()">{{ __('invoices.partner_add') }}</x-button>
            </div>

            <template x-if="! filteredPartners.length">
              <x-empty-state icon="user" class="py-16">{{ __('invoices.partners_empty') }}</x-empty-state>
            </template>

            <template x-if="filteredPartners.length">
              <div class="ll-card !p-0 overflow-hidden">
                <div class="overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead class="border-b border-black/[0.06] dark:border-white/10 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                      <tr>
                        <th class="px-4 py-3">{{ __('invoices.partner_name') }}</th>
                        <th class="px-4 py-3 hidden md:table-cell">{{ __('invoices.partner_contact_person') }}</th>
                        <th class="px-4 py-3 hidden lg:table-cell">{{ __('invoices.partner_email') }}</th>
                        <th class="px-4 py-3 hidden lg:table-cell">{{ __('invoices.partner_phone') }}</th>
                        <th class="px-4 py-3 hidden md:table-cell">{{ __('invoices.partner_vat') }}</th>
                        <th class="px-4 py-3 hidden xl:table-cell">{{ __('invoices.receipt_category') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('invoices.partner_links') }}</th>
                        <th class="px-4 py-3 w-8"></th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                      <template x-for="p in pagedPartners" :key="p.id">
                        <tr class="group cursor-pointer transition-colors hover:bg-accent/5" @click="openPartner(p)">
                          <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                              <template x-if="partnerLogoSrc(p)"><img :src="partnerLogoSrc(p)" alt="" class="h-8 w-8 shrink-0 rounded-lg object-contain bg-white border border-black/[0.06] dark:border-white/10"></template>
                              <template x-if="! partnerLogoSrc(p)"><span class="ll-chip h-8 w-8 shrink-0" style="--chip: #6b7280"><x-icon name="user" class="h-4 w-4" /></span></template>
                              <span class="font-medium text-gray-900 dark:text-gray-100" x-text="p.name"></span>
                            </div>
                          </td>
                          <td class="px-4 py-3 hidden md:table-cell text-gray-600 dark:text-gray-300" x-text="(p.contacts && p.contacts[0] && p.contacts[0].name) || '—'"></td>
                          <td class="px-4 py-3 hidden lg:table-cell text-gray-600 dark:text-gray-300" x-text="p.email || '—'"></td>
                          <td class="px-4 py-3 hidden lg:table-cell text-gray-600 dark:text-gray-300 tabular-nums" x-text="p.phone || '—'"></td>
                          <td class="px-4 py-3 hidden md:table-cell text-gray-600 dark:text-gray-300 tabular-nums" x-text="p.vatId || '—'"></td>
                          <td class="px-4 py-3 hidden xl:table-cell"><template x-if="p.category"><x-badge variant="gray"><span x-text="p.category"></span></x-badge></template></td>
                          <td class="px-4 py-3 text-right tabular-nums text-gray-500 dark:text-gray-400" x-text="partnerLinkCount(p.id)"></td>
                          <td class="px-4 py-3 text-right"><x-icon name="chevron-right" class="h-4 w-4 text-gray-300 dark:text-gray-600" /></td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
                <template x-if="filteredPartners.length > parPerPage">
                  @include('invoices._pagination', ['page' => 'parPage', 'perPage' => 'parPerPage', 'pageCount' => 'parPageCount', 'setPerPage' => 'setParPerPage', 'goto' => 'parGoto'])
                </template>
              </div>
            </template>
          </div>

          {{-- DETAIL (info + linked invoices/receipts; read-only until "Bearbeiten") --}}
          <template x-if="partnersView === 'detail' && openPartnerRec">
            <div>
              <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <x-icon-button name="arrow-left" @click="backToPartners()" :aria-label="__('common.back')" />
                  <template x-if="partnerLogoSrc(openPartnerRec)"><img :src="partnerLogoSrc(openPartnerRec)" alt="" class="h-10 w-10 shrink-0 rounded-xl object-contain bg-white border border-black/[0.06] dark:border-white/10"></template>
                  <template x-if="! partnerLogoSrc(openPartnerRec)"><span class="ll-chip h-10 w-10 shrink-0" style="--chip: #6b7280"><x-icon name="user" class="h-5 w-5" /></span></template>
                  <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="openPartnerRec.name"></h1>
                </div>
                <div class="flex items-center gap-2">
                  <x-button variant="secondary" size="sm" icon="pencil" @click="editPartner(openPartnerRec)">{{ __('common.edit') }}</x-button>
                  <x-icon-button name="trash" tone="red" size="sm" @click="deleteOpenPartner()" :aria-label="__('common.delete')" />
                </div>
              </div>

              <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Info --}}
                <div class="ll-card lg:col-span-1">
                  <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.partner_info') }}</h2>
                  <dl class="mt-3 space-y-2 text-sm">
                    <div x-show="openPartnerRec.vatId"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.partner_vat') }}</dt><dd class="tabular-nums text-gray-800 dark:text-gray-200" x-text="openPartnerRec.vatId"></dd></div>
                    <div x-show="openPartnerRec.email"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.partner_email') }}</dt><dd><a :href="'mailto:'+openPartnerRec.email" class="text-accent hover:underline" x-text="openPartnerRec.email"></a></dd></div>
                    <div x-show="openPartnerRec.phone"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.partner_phone') }}</dt><dd class="tabular-nums text-gray-800 dark:text-gray-200" x-text="openPartnerRec.phone"></dd></div>
                    <div x-show="openPartnerRec.url"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.partner_url') }}</dt><dd><a :href="openPartnerRec.url" target="_blank" rel="noopener" class="text-accent hover:underline break-all" x-text="openPartnerRec.url"></a></dd></div>
                    <div x-show="openPartnerRec.address"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.partner_address') }}</dt><dd class="whitespace-pre-line text-gray-800 dark:text-gray-200" x-text="openPartnerRec.address"></dd></div>
                    <div x-show="openPartnerRec.category"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.receipt_category') }}</dt><dd class="text-gray-800 dark:text-gray-200" x-text="openPartnerRec.category"></dd></div>
                    <div x-show="openPartnerRec.note"><dt class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.receipt_note') }}</dt><dd class="whitespace-pre-line text-gray-800 dark:text-gray-200" x-text="openPartnerRec.note"></dd></div>
                  </dl>
                  {{-- Contact persons --}}
                  <template x-if="openPartnerRec.contacts && openPartnerRec.contacts.length">
                    <div class="mt-4 border-t border-black/[0.06] dark:border-white/10 pt-3">
                      <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.partner_contacts') }}</h3>
                      <ul class="mt-2 space-y-2">
                        <template x-for="c in openPartnerRec.contacts" :key="c.id">
                          <li class="text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100"><span x-text="c.name"></span><span x-show="c.role" class="ml-1 text-xs font-normal text-gray-400" x-text="'· ' + c.role"></span></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400" x-text="[c.email, c.phone].filter(Boolean).join(' · ')"></div>
                          </li>
                        </template>
                      </ul>
                    </div>
                  </template>
                </div>

                {{-- Linked invoices + receipts --}}
                <div class="space-y-6 lg:col-span-2">
                  <div class="ll-card !p-0 overflow-hidden">
                    <h2 class="border-b border-black/[0.06] dark:border-white/10 px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.partner_linked_invoices') }} <span class="text-gray-400" x-text="'(' + invoicesForPartner(openPartnerRec.id).length + ')'"></span></h2>
                    <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                      <template x-for="inv in invoicesForPartner(openPartnerRec.id)" :key="inv.id">
                        <button type="button" @click="openInvoiceById(inv.id)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-accent/5">
                          <span class="ll-chip h-8 w-8 shrink-0" style="--chip: #7066f5"><x-icon name="document-text" class="h-4 w-4" /></span>
                          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100 tabular-nums" x-text="inv.number || '—'"></span><span class="block text-xs text-gray-500 dark:text-gray-400 tabular-nums" x-text="inv.issueDate || ''"></span></span>
                          <span class="shrink-0 text-sm tabular-nums text-gray-700 dark:text-gray-300" x-text="fmtMoney(computeTotals(inv).gross, inv.currency, 'de')"></span>
                        </button>
                      </template>
                      <template x-if="! invoicesForPartner(openPartnerRec.id).length"><p class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">—</p></template>
                    </div>
                  </div>
                  <div class="ll-card !p-0 overflow-hidden">
                    <h2 class="border-b border-black/[0.06] dark:border-white/10 px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.partner_linked_receipts') }} <span class="text-gray-400" x-text="'(' + receiptsForPartner(openPartnerRec.id).length + ')'"></span></h2>
                    <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                      <template x-for="d in receiptsForPartner(openPartnerRec.id)" :key="d.r.id">
                        <button type="button" @click="openReceiptDoc(d)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-accent/5">
                          <span class="ll-chip h-8 w-8 shrink-0" style="--chip: #e2915a"><x-icon name="paper-clip" class="h-4 w-4" /></span>
                          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="d.r.name || d.r.merchant || '{{ __('invoices.receipt') }}'"></span><span class="block text-xs text-gray-500 dark:text-gray-400 tabular-nums" x-text="d.tx.date || ''"></span></span>
                          <span class="shrink-0 text-sm tabular-nums text-gray-700 dark:text-gray-300" x-text="fmtMoney(d.tx.amount, 'EUR', 'de')"></span>
                        </button>
                      </template>
                      <template x-if="! receiptsForPartner(openPartnerRec.id).length"><p class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">—</p></template>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          {{-- Partner editor modal (create + edit; multiple contact persons) --}}
          <div x-show="partnerEditing" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cancelPartner()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelPartner()"></div>
            <div class="relative flex max-h-[88vh] w-full max-w-md flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="partnerEditing">
                <div class="flex min-h-0 flex-col">
                  <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="partnerEditing.id ? '{{ __('common.edit') }}' : '{{ __('invoices.partner_add') }}'"></h3>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="cancelPartner()" :aria-label="__('common.close')" />
                  </div>
                  <div class="min-h-0 flex-1 space-y-3 overflow-auto px-5 py-4">
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_name') }} <span class="text-red-500">*</span></label>
                      <input type="text" x-model="partnerEditing.name" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_url') }}</label>
                      <div class="flex items-center gap-2">
                        <template x-if="partnerLogoSrc(partnerEditing)"><img :src="partnerLogoSrc(partnerEditing)" alt="" class="h-8 w-8 shrink-0 rounded-lg object-contain bg-white"></template>
                        <input type="url" x-model="partnerEditing.url" placeholder="https://…" class="min-w-0 flex-1 rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                      <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('invoices.partner_url_hint') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_email') }}</label>
                        <input type="email" x-model="partnerEditing.email" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_phone') }}</label>
                        <input type="tel" x-model="partnerEditing.phone" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_vat') }}</label>
                      <input type="text" x-model="partnerEditing.vatId" placeholder="DE…" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm tabular-nums">
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_address') }}</label>
                      <textarea x-model="partnerEditing.address" rows="2" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                    </div>
                    {{-- Contact persons (multiple) --}}
                    <div>
                      <div class="mb-1 flex items-center justify-between">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_contacts') }}</label>
                        <button type="button" @click="addPartnerContact(partnerEditing)" class="inline-flex items-center gap-1 text-xs font-medium text-accent hover:underline"><x-icon name="plus" class="h-3.5 w-3.5" />{{ __('invoices.partner_contact_add') }}</button>
                      </div>
                      <div class="space-y-2">
                        <template x-for="(c, ci) in (partnerEditing.contacts || [])" :key="c.id">
                          <div class="rounded-xl border border-black/[0.06] dark:border-white/10 p-2">
                            <div class="flex items-center gap-2">
                              <input type="text" x-model="c.name" placeholder="{{ __('invoices.partner_contact_person') }}" class="min-w-0 flex-1 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                              <input type="text" x-model="c.role" placeholder="{{ __('invoices.partner_contact_role') }}" class="min-w-0 w-28 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                              <x-icon-button name="trash" tone="red" size="sm" @click="removePartnerContact(partnerEditing, ci)" :aria-label="__('common.delete')" />
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                              <input type="email" x-model="c.email" placeholder="{{ __('invoices.partner_email') }}" class="rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                              <input type="tel" x-model="c.phone" placeholder="{{ __('invoices.partner_phone') }}" class="rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                            </div>
                          </div>
                        </template>
                      </div>
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

        {{-- ===================== STATISTICS ===================== --}}
        <div x-show="section === 'stats'" class="mt-6">
          <template x-if="! statsKpis.count && statsYear === {{ (int) date('Y') }} && ! projects.length">
            <x-empty-state icon="chart-bar">{{ __('invoices.stats_empty') }}</x-empty-state>
          </template>
          <template x-if="statsKpis.count || statsYear !== {{ (int) date('Y') }} || projects.length">
            <div>
              {{-- Project costs, clearly split business vs private (scope-aware) --}}
              <template x-if="projects.length">
                <div>
                  <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_project_costs') }}</h2>
                  <div class="grid grid-cols-2 gap-4">
                    <div class="ll-card" x-show="financeScope !== 'private'">
                      <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_project_business') }}</p>
                      <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(projectKindSummary.business)"></p>
                    </div>
                    <div class="ll-card" x-show="financeScope !== 'business'">
                      <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_project_private') }}</p>
                      <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(projectKindSummary.private)"></p>
                    </div>
                  </div>
                </div>
              </template>

              {{-- Business revenue (invoices) — hidden in the private scope --}}
              <div x-show="financeScope !== 'private'" :class="projects.length ? 'mt-6' : ''">
              <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.stat_revenue_section') }}</h2>
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
              </div>{{-- /business revenue --}}
            </div>
          </template>
        </div>

        {{-- ===================== SETTINGS (partners + categories) — iOS grouped lists ===================== --}}
        <div x-show="section === 'settings'" class="mt-6 mx-auto max-w-2xl">
          {{-- Categories --}}
          <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('invoices.cats_title') }}</h2>
          <p class="mb-2 px-1 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.cats_intro') }}</p>
          <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            {{-- Built-in default categories (not removable) — shown in full, no pagination --}}
            <template x-for="c in sortedCatSuggestions" :key="'def-'+c">
              <div class="flex items-center gap-3 px-4 py-2.5">
                <span class="ll-chip h-8 w-8 rounded-lg shrink-0" style="--chip: #e2915a"><x-icon name="hashtag" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="c"></span>
                <x-badge variant="gray">{{ __('invoices.cats_default') }}</x-badge>
              </div>
            </template>
            {{-- Custom categories (removable) --}}
            <template x-for="c in pagedCategories" :key="c.name">
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
            <template x-if="(financeCategories || []).length > catPerPage">
              @include('invoices._pagination', ['page' => 'catPage', 'perPage' => 'catPerPage', 'pageCount' => 'catPageCount', 'setPerPage' => 'setCatPerPage', 'goto' => 'catGoto'])
            </template>
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
          <template x-if="! scopedPayments.length">
            <x-empty-state icon="wallet">{{ __('invoices.pay_empty') }}</x-empty-state>
          </template>

          {{-- List (iOS grouped). Bank accounts open a statement/transaction view. --}}
          <template x-if="scopedPayments.length">
            <div class="ll-card !p-0 mt-4 overflow-hidden">
              <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="pm in scopedPayments" :key="pm.id">
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
                      <select @change="setTxYear(parseInt($event.target.value, 10))" class="appearance-none rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] px-2 py-1.5 text-sm" aria-label="{{ __('invoices.stats_year') }}">
                        <template x-for="y in accountTxYears" :key="y"><option :value="y" :selected="txYear === y" x-text="y"></option></template>
                      </select>
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
                    <p x-show="accountPrivateNoEg" class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400" x-text="'{{ __('invoices.eg_missing_count') }}'.replace(':n', accountPrivateNoEg)"></p>
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
                              <template x-if="privatLabel(tx)">
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                  <span class="inline-flex items-center rounded-full bg-violet-500/15 px-2 py-0.5 text-[11px] font-medium text-violet-600 dark:text-violet-300" x-text="privatLabel(tx)"></span>
                                  <template x-if="needsEigenbeleg(tx)">
                                    <button type="button" @click="newEigenbeleg(tx)" class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-950/50 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-200 dark:hover:bg-amber-900/60">
                                      <x-icon name="pencil" class="h-3 w-3" /><span x-text="'{{ __('invoices.eg_needed') }}'"></span>
                                    </button>
                                  </template>
                                  <template x-if="hasEigenbeleg(tx)">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-500/15 px-2 py-0.5 text-[11px] font-medium text-green-600 dark:text-green-400"><x-icon name="check" class="h-3 w-3" />{{ __('invoices.eg_present') }}</span>
                                  </template>
                                </div>
                              </template>
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
                    @include('invoices._pagination', ['page' => 'txPage', 'perPage' => 'txPerPage', 'pageCount' => 'txPageCount', 'setPerPage' => 'setTxPerPage', 'goto' => 'txGoto'])
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
                      <x-button variant="secondary" size="sm" icon="pencil" @click="newEigenbeleg(receiptTx)">{{ __('invoices.eg_create') }}</x-button>
                      <input type="file" x-ref="receiptFile" accept="application/pdf,image/*" multiple class="hidden" @change="uploadReceipts($event.target.files); $event.target.value = ''">
                      <x-button variant="primary" size="sm" icon="arrow-up-tray" ::disabled="receiptBusy" @click="$refs.receiptFile.click()">{{ __('invoices.receipts_add') }}</x-button>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {{-- ---- EIGENBELEG (self-receipt: prefilled from the booking → PDF) ---- --}}
          <div x-show="eigenbeleg" x-cloak class="fixed inset-0 z-[1130] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="! egBusy && cancelEigenbeleg()">
            <div class="absolute inset-0 bg-gray-900/50" @click="! egBusy && cancelEigenbeleg()"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <template x-if="eigenbeleg">
                <div class="flex min-h-0 flex-col">
                  <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.eg_title') }}</h3>
                    <x-icon-button name="x-mark" tone="gray" size="sm" @click="cancelEigenbeleg()" :aria-label="__('common.close')" />
                  </div>
                  <div class="min-h-0 flex-1 space-y-3 overflow-auto px-5 py-4">
                    <x-alert variant="info">{{ __('invoices.eg_intro') }}</x-alert>
                    {{-- Beleggrund (type) --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_grund') }}</label>
                      <select x-model="eigenbeleg.grund" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                        <template x-for="g in egGrundOptions" :key="g"><option :value="g" x-text="egGrundLabel(g)"></option></template>
                      </select>
                    </div>
                    <div x-show="eigenbeleg.grund === 'sonstiges'">
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_grund_other') }}</label>
                      <input type="text" x-model="eigenbeleg.grundOther" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                    </div>
                    {{-- Betrag + Buchungstext (always) --}}
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_gross') }} <span class="text-red-500">*</span></label>
                      <input type="number" step="0.01" x-model.number="eigenbeleg.gross" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm text-right tabular-nums">
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_buchungstext') }}</label>
                      <textarea x-model="eigenbeleg.buchungstext" rows="2" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                    </div>
                    {{-- Lost-receipt business expense: strict Pflichtangaben (payee/VAT/reason) --}}
                    <template x-if="egIsExpense">
                      <div class="space-y-3 rounded-xl border border-black/[0.06] dark:border-white/10 p-3">
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_recipient') }} <span class="text-red-500">*</span></label>
                          <input type="text" x-model="eigenbeleg.recipient" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_address') }}</label>
                          <textarea x-model="eigenbeleg.address" rows="2" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                        </div>
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_vat_rate') }}</label>
                          <select x-model.number="eigenbeleg.vatRate" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                            <template x-for="r in egVatChoices()" :key="r"><option :value="r" x-text="r + ' %'"></option></template>
                          </select>
                        </div>
                        <dl class="rounded-xl bg-gray-50 dark:bg-[#2c2c2e]/60 px-3 py-2 text-sm">
                          <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ __('invoices.net') }}</dt><dd class="tabular-nums text-gray-700 dark:text-gray-200" x-text="fmtMoney(egNet, 'EUR', 'de')"></dd></div>
                          <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400" x-text="'{{ __('invoices.vat_at') }}'.replace(':rate', eigenbeleg.vatRate)"></dt><dd class="tabular-nums text-gray-700 dark:text-gray-200" x-text="fmtMoney(egVat, 'EUR', 'de')"></dd></div>
                        </dl>
                        <div>
                          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_reason') }} <span class="text-red-500">*</span></label>
                          <textarea x-model="eigenbeleg.reason" rows="2" placeholder="z. B. Originalquittung verloren" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm"></textarea>
                        </div>
                      </div>
                    </template>
                    {{-- Ort + dates + issuer --}}
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_ort') }}</label>
                        <input type="text" x-model="eigenbeleg.ort" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_date') }}</label>
                        <input type="date" x-model="eigenbeleg.date" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_created') }}</label>
                        <input type="date" x-model="eigenbeleg.createdAt" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_issuer') }}</label>
                        <input type="text" x-model="eigenbeleg.issuer" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm">
                      </div>
                    </div>
                    {{-- Signature pad (finger/trackpad); embedded into the sealed PDF --}}
                    <div>
                      <div class="mb-1 flex items-center justify-between">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.eg_signature') }}</label>
                        <button type="button" @click="egSigClear()" class="text-xs font-medium text-accent hover:underline">{{ __('invoices.eg_sig_clear') }}</button>
                      </div>
                      <canvas x-ref="egCanvas" x-init="$nextTick(() => egSigInit())"
                        class="h-32 w-full touch-none rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-white"
                        @pointerdown.prevent="egSigStart($event)" @pointermove.prevent="egSigMove($event)" @pointerup.prevent="egSigEnd()" @pointerleave="egSigEnd()"></canvas>
                      <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.eg_sig_hint') }}</p>
                    </div>
                  </div>
                  <div class="flex items-center justify-end gap-3 border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                    <x-button variant="secondary" @click="cancelEigenbeleg()" ::disabled="egBusy">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" icon="document-text" @click="saveEigenbeleg()" ::disabled="egBusy">
                      <span x-show="! egBusy">{{ __('invoices.eg_generate') }}</span>
                      <span x-show="egBusy">{{ __('invoices.saving') }}</span>
                    </x-button>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {{-- Off-screen print node for the Eigenbeleg PDF (rasterised by html2canvas).
               Teleported to <body> so no ancestor display:none/overflow can blank it. --}}
          <template x-teleport="body">
          <div id="eigenbeleg-print" style="position:fixed; left:-10000px; top:0; width:794px; background:#fff; color:#111;">
            <template x-if="eigenbeleg">
              <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding:60px 64px; font-size:13px; line-height:1.6; color:#111;">
                <div style="text-align:center; font-size:22px; font-weight:700; margin-bottom:36px;">{{ __('invoices.eg_title') }}</div>

                {{-- Beleggrund (checkbox list, selected one ticked) --}}
                <div style="font-size:16px; font-weight:700; margin-bottom:6px;">{{ __('invoices.eg_grund') }}</div>
                <div style="height:2px; background:#111; margin-bottom:18px;"></div>
                <table style="width:100%; border-collapse:collapse; margin-bottom:34px;">
                  <tbody>
                    <template x-for="(g, gi) in egGrundOptions" :key="g">
                      <tr x-show="gi % 2 === 0">
                        <td style="width:50%; padding:6px 0; vertical-align:top;">
                          <span x-text="(eigenbeleg.grund === g ? '☒ ' : '☐ ') + egGrundLabel(g)"></span>
                          <span x-show="g === 'sonstiges' && eigenbeleg.grund === 'sonstiges' && eigenbeleg.grundOther" x-text="' — ' + eigenbeleg.grundOther"></span>
                        </td>
                        <td style="width:50%; padding:6px 0; vertical-align:top;">
                          <template x-if="egGrundOptions[gi + 1]">
                            <span>
                              <span x-text="(eigenbeleg.grund === egGrundOptions[gi + 1] ? '☒ ' : '☐ ') + egGrundLabel(egGrundOptions[gi + 1])"></span>
                              <span x-show="egGrundOptions[gi + 1] === 'sonstiges' && eigenbeleg.grund === 'sonstiges' && eigenbeleg.grundOther" x-text="' — ' + eigenbeleg.grundOther"></span>
                            </span>
                          </template>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>

                {{-- Belegdaten --}}
                <div style="font-size:16px; font-weight:700; margin-bottom:6px;">{{ __('invoices.receipts_title') }}</div>
                <div style="height:2px; background:#111; margin-bottom:18px;"></div>
                <table style="width:100%; border-collapse:collapse;">
                  <tbody>
                    <tr><td style="padding:8px 0; width:40%; vertical-align:top;">{{ __('invoices.eg_gross') }}</td><td style="padding:8px 0; font-weight:700;" x-text="fmtMoney(eigenbeleg.gross, 'EUR', 'de')"></td></tr>
                    <tr><td style="padding:8px 0; vertical-align:top;">{{ __('invoices.eg_buchungstext') }}</td><td style="padding:8px 0; white-space:pre-line;" x-text="eigenbeleg.buchungstext || '—'"></td></tr>
                    <template x-if="egIsExpense">
                      <tr><td style="padding:8px 0; vertical-align:top;">{{ __('invoices.eg_recipient') }}</td><td style="padding:8px 0; white-space:pre-line;" x-text="[eigenbeleg.recipient, eigenbeleg.address].filter(Boolean).join('\n') || '—'"></td></tr>
                    </template>
                    <template x-if="egIsExpense">
                      <tr><td style="padding:8px 0; vertical-align:top;">{{ __('invoices.net') }} / <span x-text="'{{ __('invoices.vat_at') }}'.replace(':rate', eigenbeleg.vatRate)"></span></td><td style="padding:8px 0;" x-text="fmtMoney(egNet, 'EUR', 'de') + ' / ' + fmtMoney(egVat, 'EUR', 'de')"></td></tr>
                    </template>
                    <template x-if="egIsExpense">
                      <tr><td style="padding:8px 0; vertical-align:top;">{{ __('invoices.eg_reason') }}</td><td style="padding:8px 0; white-space:pre-line;" x-text="eigenbeleg.reason || '—'"></td></tr>
                    </template>
                  </tbody>
                </table>
                <div x-show="egIsExpense" style="margin-top:12px; font-size:11px; color:#777;">{{ __('invoices.eg_novat_note') }}</div>

                {{-- Ort/Datum + signature --}}
                <div style="margin-top:64px; display:flex; justify-content:space-between; align-items:flex-end; gap:60px;">
                  <div style="flex:1;">
                    <div style="border-top:1px solid #111; padding-top:6px; font-size:12px;" x-text="[eigenbeleg.ort, fmtDate(eigenbeleg.date)].filter(Boolean).join(', ')"></div>
                    <div style="margin-top:4px; font-size:10px; color:#777;">{{ __('invoices.eg_ort') }}, {{ __('invoices.eg_date') }}</div>
                  </div>
                  <div style="flex:1;">
                    <img x-show="eigenbeleg.signature" :src="eigenbeleg.signature" style="height:52px; display:block; margin-bottom:-6px;" alt="">
                    <div style="border-top:1px solid #111; padding-top:6px; font-size:12px;" x-text="eigenbeleg.issuer || ''"></div>
                    <div style="margin-top:4px; font-size:10px; color:#777;">{{ __('invoices.eg_signature') }}</div>
                  </div>
                </div>
              </div>
            </template>
          </div>
          </template>

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
        <div x-show="view === 'list'" class="relative" x-data="{ drag: false }"
             @dragover.prevent="drag = true" @dragenter.prevent="drag = true"
             @dragleave.prevent="if ($event.target === $el) drag = false"
             @drop.prevent="drag = false; if ($event.dataTransfer?.files?.length) importPdfs($event.dataTransfer.files)">
          {{-- Drop invoice PDFs anywhere on the list to import them (same as the button) --}}
          <div x-show="drag" x-cloak class="pointer-events-none absolute inset-0 z-40 flex items-center justify-center rounded-2xl border-2 border-dashed border-accent bg-accent/10">
            <span class="rounded-xl bg-white/90 px-4 py-2 text-sm font-medium text-accent shadow dark:bg-[#1c1c1e]/90">{{ __('invoices.import_drop_hint') }}</span>
          </div>
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
            <input type="search" x-model.debounce.250ms="query" @input="invPage = 1" placeholder="{{ __('invoices.search') }}" class="w-full max-w-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
            <select x-model="filterStatus" @change="invPage = 1" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent">
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
                <template x-for="inv in pagedInvoices" :key="inv.id">
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
                        <x-action-menu :aria-label="__('invoices.col_actions')">
                          <x-action-menu-item icon="pencil" @click="open(inv)">{{ __('common.edit') }}</x-action-menu-item>
                          <x-action-menu-item icon="printer" @click="printInvoice(inv)">{{ __('invoices.print') }}</x-action-menu-item>
                          <x-action-menu-item icon="trash" danger @click="trash(inv)">{{ __('invoices.trash') }}</x-action-menu-item>
                        </x-action-menu>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
            @include('invoices._pagination', ['page' => 'invPage', 'perPage' => 'invPerPage', 'pageCount' => 'invPageCount', 'setPerPage' => 'setInvPerPage', 'goto' => 'invGoto'])
          </div>
        </div>

        {{-- ============= IMPORTED INVOICE (inline PDF + key-field bar) ============= --}}
        <template x-if="view === 'imported' && current">
        <div x-cloak class="flex min-h-[calc(100vh-9rem)] flex-col">
          {{-- Header — imported invoices are an immutable record (the original PDF); no edit mode. --}}
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <x-icon-button name="arrow-left" @click="backToList()" aria-label="{{ __('common.back') }}" />
              <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums" x-text="current?.number || @js(__('invoices.status_draft'))"></h1>
              <span class="inline-flex items-center rounded-full bg-green-500/15 px-2 py-0.5 text-xs font-medium text-green-600 dark:text-green-400" x-text="statusLabel(current?.status)"></span>
            </div>
            <x-action-menu :aria-label="__('invoices.col_actions')">
              <x-action-menu-item icon="arrow-down-tray" @click="downloadZugferd(current)" title="{{ __('invoices.zugferd_hint') }}">{{ __('invoices.zugferd') }}</x-action-menu-item>
              <x-action-menu-item icon="trash" danger @click="trash(current)">{{ __('common.delete') }}</x-action-menu-item>
            </x-action-menu>
          </div>

          {{-- Key-field bar (read-only). Recipient links to the business partner. --}}
          <div class="ll-card mt-4 grid grid-cols-2 gap-x-5 gap-y-3 md:grid-cols-6">
            <div class="col-span-2">
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_recipient') }}</p>
              <template x-if="current.customer?.partnerId">
                <button type="button" @click="goToPartner(current)" class="mt-1 text-left text-sm font-medium text-accent hover:underline" x-text="current.customer?.name || '—'"></button>
              </template>
              <template x-if="! current.customer?.partnerId">
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100" x-text="current.customer?.name || '—'"></p>
              </template>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.col_number') }}</p>
              <p class="mt-1 text-sm tabular-nums text-gray-900 dark:text-gray-100" x-text="current.number || '—'"></p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.issue_date') }}</p>
              <p class="mt-1 text-sm tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtDate(current.issueDate) || '—'"></p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_gross') }}</p>
              <p class="mt-1 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="fmtMoney(impGross, current.currency, 'de')"></p>
            </div>
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_vat_rate') }}</p>
              <p class="mt-1 text-sm tabular-nums text-gray-900 dark:text-gray-100" x-text="impRate + ' %'"></p>
            </div>
          </div>

          {{-- Inline original PDF (authoritative record) — rendered client-side to full-width
               page images; fills the field and scrolls, no browser PDF chrome. --}}
          <div class="mt-4 min-h-[60vh] flex-1 overflow-auto rounded-2xl border border-black/[0.06] dark:border-white/10 bg-gray-100 dark:bg-[#111] p-3 sm:p-4">
            <template x-if="invoicePdf?.pages?.length">
              <div class="mx-auto flex max-w-4xl flex-col gap-4">
                <template x-for="(pg, i) in invoicePdf.pages" :key="i">
                  <img :src="pg" class="w-full rounded-lg bg-white shadow" alt="">
                </template>
              </div>
            </template>
            <template x-if="! invoicePdf?.pages?.length"><div class="flex h-full min-h-[50vh] items-center justify-center text-sm text-gray-400 dark:text-gray-500"><x-icon name="arrow-path" class="mr-2 h-4 w-4 animate-spin" />{{ __('invoices.import_title') }}</div></template>
          </div>
        </div>
        </template>

        {{-- ===================== EDITOR ===================== --}}
        <template x-if="view === 'edit' && current">
        <div x-cloak @input="onFieldInput()">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <x-icon-button name="arrow-left" @click="backToList()" aria-label="{{ __('common.back') }}" />
              <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums" x-text="current?.number || @js(__('invoices.status_draft'))"></h1>
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="{ 'bg-green-500/15 text-green-600 dark:text-green-400': current?.status === 'paid', 'bg-accent/15 text-accent': current?.status === 'sent', 'bg-gray-500/15 text-gray-500 dark:text-gray-400': current?.status === 'draft' }"
                x-text="statusLabel(current?.status)"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              {{-- Locked invoice with pending edits → explicit versioned save (reason required). --}}
              <x-button variant="primary" size="sm" icon="check" x-show="isLocked(current) && dirty" ::disabled="pdfBusy" @click="saveVersionedEdit()">
                <span x-show="! pdfBusy">{{ __('invoices.save_changes') }}</span>
                <span x-show="pdfBusy">{{ __('invoices.saving') }}</span>
              </x-button>
              <x-action-menu :aria-label="__('invoices.col_actions')">
                <x-action-menu-item icon="pencil" x-show="isLocked(current) && ! editUnlocked" @click="requestEdit()">{{ __('invoices.edit') }}</x-action-menu-item>
                <x-action-menu-item icon="document-text" x-show="current?.imported && current?.pdf" @click="openOriginalPdf(current)">{{ __('invoices.open_original') }}</x-action-menu-item>
                <x-action-menu-item icon="printer" x-show="! current?.imported || ! current?.pdf" @click="printInvoice(current)">{{ __('invoices.print') }}</x-action-menu-item>
                <x-action-menu-item icon="arrow-down-tray" @click="downloadZugferd(current)" title="{{ __('invoices.zugferd_hint') }}">{{ __('invoices.zugferd') }}</x-action-menu-item>
                <x-action-menu-item icon="check" x-show="! current?.imported && current?.status === 'draft'" @click="finalize(current)">{{ __('invoices.finalize') }}</x-action-menu-item>
                <x-action-menu-item icon="check-circle" x-show="! current?.imported && current?.status === 'sent'" @click="markPaid(current)">{{ __('invoices.mark_paid') }}</x-action-menu-item>
              </x-action-menu>
            </div>
          </div>

          {{-- Locked invoices (imported / finalized) stay editable, but every save is a
               versioned correction with a mandatory reason (GoBD). --}}
          <template x-if="isLocked(current)">
            <x-alert variant="info" class="mt-4 flex items-start gap-2">
              <x-icon name="lock-closed" class="mt-0.5 h-4 w-4 shrink-0" />
              <span>{{ __('invoices.edit_versioned_note') }}</span>
            </x-alert>
          </template>

          <fieldset :disabled="isLocked(current) && ! editUnlocked" class="contents">

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
                      <td class="py-1 pr-2 align-top"><textarea x-model="l.desc" rows="2" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent resize-y min-h-[2.5rem]"></textarea></td>
                      <td class="py-1 px-2 align-top"><input type="number" step="0.01" x-model.number="l.qty" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2 align-top"><input type="text" x-model="l.unit" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2 align-top"><input type="number" step="0.01" x-model.number="l.unitPrice" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2 align-top"><input type="number" step="0.01" x-model.number="l.vatRate" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-right shadow-sm focus:border-accent focus:ring-accent"></td>
                      <td class="py-1 px-2 text-right tabular-nums text-gray-700 dark:text-gray-300 align-top" x-text="fmtMoney(lineNet(l))"></td>
                      <td class="py-1 pl-2 text-right align-top"><x-icon-button name="x-mark" size="sm" @click="removeLine(i)" title="{{ __('invoices.remove') }}" aria-label="{{ __('invoices.remove') }}" /></td>
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
          </fieldset>{{-- /editable fieldset --}}

          {{-- Version history (GoBD correction trail): each entry keeps the reason + date;
               online invoices carry a generated PDF per version, imported keep fields only. --}}
          <template x-if="current?.versions?.length">
            <div class="ll-card mt-6">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.versions_heading') }}</h2>
              <div class="mt-3 divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="v in [...current.versions].reverse()" :key="v.seq">
                  <div class="flex items-start justify-between gap-3 py-2">
                    <div class="min-w-0">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100 tabular-nums" x-text="v.label"></div>
                      <div class="text-xs text-gray-500 dark:text-gray-400" x-text="fmtDate(v.at)"></div>
                      <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300 break-words" x-text="v.reason"></div>
                    </div>
                    <div class="shrink-0">
                      <x-button variant="secondary" size="sm" icon="document-text" x-show="v.pdf" @click="openVersionPdf(v)">{{ __('invoices.version_open_pdf') }}</x-button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </template>

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
                          <td style="padding:9px 8px 9px 0; font-weight:500; vertical-align:top; white-space:pre-line;" x-text="l.desc"></td>
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
                        <td style="padding:9px 6px 9px 0; vertical-align:top; white-space:pre-line;" x-text="l.desc"></td>
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
                            <td><div class="ie-d-title" style="white-space:pre-line;" x-text="l.desc"></div></td>
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

            {{-- ---------- KLASSISCH (traditional German business sheet) ---------- --}}
            <template x-if="_printing && tpl === 'klassisch'">
              <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:10.5px; line-height:1.5; color:#1f2937; padding:18mm 16mm;">
                {{-- Wordmark / logo top-right --}}
                <div style="text-align:right; margin-bottom:26px;">
                  <template x-if="company.logo"><img :src="company.logo" alt="" style="max-height:44px; display:inline-block;"></template>
                  <template x-if="! company.logo"><div style="font-size:22px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:#334155;" x-text="company.name"></div></template>
                </div>
                <div style="display:flex; justify-content:space-between; gap:24px; align-items:flex-start;">
                  {{-- Left: sender one-liner + recipient block --}}
                  <div style="flex:1;">
                    <div style="font-size:8.5px; color:#6b7280; border-bottom:1px solid #e5e7eb; padding-bottom:3px; margin-bottom:12px;">
                      <strong style="color:#374151;" x-text="company.name"></strong><span x-text="company.address ? ' ' + company.address.replace(/\n/g, ' · ') : ''"></span>
                    </div>
                    <div style="font-weight:700; font-size:12px;" x-text="_printing.customer?.name"></div>
                    <div style="color:#374151;" x-show="_printing.customer?.attn" x-text="_printing.customer?.attn"></div>
                    <div style="color:#374151; white-space:pre-line;" x-text="_printing.customer?.address"></div>
                    <div style="color:#374151;" x-show="_printing.customer?.email" x-text="_printing.customer?.email"></div>
                    <div style="color:#374151; margin-top:2px;" x-show="_printing.customer?.vatId" x-text="pl('vat_id_label') + ' ' + _printing.customer?.vatId"></div>
                  </div>
                  {{-- Right: "Rechnung" info box --}}
                  <div style="width:240px;">
                    <div style="font-size:17px; font-weight:700; margin-bottom:8px;" x-text="pl('print_title')"></div>
                    <div style="display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px solid #f0f1f3;"><span style="color:#6b7280;" x-text="pl('invoice_number')"></span><span class="tabular-nums" style="font-weight:600;" x-text="_printing.number || '—'"></span></div>
                    <div style="display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px solid #f0f1f3;"><span style="color:#6b7280;" x-text="pl('invoice_date')"></span><span class="tabular-nums" x-text="_printing.issueDate"></span></div>
                    <div style="display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px solid #f0f1f3;"><span style="color:#6b7280;" x-text="pl('due')"></span><span class="tabular-nums" x-text="_printing.dueDate"></span></div>
                    <div style="display:flex; justify-content:space-between; padding:8px 10px; margin-top:8px; background:#f3f4f6; font-weight:700;"><span x-text="pl('payable') + ' ' + _printing.currency"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).gross, _printing.currency, _printing.lang)"></span></div>
                  </div>
                </div>
                {{-- Line-item table --}}
                <table style="width:100%; margin-top:26px; border-collapse:collapse;">
                  <thead><tr style="background:#eceef1; text-align:left; font-weight:700; font-size:9.5px;">
                    <th style="padding:7px 8px;" x-text="pl('line_desc')"></th>
                    <th style="padding:7px 8px; text-align:right; white-space:nowrap;" x-text="pl('line_qty')"></th>
                    <th style="padding:7px 8px; white-space:nowrap;" x-text="pl('line_unit')"></th>
                    <th style="padding:7px 8px; text-align:right; white-space:nowrap;" x-text="pl('line_price')"></th>
                    <th style="padding:7px 8px; text-align:right; white-space:nowrap;" x-text="pl('amount')"></th>
                  </tr></thead>
                  <tbody>
                    <template x-for="(l, i) in _printing.lines" :key="i">
                      <tr style="border-bottom:1px solid #e5e7eb;">
                        <td style="padding:9px 8px; vertical-align:top;">
                          <div style="font-weight:700;" x-text="(l.desc || '').split('\n')[0]"></div>
                          <div style="color:#6b7280; white-space:pre-line; margin-top:1px;" x-show="(l.desc || '').includes('\n')" x-text="(l.desc || '').split('\n').slice(1).join('\n')"></div>
                        </td>
                        <td style="padding:9px 8px; text-align:right; vertical-align:top; white-space:nowrap;" class="tabular-nums" x-text="fmtQty(l.qty, _printing.lang)"></td>
                        <td style="padding:9px 8px; vertical-align:top; white-space:nowrap;" x-text="l.unit || ''"></td>
                        <td style="padding:9px 8px; text-align:right; vertical-align:top; white-space:nowrap;" class="tabular-nums" x-text="fmtMoney(l.unitPrice, _printing.currency, _printing.lang)"></td>
                        <td style="padding:9px 8px; text-align:right; vertical-align:top; white-space:nowrap; font-weight:600;" class="tabular-nums" x-text="fmtMoney(lineNet(l), _printing.currency, _printing.lang)"></td>
                      </tr>
                    </template>
                  </tbody>
                </table>
                {{-- Totals --}}
                <div style="display:flex; justify-content:flex-end; margin-top:16px;">
                  <div style="width:260px;">
                    <div style="display:flex; justify-content:space-between; padding:3px 10px; color:#6b7280;"><span x-text="pl('subtotal')"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).net, _printing.currency, _printing.lang)"></span></div>
                    <template x-for="rate in vatRatesOf(_printing)" :key="rate">
                      <div style="display:flex; justify-content:space-between; padding:3px 10px; color:#6b7280;"><span x-text="pl('vat_at').replace(':rate', rate)"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).vatByRate[rate], _printing.currency, _printing.lang)"></span></div>
                    </template>
                    <div style="display:flex; justify-content:space-between; padding:8px 10px; margin-top:6px; border-top:2px solid #334155; font-weight:800; font-size:13px;"><span x-text="pl('gross')"></span><span class="tabular-nums" x-text="fmtMoney(computeTotals(_printing).gross, _printing.currency, _printing.lang)"></span></div>
                  </div>
                </div>
                <div style="margin-top:18px;" x-show="_printing.note">
                  <div style="font-weight:700; font-size:9px; text-transform:uppercase; letter-spacing:.06em; color:#6b7280;" x-text="pl('notes_heading')"></div>
                  <div style="white-space:pre-line; color:#374151; margin-top:2px;" x-text="_printing.note"></div>
                </div>
                <div style="margin-top:14px; text-align:center; color:#4b5563; white-space:pre-line;" x-show="_printing.footer || company.footer_text" x-text="_printing.footer || company.footer_text"></div>
                <div style="margin-top:24px; padding-top:10px; border-top:1px solid #e5e7eb; display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; font-size:8.5px; color:#6b7280;">
                  <div x-show="company.payment_terms_text"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.05em;" x-text="pl('payment_terms_heading')"></div><div style="white-space:pre-line;" x-text="company.payment_terms_text"></div></div>
                  <div x-show="company.payment_methods"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.05em;" x-text="pl('payment_methods_heading')"></div><div style="white-space:pre-line;" x-text="company.payment_methods"></div></div>
                  <div x-show="company.bank_name || company.iban"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.05em;" x-text="pl('bank_details')"></div><div x-text="[company.bank_name, company.iban ? 'IBAN ' + company.iban : '', company.bic ? 'BIC ' + company.bic : ''].filter(Boolean).join(' · ')"></div></div>
                </div>
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
        <div class="relative flex h-[85vh] w-[80vw] max-w-6xl flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('invoices.import_title') }}</h3>
            <x-icon-button name="x-mark" tone="gray" size="sm" @click="cancelImport()" x-show="! importReview?.running && ! importReview?.saving" aria-label="{{ __('common.close') }}" />
          </div>

          {{-- Parsing progress --}}
          <template x-if="importReview?.running">
            <div class="flex items-center justify-center gap-3 px-5 py-16 text-sm text-gray-500 dark:text-gray-400">
              <x-icon name="arrow-path" class="h-5 w-5 animate-spin" />
              <span x-text="'{{ __('invoices.import_parsing') }}'.replace(':done', importReview?.done ?? 0).replace(':total', importReview?.total ?? 0)"></span>
            </div>
          </template>

          {{-- Saving / upload progress --}}
          <template x-if="importReview?.saving">
            <div class="flex flex-col items-center justify-center gap-4 px-5 py-16">
              <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                <x-icon name="arrow-path" class="h-5 w-5 animate-spin" />
                <span x-text="'{{ __('invoices.import_saving') }}'.replace(':done', importReview?.saved ?? 0).replace(':total', importReview?.saveTotal ?? 0)"></span>
              </div>
              <div class="h-2 w-64 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full ll-accent transition-all" :style="{ width: (importReview?.saveTotal ? Math.round(importReview.saved / importReview.saveTotal * 100) : 0) + '%' }"></div>
              </div>
            </div>
          </template>

          {{-- Review stepper: one invoice at a time — original PDF inline (left) + the six
               key fields (right), editable, confirmed against the document before commit. --}}
          <template x-if="importCurrent">
            <div class="flex min-h-0 flex-1 flex-col">
              {{-- Stepper header: position + prev/next + failed count --}}
              <div class="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 px-5 py-2.5">
                <div class="flex items-center gap-2">
                  <x-icon-button name="chevron-left" tone="gray" size="sm" @click="importPrev()" ::disabled="importReview.idx <= 0" aria-label="{{ __('common.back') }}" />
                  <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="'{{ __('invoices.import_step') }}'.replace(':i', importReview.idx + 1).replace(':n', importReview.items.length)"></span>
                  <x-icon-button name="chevron-right" tone="gray" size="sm" @click="importNext()" ::disabled="importReview.idx >= importReview.items.length - 1" aria-label="{{ __('common.next') }}" />
                </div>
                <span x-show="importReview.failed" class="text-xs text-red-600 dark:text-red-400" x-text="'{{ __('invoices.import_failed_n') }}'.replace(':n', importReview.failed)"></span>
              </div>

              <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-2">
                {{-- PDF preview --}}
                <div class="min-h-0 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-[#111] md:border-b-0 md:border-r">
                  <iframe :src="importCurrent._url" class="h-full min-h-[40vh] w-full" title="{{ __('invoices.import_title') }}"></iframe>
                </div>
                {{-- Six key fields --}}
                <div class="min-h-0 overflow-auto px-5 py-4">
                  <label class="mb-3 flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    <input type="checkbox" x-model="importCurrent.selected" class="rounded border-gray-300 text-accent focus:ring-accent">
                    {{ __('invoices.import_include') }}
                  </label>
                  <div class="space-y-3">
                    <div>
                      <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_recipient') }}</label>
                      <input type="text" list="import-partner-names" x-model="importCurrent.recipient.name" placeholder="{{ __('invoices.customer_name') }}"
                        :class="importCurrent._warnings.includes('recipient') && ! importCurrent.recipient.name ? 'ring-1 ring-amber-400' : ''"
                        class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm focus:border-accent focus:ring-accent">
                      <datalist id="import-partner-names"><template x-for="n in partnerNames" :key="n"><option :value="n"></option></template></datalist>
                    </div>
                    {{-- Contact person (Ansprechpartner) — pick one of the partner's, or type a new one --}}
                    <div>
                      <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.partner_contact_person') }}</label>
                      <input type="text" list="import-partner-contacts" x-model="importCurrent.contactPerson" placeholder="—"
                        class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm focus:border-accent focus:ring-accent">
                      <datalist id="import-partner-contacts"><template x-for="c in partnerContactsFor(importCurrent.recipient.name)" :key="c.id"><option :value="c.name"></option></template></datalist>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.col_number') }}</label>
                        <input type="text" x-model="importCurrent.number" placeholder="—"
                          :class="importCurrent._warnings.includes('number') && ! importCurrent.number ? 'ring-1 ring-amber-400' : ''"
                          class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm tabular-nums focus:border-accent focus:ring-accent">
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.issue_date') }}</label>
                        <input type="date" x-model="importCurrent.issueDate"
                          :class="importCurrent._warnings.includes('date') && ! importCurrent.issueDate ? 'ring-1 ring-amber-400' : ''"
                          class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm focus:border-accent focus:ring-accent">
                      </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_gross') }}</label>
                        <input type="number" step="0.01" x-model.number="importCurrent.gross"
                          :class="importCurrent._warnings.includes('amount') && importCurrent.gross == null ? 'ring-1 ring-amber-400' : ''"
                          class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm text-right tabular-nums focus:border-accent focus:ring-accent">
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.import_vat_rate') }}</label>
                        <select x-model.number="importCurrent.vatRate" class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm focus:border-accent focus:ring-accent">
                          <template x-for="r in importVatChoices()" :key="r"><option :value="r" x-text="r + ' %'"></option></template>
                        </select>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('invoices.currency') }}</label>
                        <select x-model="importCurrent.currency" class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm focus:border-accent focus:ring-accent">
                          <template x-for="c in currencyOptions" :key="c"><option :value="c" x-text="c"></option></template>
                        </select>
                      </div>
                    </div>
                    {{-- Derived net + VAT preview --}}
                    <dl class="rounded-xl bg-gray-50 dark:bg-[#2c2c2e]/60 px-3 py-2 text-sm">
                      <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ __('invoices.net') }}</dt><dd class="tabular-nums text-gray-700 dark:text-gray-200" x-text="fmtMoney(importNet(importCurrent), importCurrent.currency, 'de')"></dd></div>
                      <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400" x-text="'{{ __('invoices.vat_at') }}'.replace(':rate', importCurrent.vatRate)"></dt><dd class="tabular-nums text-gray-700 dark:text-gray-200" x-text="fmtMoney(importVat(importCurrent), importCurrent.currency, 'de')"></dd></div>
                    </dl>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('invoices.import_hint') }}</p>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                <span class="text-xs text-gray-400 dark:text-gray-500" x-text="'{{ __('invoices.import_summary') }}'.replace(':n', importReview.items.length)"></span>
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
