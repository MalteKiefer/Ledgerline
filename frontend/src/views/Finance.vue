<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('messages.nav.finance') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <SectionNav :groups="financeNavGroups" :active="isFinanceSectionActive" @select="go($event.id)" />

      <div class="min-w-0 flex-1">
    <!-- Dashboard (consolidated: headline KPIs + charts + all statistics, one view) -->
    <div v-show="tab === 'dashboard'" class="space-y-4">
      <div v-if="kpis" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.stat_revenue') }} {{ kpis.year }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ money(kpis.net) }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.outstanding_total') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ money(openGross) }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.vat_payable') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums text-primary-600 dark:text-primary-300">{{ money(vatPayable) }}</div>
        </Card>
      </div>

      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-[var(--ll-muted)]">{{ t('invoices.tab_stats') }}</h2>
        <div class="w-32">
          <Select
            :model-value="String(statsYear)"
            :options="years.map((y) => ({ title: String(y), value: y }))"
            @update:model-value="onStatsYear"
          />
        </div>
      </div>

      <div v-if="kpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.stat_revenue') }}</div>
          <div class="mt-1 font-mono text-xl font-bold tabular-nums">{{ money(kpis.net) }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.invoice_count') }}</div>
          <div class="mt-1 font-mono text-xl font-bold tabular-nums">{{ kpis.count }}</div>
        </Card>
        <Card v-if="kpis.growthPct != null" :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">YoY</div>
          <div class="mt-1 font-mono text-xl font-bold tabular-nums">{{ kpis.growthPct }}%</div>
        </Card>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card :title="t('invoices.stat_monthly')">
          <div v-if="months.length" class="-ml-1"><Chart :data="monthlyChartData" :options="monthlyChartOptions" bars /></div>
          <div v-if="months.length" class="mt-3 overflow-x-auto border-t border-[var(--ll-border)] pt-3">
            <table class="w-full min-w-72 text-sm">
              <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
                <tr><th class="pb-1.5 font-medium">{{ t('common.date') }}</th><th class="pb-1.5 text-right font-medium">{{ t('invoices.gross') }}</th></tr>
              </thead>
              <tbody>
                <tr v-for="month in months" :key="month.month" class="border-t border-[var(--ll-border)]">
                  <td class="py-1.5">{{ monthLabel(month.month) }}</td>
                  <td class="py-1.5 text-right font-mono tabular-nums">{{ money(Number(month.net ?? 0)) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
        </Card>
        <Card :title="t('invoices.chart_by_category')">
          <div v-if="categoryBreakdown.length" class="-ml-1"><Chart :data="categoryChartData" :options="categoryChartOptions" bars /></div>
          <div v-if="categoryBreakdown.length" class="mt-3 overflow-x-auto border-t border-[var(--ll-border)] pt-3">
            <table class="w-full min-w-72 text-sm">
              <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
                <tr><th class="pb-1.5 font-medium">{{ t('invoices.receipt_category') }}</th><th class="pb-1.5 text-right font-medium">{{ t('invoices.gross') }}</th></tr>
              </thead>
              <tbody>
                <tr v-for="[category, total] in categoryBreakdown" :key="category" class="border-t border-[var(--ll-border)]">
                  <td class="py-1.5">{{ category }}</td>
                  <td class="py-1.5 text-right font-mono tabular-nums">{{ money(total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <!-- VAT advance -->
        <Card :title="t('invoices.vat_title')">
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.vat_output') }}</span><span class="font-mono tabular-nums">{{ money(Number(vatAdv?.outputVat ?? 0)) }}</span></div>
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.vat_input') }}</span><span class="font-mono tabular-nums">{{ money(Number(vatAdv?.inputVat ?? 0)) }}</span></div>
          <div class="my-1 border-t border-[var(--ll-border)]" />
          <div class="flex justify-between py-1 text-sm font-medium"><span>{{ t('invoices.vat_payable') }}</span><span class="font-mono tabular-nums text-primary-600 dark:text-primary-300">{{ money(Number(vatAdv?.payable ?? 0)) }}</span></div>
        </Card>

        <!-- EÜR -->
        <Card :title="t('invoices.euer_title')">
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.euer_income') }}</span><span class="font-mono tabular-nums">{{ money(Number(euerData?.income?.total ?? 0)) }}</span></div>
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.euer_expenses') }}</span><span class="font-mono tabular-nums">{{ money(Number(euerData?.expenses?.total ?? 0)) }}</span></div>
          <div class="my-1 border-t border-[var(--ll-border)]" />
          <div class="flex justify-between py-1 text-sm font-medium"><span>{{ t('invoices.euer_profit') }}</span><span class="font-mono tabular-nums">{{ money(Number(euerData?.profit ?? 0)) }}</span></div>
        </Card>

        <!-- Aging -->
        <Card :title="t('invoices.aging_title')">
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.aging_current') }}</span><span class="font-mono tabular-nums">{{ money(agingGross('current')) }}</span></div>
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.aging_1_30') }}</span><span class="font-mono tabular-nums">{{ money(agingGross('1_30')) }}</span></div>
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.aging_31_60') }}</span><span class="font-mono tabular-nums">{{ money(agingGross('31_60')) }}</span></div>
          <div class="flex justify-between py-1 text-sm"><span class="text-[var(--ll-muted)]">{{ t('invoices.aging_60plus') }}</span><span class="font-mono tabular-nums">{{ money(agingGross('60_plus')) }}</span></div>
          <div class="my-1 border-t border-[var(--ll-border)]" />
          <div class="flex justify-between py-1 text-sm font-medium"><span>{{ t('invoices.aging_open_total') }}</span><span class="font-mono tabular-nums text-amber-600 dark:text-amber-400">{{ money(openGross) }}</span></div>
        </Card>

        <!-- Revenue by customer -->
        <Card :title="t('invoices.stat_by_customer')">
          <div v-for="c in customers" :key="c.name" class="flex justify-between py-1 text-sm">
            <span class="max-w-[70%] truncate">{{ c.name }}</span><span class="font-mono tabular-nums">{{ money(Number(c.net ?? 0)) }}</span>
          </div>
          <div v-if="!customers.length" class="text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
        </Card>
      </div>

      <!-- GoBD invoice-numbering gaps -->
      <Card v-if="numberGapGroups.length" :title="t('invoices.gaps_title')" class="border-amber-500/40">
        <div v-for="(g, gi) in numberGapGroups" :key="gi" class="py-1.5 text-sm">
          <div class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
            <Icon name="warning" :size="14" />
            <span>{{ t('invoices.gaps_range', { group: gapGroupLabel(g.group), min: g.min, max: g.max }) }}</span>
          </div>
          <div class="mt-0.5 flex flex-wrap gap-1.5">
            <span v-for="n in g.missing" :key="n" class="rounded bg-amber-500/15 px-2 py-0.5 font-mono text-xs text-amber-700 dark:text-amber-400">{{ n }}</span>
          </div>
        </div>
      </Card>

      <div class="grid gap-4 md:grid-cols-2">
        <!-- Possible duplicates -->
        <Card :title="t('invoices.duplicates_title')">
          <div v-if="!dupInvoices.length && !dupTransactions.length" class="text-sm text-[var(--ll-muted)]">{{ t('invoices.duplicates_none') }}</div>
          <div v-for="(g, gi) in dupInvoices" :key="'i' + gi" class="py-1.5 text-sm">
            <div class="text-xs text-[var(--ll-muted)]">{{ t('invoices.dup_reason_' + g.reason) }}</div>
            <div class="mt-0.5 flex flex-wrap gap-1.5">
              <button v-for="id in g.ids" :key="id" class="rounded bg-black/[0.05] px-2 py-0.5 text-xs hover:bg-primary-500/15 dark:bg-white/10" @click="openInvoiceById(id)">{{ invoiceLabel(id) }}</button>
            </div>
          </div>
          <div v-for="(g, gi) in dupTransactions" :key="'t' + gi" class="py-1.5 text-sm">
            <div class="text-xs text-[var(--ll-muted)]">{{ t('invoices.dup_reason_' + g.reason) }}</div>
            <div class="mt-0.5 flex flex-wrap gap-1.5">
              <button v-for="id in g.ids" :key="id" class="rounded bg-black/[0.05] px-2 py-0.5 text-xs hover:bg-primary-500/15 dark:bg-white/10" @click="go('bank')">{{ txLabel(id) }}</button>
            </div>
          </div>
        </Card>

        <!-- Category suggestions (learned merchant → category) -->
        <Card :title="t('invoices.suggestions_title')">
          <div v-if="!catSuggestions.length" class="text-sm text-[var(--ll-muted)]">{{ t('invoices.suggestions_none') }}</div>
          <div v-for="s in catSuggestions" :key="s.tx_id" class="flex items-center justify-between gap-2 py-1 text-sm">
            <span class="max-w-[55%] truncate">{{ s.merchant }}</span>
            <Badge tone="gray">{{ s.suggested_category }}</Badge>
          </div>
        </Card>
      </div>
    </div>

    <!-- Documents: one review queue for outgoing invoices and incoming receipts.
         Both kinds can be filed here: an incoming receipt goes through the OCR
         inbox, an outgoing invoice PDF becomes a draft invoice with the document
         attached. A dropped file cannot say which it is, so the overlay splits
         in two and the drop target decides. -->
    <Card
      v-show="tab === 'documents'" :body-class="'p-0'" class="relative"
      @dragover.prevent="onDocDragOver" @dragenter.prevent="onDocDragOver" @dragleave.prevent="onDocDragLeave"
    >
      <!-- Busy first: while a batch runs, the split target would be a lie. -->
      <div v-if="inboxBusy || invUploadBusy" class="absolute inset-0 z-20 grid place-items-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-500/5 backdrop-blur-sm">
        <div class="text-center">
          <Icon name="progress_activity" :size="28" class="mx-auto mb-2 animate-spin text-primary-600 dark:text-primary-300" />
          <div class="text-sm font-medium">
            {{ inboxBusy
              ? t('invoices.inbox_processing', { done: String(inboxDone), total: String(inboxTotal) })
              : t('invoices.inbox_processing', { done: String(invUploadDone), total: String(invUploadTotal) }) }}
          </div>
        </div>
      </div>
      <div v-else-if="docDrag" class="absolute inset-0 z-20 grid grid-cols-2 gap-2 rounded-xl bg-[var(--ll-bg)]/80 p-2 backdrop-blur-sm">
        <div
          class="grid place-items-center rounded-lg border-2 border-dashed border-primary-500 bg-primary-500/5 text-center text-primary-600 dark:text-primary-300"
          @dragover.prevent @drop.prevent="onDropReceipts"
        >
          <div>
            <Icon name="inbox" :size="28" class="mx-auto mb-2" />
            <div class="text-sm font-medium">{{ t('invoices.upload_drop_receipt') }}</div>
            <div class="mt-0.5 text-xs opacity-80">{{ t('invoices.upload_drop_receipt_hint') }}</div>
          </div>
        </div>
        <div
          class="grid place-items-center rounded-lg border-2 border-dashed border-primary-500 bg-primary-500/5 text-center text-primary-600 dark:text-primary-300"
          @dragover.prevent @drop.prevent="onDropInvoices"
        >
          <div>
            <Icon name="receipt_long" :size="28" class="mx-auto mb-2" />
            <div class="text-sm font-medium">{{ t('invoices.upload_drop_invoice') }}</div>
            <div class="mt-0.5 text-xs opacity-80">{{ t('invoices.upload_drop_invoice_hint') }}</div>
          </div>
        </div>
      </div>
      <template #header>
        <div class="flex flex-wrap items-center gap-2">
          <TextField v-model="documentQuery" :placeholder="t('common.search')" icon="search" class="w-full sm:w-72" />
          <Select v-model="documentDirectionFilter" :options="documentDirectionOptions" class="w-36" />
          <Select v-model="documentMatchFilter" :options="documentMatchOptions" class="w-36" />
          <TextField v-model="documentFrom" type="date" :placeholder="t('invoices.document_from')" class="w-36" />
          <TextField v-model="documentTo" type="date" :placeholder="t('invoices.document_to')" class="w-36" />
          <Btn v-if="documentFiltersActive" variant="ghost" size="sm" icon="filter_alt_off" @click="resetDocumentFilters">{{ t('invoices.document_clear_filters') }}</Btn>
        </div>
      </template>
      <template #actions>
        <Btn variant="ghost" size="sm" icon="sell" @click="catDialog = true">{{ t('invoices.categories') }}</Btn>
        <Btn variant="ghost" size="sm" icon="document_scanner" :loading="bulkRescanBusy" @click="runBulkRescan">{{ t('invoices.ocr_rescan_all') }}</Btn>
        <Btn variant="ghost" size="sm" icon="join_inner" :loading="invMatchBusy || matchBusy" @click="runAllDocumentMatching">{{ t('invoices.auto_match') }}</Btn>
        <input ref="inboxInput" type="file" multiple accept="application/pdf,image/*" class="hidden" @change="onInboxPick">
        <input ref="invUploadInput" type="file" multiple accept="application/pdf" class="hidden" @change="onInvoicePick">
        <Btn variant="soft" size="sm" icon="inbox" :loading="inboxBusy" @click="inboxInput?.click()">{{ t('invoices.upload_receipt') }}</Btn>
        <Btn variant="soft" size="sm" icon="upload_file" :loading="invUploadBusy" @click="invUploadInput?.click()">{{ t('invoices.upload_invoice') }}</Btn>
        <Btn variant="ghost" size="sm" icon="receipt" @click="newReceipt">{{ t('invoices.receipt_standalone') }}</Btn>
        <Btn variant="solid" size="sm" icon="add" @click="newInvoice">{{ t('invoices.new') }}</Btn>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="documentSortBy('date')"><SortLabel :label="t('common.date')" active-key="date" :sort="documentSort" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="documentSortBy('direction')"><SortLabel :label="t('invoices.document_direction')" active-key="direction" :sort="documentSort" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="documentSortBy('partner')"><SortLabel :label="t('invoices.document_partner')" active-key="partner" :sort="documentSort" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 text-right font-medium" @click="documentSortBy('amount')"><SortLabel :label="t('invoices.gross')" active-key="amount" :sort="documentSort" justify="end" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="documentSortBy('matched')"><SortLabel :label="t('invoices.document_matching')" active-key="matched" :sort="documentSort" /></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in documentRows" :key="doc.key" class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5" @click="openDocument(doc)">
              <td class="px-4 py-2.5 whitespace-nowrap text-[var(--ll-muted)]">{{ fmtDate(doc.date) }}</td>
              <td class="px-4 py-2.5"><Badge :tone="doc.direction === 'income' ? 'success' : 'warning'">{{ t('invoices.document_' + doc.direction) }}</Badge></td>
              <td class="px-4 py-2.5"><div class="font-medium">{{ doc.partner }}</div><div class="text-xs text-[var(--ll-muted)]">{{ doc.reference }}</div></td>
              <td class="px-4 py-2.5 text-right font-mono tabular-nums" :class="doc.direction === 'income' ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ money(doc.amount) }}</td>
              <td class="px-4 py-2.5"><Badge :tone="doc.matched ? 'success' : 'gray'">{{ t(doc.matched ? 'invoices.document_matched' : 'invoices.document_unmatched') }}</Badge></td>
            </tr>
            <tr v-if="!documentRows.length"><td colspan="5" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Payments -->
    <Card v-show="tab === 'payments'" :title="t('invoices.tab_payments')">
      <template #actions><Btn variant="solid" size="sm" icon="add" @click="newPayment">{{ t('common.add') }}</Btn></template>
      <div class="divide-y divide-[var(--ll-border)]">
        <div v-for="p in f.paymentMethods" :key="p.id" class="flex items-center gap-3 py-2.5">
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium">{{ p.name }}</div>
            <div class="truncate text-xs text-[var(--ll-muted)]">{{ p.iban || p.type }}</div>
          </div>
          <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editPayment(p)" />
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="f.deletePayment(p.id).then(f.load)" />
        </div>
        <div v-if="!f.paymentMethods.length" class="py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</div>
      </div>
    </Card>

    <!-- Bank transactions -->
    <Card v-show="tab === 'bank'" :body-class="'p-0'">
      <template #header>
        <div class="flex flex-wrap items-center gap-2">
          <TextField v-model="txQuery" :placeholder="t('invoices.tx_search_ph')" icon="search" class="w-full sm:w-64" />
          <Select v-model="txDirection" :options="txDirectionItems" class="w-40" />
          <Select v-model="txDocFilter" :options="txDocItems" class="w-44" />
        </div>
      </template>
      <template #actions>
        <div class="flex items-center gap-2">
          <Select v-model.number="bankAccount" :options="bankAccountItems" class="w-44" />
          <Btn variant="ghost" size="sm" icon="upload" @click="bankCsvInput?.click()">{{ t('invoices.tx_import') }}</Btn>
          <input ref="bankCsvInput" type="file" accept=".csv,text/csv" class="hidden" @change="onBankCsv">
          <Btn variant="solid" size="sm" icon="add" @click="newTx">{{ t('common.add') }}</Btn>
        </div>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="txSortBy('date')"><SortLabel :label="t('common.date')" active-key="date" :sort="txSort" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="txSortBy('counterparty')"><SortLabel :label="t('invoices.tx_counterparty')" active-key="counterparty" :sort="txSort" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="txSortBy('purpose')"><SortLabel :label="t('invoices.tx_purpose')" active-key="purpose" :sort="txSort" /></th>
              <th class="cursor-pointer select-none px-4 py-2.5 text-right font-medium" @click="txSortBy('amount')"><SortLabel :label="t('invoices.gross')" active-key="amount" :sort="txSort" justify="end" /></th>
              <th class="px-4 py-2.5" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="tx in bankTransactions" :key="tx.id" class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
              <td class="px-4 py-2.5 whitespace-nowrap">{{ fmtDate(tx.date) }}</td>
              <td class="px-4 py-2.5"><div class="truncate">{{ tx.counterparty || '—' }}</div><div v-if="tx.counterparty_iban" class="truncate text-xs text-[var(--ll-muted)]">{{ tx.counterparty_iban }}</div></td>
              <td class="px-4 py-2.5"><div class="max-w-xs truncate text-[var(--ll-muted)]">{{ tx.purpose || '—' }}</div></td>
              <td class="px-4 py-2.5 text-right font-medium" :class="tx.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400'">{{ money(tx.amount) }}</td>
              <td class="px-4 py-2.5 text-right whitespace-nowrap">
                <button class="relative mr-1 inline-flex items-center rounded p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" :class="isTxDocumented(tx) ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" :title="t('invoices.tx_documents')" @click="openTxReceipts(tx)">
                  <Icon name="attach_file" :size="18" />
                  <span v-if="txDocumentCount(tx)" class="ml-0.5 text-xs tabular-nums">{{ txDocumentCount(tx) }}</span>
                </button>
                <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editTx(tx)" />
                <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delTx(tx)" />
              </td>
            </tr>
            <tr v-if="!bankTransactions.length"><td colspan="5" class="py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Projects (cost projects): tree list ↔ detail with the project ledger.
         A project earns money from hand-typed rows (Ab-/Zubuchungen), from bank
         transactions and from receipts assigned to it — see shared/finance-projects. -->
    <div v-show="tab === 'projects'">
      <!-- LIST (indented tree; every figure is the ROLLED-UP subtree total) -->
      <Card v-if="projectsView === 'list'" :title="t('invoices.tab_projects')" :body-class="'p-0'">
        <template #actions>
          <Btn variant="ghost" size="sm" icon="delete_sweep" :title="t('invoices.project_trash')" @click="openProjectTrash" />
          <Btn variant="solid" size="sm" icon="add" @click="newProject">{{ t('invoices.project_add') }}</Btn>
        </template>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
              <tr class="border-b border-[var(--ll-border)]">
                <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="prjSortBy('name')"><SortLabel :label="t('invoices.project_name')" active-key="name" :sort="prjSort" /></th>
                <th class="hidden cursor-pointer select-none px-4 py-2.5 text-right font-medium sm:table-cell" @click="prjSortBy('out')"><SortLabel :label="t('invoices.project_out')" active-key="out" :sort="prjSort" justify="end" /></th>
                <th class="hidden cursor-pointer select-none px-4 py-2.5 text-right font-medium sm:table-cell" @click="prjSortBy('in')"><SortLabel :label="t('invoices.project_in')" active-key="in" :sort="prjSort" justify="end" /></th>
                <th class="cursor-pointer select-none px-4 py-2.5 text-right font-medium" @click="prjSortBy('net')"><SortLabel :label="t('invoices.project_net')" active-key="net" :sort="prjSort" justify="end" /></th>
                <th class="px-4 py-2.5"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in pagedProjectRows" :key="row.p.id"
                class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
                @click="openProject(row.p)"
              >
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-3" :style="{ paddingLeft: (row.depth * 24) + 'px' }">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="account_tree" :size="18" /></span>
                    <span class="min-w-0">
                      <span class="flex items-center gap-2">
                        <span class="truncate font-medium">{{ row.p.name }}</span>
                        <Badge v-if="row.p.kind === 'private'" tone="gray">{{ t('invoices.project_kind_private') }}</Badge>
                      </span>
                      <span v-if="row.p.note" class="block truncate text-xs text-[var(--ll-muted)]">{{ row.p.note }}</span>
                    </span>
                  </div>
                </td>
                <td class="hidden px-4 py-2.5 text-right font-mono tabular-nums text-[var(--ll-muted)] sm:table-cell">{{ money(rolledFor(row.p.id).out) }}</td>
                <td class="hidden px-4 py-2.5 text-right font-mono tabular-nums text-[var(--ll-muted)] sm:table-cell">{{ money(rolledFor(row.p.id).in) }}</td>
                <td class="px-4 py-2.5 text-right font-mono font-medium tabular-nums">{{ money(rolledFor(row.p.id).net) }}</td>
                <td class="px-4 py-2.5 text-right"><Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" /></td>
              </tr>
              <tr v-if="!f.projects.length"><td colspan="5" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('invoices.project_empty') }}</td></tr>
            </tbody>
            <!-- Roots only: adding every row would count each subproject twice. -->
            <tfoot v-if="f.projects.length">
              <tr class="border-t border-[var(--ll-border)] text-xs uppercase tracking-wide text-[var(--ll-muted)]">
                <td class="px-4 py-2.5 font-medium">{{ t('invoices.project_total') }}</td>
                <td class="hidden px-4 py-2.5 text-right font-mono tabular-nums sm:table-cell">{{ money(projectGrandTotal.out) }}</td>
                <td class="hidden px-4 py-2.5 text-right font-mono tabular-nums sm:table-cell">{{ money(projectGrandTotal.in) }}</td>
                <td class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums text-[var(--ll-fg)]">{{ money(projectGrandTotal.net) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <Pager v-model:page="prjPage" v-model:per-page="prjPerPage" :total="projectRows.length" />
      </Card>

      <!-- DETAIL (ledger + assigned bookings/receipts + subprojects) -->
      <div v-else-if="projectsView === 'detail' && openProjectRec" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <Btn variant="ghost" size="sm" icon="arrow_back" :title="t('common.back')" @click="backToProjects" />
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="account_tree" :size="22" /></span>
            <span class="min-w-0">
              <span class="flex items-center gap-2">
                <h2 class="min-w-0 truncate text-lg font-semibold">{{ openProjectRec.name }}</h2>
                <Badge :tone="openProjectRec.kind === 'private' ? 'gray' : 'info'">{{ t('invoices.project_kind_' + (openProjectRec.kind || 'business')) }}</Badge>
              </span>
              <span v-if="projectParentName(openProjectRec)" class="block truncate text-xs text-[var(--ll-muted)]">{{ t('invoices.project_parent') }}: {{ projectParentName(openProjectRec) }}</span>
            </span>
          </div>
          <div class="flex items-center gap-2">
            <Btn variant="soft" size="sm" icon="edit" @click="editProject(openProjectRec)">{{ t('common.edit') }}</Btn>
            <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delProject(openProjectRec)" />
          </div>
        </div>

        <!-- Figures: this project alone, then the whole branch below it -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <Card :body-class="'p-4'">
            <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.project_out') }}</div>
            <div class="mt-1 font-mono text-xl font-bold tabular-nums">{{ money(openProjectOwn.out) }}</div>
          </Card>
          <Card :body-class="'p-4'">
            <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.project_in') }}</div>
            <div class="mt-1 font-mono text-xl font-bold tabular-nums">{{ money(openProjectOwn.in) }}</div>
          </Card>
          <Card :body-class="'p-4'">
            <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.project_own') }}</div>
            <div class="mt-1 font-mono text-xl font-bold tabular-nums">{{ money(openProjectOwn.net) }}</div>
          </Card>
          <Card :body-class="'p-4'">
            <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.project_rolled') }}</div>
            <div class="mt-1 font-mono text-xl font-bold tabular-nums text-primary-600 dark:text-primary-300">{{ money(openProjectRolled.net) }}</div>
          </Card>
        </div>

        <!-- Hand-typed ledger -->
        <Card :body-class="'p-0'">
          <template #header><h2 class="text-sm font-semibold">{{ t('invoices.project_ledger') }} <span class="text-[var(--ll-muted)]">({{ projectLedger.length }})</span></h2></template>
          <template #actions>
            <Btn variant="soft" size="sm" icon="south_west" @click="newExpense('in')">{{ t('invoices.project_add_in') }}</Btn>
            <Btn variant="solid" size="sm" icon="north_east" @click="newExpense('out')">{{ t('invoices.project_add_out') }}</Btn>
          </template>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
                <tr class="border-b border-[var(--ll-border)]">
                  <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="expSortBy('date')"><SortLabel :label="t('common.date')" active-key="date" :sort="expSort" /></th>
                  <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="expSortBy('title')"><SortLabel :label="t('invoices.project_title')" active-key="title" :sort="expSort" /></th>
                  <th class="hidden cursor-pointer select-none px-4 py-2.5 font-medium md:table-cell" @click="expSortBy('category')"><SortLabel :label="t('invoices.receipt_category')" active-key="category" :sort="expSort" /></th>
                  <th class="hidden cursor-pointer select-none px-4 py-2.5 font-medium lg:table-cell" @click="expSortBy('account')"><SortLabel :label="t('invoices.project_account')" active-key="account" :sort="expSort" /></th>
                  <th class="cursor-pointer select-none px-4 py-2.5 text-right font-medium" @click="expSortBy('amount')"><SortLabel :label="t('invoices.gross')" active-key="amount" :sort="expSort" justify="end" /></th>
                  <th class="px-4 py-2.5"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in pagedLedger" :key="row.id" class="border-b border-[var(--ll-border)] last:border-0">
                  <td class="px-4 py-2.5 align-top tabular-nums">{{ fmtDate(row.date) }}</td>
                  <td class="px-4 py-2.5">
                    <span class="block font-medium">{{ row.title || '—' }}</span>
                    <span v-if="row.note" class="block whitespace-pre-line text-xs text-[var(--ll-muted)]">{{ row.note }}</span>
                  </td>
                  <td class="hidden px-4 py-2.5 md:table-cell">
                    <Badge v-if="row.category" tone="gray">{{ row.category }}</Badge>
                    <span v-else class="text-[var(--ll-muted)]">—</span>
                    <span v-if="catAccountNo(row.category)" class="ml-1 font-mono text-xs text-[var(--ll-muted)]">{{ catAccountNo(row.category) }}</span>
                  </td>
                  <td class="hidden px-4 py-2.5 text-[var(--ll-muted)] lg:table-cell">{{ paymentName(row.payment_method_id) || '—' }}</td>
                  <td class="px-4 py-2.5 text-right font-mono tabular-nums" :class="row.direction === 'in' ? 'text-emerald-600 dark:text-emerald-400' : ''">
                    {{ (row.direction === 'in' ? '+' : '−') + ' ' + money(row.amount) }}
                  </td>
                  <td class="px-4 py-2.5">
                    <div class="flex items-center justify-end gap-0.5">
                      <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editExpense(row)" />
                      <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delExpense(row)" />
                    </div>
                  </td>
                </tr>
                <tr v-if="!projectLedger.length"><td colspan="6" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('invoices.project_ledger_empty') }}</td></tr>
              </tbody>
              <!-- Hand-typed rows only — the assigned bookings/receipts are counted in the tiles above. -->
              <tfoot v-if="projectLedger.length">
                <tr class="border-t border-[var(--ll-border)]">
                  <td colspan="4" class="px-4 py-2.5 text-xs uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.project_total') }}</td>
                  <td class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums">{{ money(projectLedgerTotals.net) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <Pager v-model:page="expPage" v-model:per-page="expPerPage" :total="projectLedger.length" />
        </Card>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <!-- Bank transactions assigned to this project -->
          <Card :body-class="'p-0'">
            <template #header><h2 class="text-sm font-semibold">{{ t('invoices.project_bookings') }} <span class="text-[var(--ll-muted)]">({{ projectTxs.length }})</span></h2></template>
            <template #actions><Btn variant="soft" size="sm" icon="add_link" @click="openAssignProjectTx">{{ t('invoices.project_assign_tx') }}</Btn></template>
            <div class="divide-y divide-[var(--ll-border)]">
              <div v-for="tx in projectTxs" :key="tx.id" class="flex items-center gap-3 px-4 py-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="account_balance" :size="18" /></span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-medium">{{ tx.counterparty || t('invoices.tx_counterparty') }}</span>
                  <span class="block truncate text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(tx.date) }} · {{ tx.purpose || '—' }}</span>
                </span>
                <span class="shrink-0 font-mono text-sm tabular-nums" :class="tx.amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : ''">{{ money(tx.amount) }}</span>
                <Btn variant="ghost" size="sm" icon="link_off" :title="t('invoices.project_unassign')" @click="unassignTxFromProject(tx)" />
              </div>
              <p v-if="!projectTxs.length" class="px-4 py-3 text-sm text-[var(--ll-muted)]">—</p>
            </div>
          </Card>

          <!-- Receipts bundled into this project -->
          <Card :body-class="'p-0'">
            <template #header><h2 class="text-sm font-semibold">{{ t('invoices.project_receipts') }} <span class="text-[var(--ll-muted)]">({{ projectReceipts.length }})</span></h2></template>
            <template #actions><Btn variant="soft" size="sm" icon="add_link" @click="openAssignProjectReceipt">{{ t('invoices.project_assign_receipt') }}</Btn></template>
            <div class="divide-y divide-[var(--ll-border)]">
              <div v-for="r in projectReceipts" :key="r.id" class="flex items-center gap-3 px-4 py-2.5">
                <button type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300" :title="t('common.open')" @click="openReceiptWorkspace(r)"><Icon name="receipt" :size="18" /></button>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-medium">{{ r.name }}</span>
                  <span class="block truncate text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(r.date ?? r.created_at) }}<template v-if="r.category"> · {{ r.category }}</template></span>
                </span>
                <span class="shrink-0 font-mono text-sm tabular-nums">{{ r.amount != null ? money(Number(r.amount)) : '—' }}</span>
                <Btn variant="ghost" size="sm" icon="link_off" :title="t('invoices.project_unassign')" @click="unassignReceiptFromProject(r)" />
              </div>
              <p v-if="!projectReceipts.length" class="px-4 py-3 text-sm text-[var(--ll-muted)]">—</p>
            </div>
          </Card>
        </div>

        <!-- Subprojects (direct children; each figure includes ITS own branch) -->
        <Card v-if="projectChildren.length" :body-class="'p-0'">
          <template #header><h2 class="text-sm font-semibold">{{ t('invoices.project_subprojects') }} <span class="text-[var(--ll-muted)]">({{ projectChildren.length }})</span></h2></template>
          <template #actions><Btn variant="soft" size="sm" icon="add" @click="newSubProject">{{ t('invoices.project_add') }}</Btn></template>
          <div class="divide-y divide-[var(--ll-border)]">
            <button v-for="c in projectChildren" :key="c.id" type="button" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-black/[0.02] dark:hover:bg-white/5" @click="openProject(c)">
              <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="account_tree" :size="18" /></span>
              <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ c.name }}</span></span>
              <span class="shrink-0 font-mono text-sm tabular-nums">{{ money(rolledFor(c.id).net) }}</span>
              <Icon name="chevron_right" :size="18" class="shrink-0 text-[var(--ll-muted)]" />
            </button>
          </div>
        </Card>
        <div v-else class="flex justify-end">
          <Btn variant="soft" size="sm" icon="add" @click="newSubProject">{{ t('invoices.project_subprojects') }}</Btn>
        </div>
      </div>
    </div>

    <!-- Partners (business partners / Geschäftspartner): list ↔ detail -->
    <div v-show="tab === 'partners'">
      <!-- LIST / TABLE -->
      <Card v-if="partnersView === 'list'" :body-class="'p-0'">
        <template #header>
          <TextField v-model="partnerSearch" :placeholder="t('invoices.partners_search')" icon="search" class="w-full sm:w-72" />
        </template>
        <template #actions><Btn variant="solid" size="sm" icon="add" @click="newPartner">{{ t('invoices.partner_add') }}</Btn></template>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
              <tr class="border-b border-[var(--ll-border)]">
                <th class="cursor-pointer select-none px-4 py-2.5 font-medium" @click="partnerSortBy('name')"><SortLabel :label="t('invoices.partner_name')" active-key="name" :sort="partnerSort" /></th>
                <th class="hidden cursor-pointer select-none px-4 py-2.5 font-medium md:table-cell" @click="partnerSortBy('contact')"><SortLabel :label="t('invoices.partner_contact_person')" active-key="contact" :sort="partnerSort" /></th>
                <th class="hidden cursor-pointer select-none px-4 py-2.5 font-medium lg:table-cell" @click="partnerSortBy('email')"><SortLabel :label="t('invoices.partner_email')" active-key="email" :sort="partnerSort" /></th>
                <th class="hidden cursor-pointer select-none px-4 py-2.5 font-medium lg:table-cell" @click="partnerSortBy('phone')"><SortLabel :label="t('invoices.partner_phone')" active-key="phone" :sort="partnerSort" /></th>
                <th class="hidden cursor-pointer select-none px-4 py-2.5 font-medium md:table-cell" @click="partnerSortBy('vat')"><SortLabel :label="t('invoices.partner_vat')" active-key="vat" :sort="partnerSort" /></th>
                <th class="cursor-pointer select-none px-4 py-2.5 text-right font-medium" @click="partnerSortBy('links')"><SortLabel :label="t('invoices.partner_links')" active-key="links" :sort="partnerSort" justify="end" /></th>
                <th class="px-4 py-2.5"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in filteredPartners" :key="p.id" class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5" @click="openPartner(p)">
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-3">
                    <img v-if="logoSrc(p.logo)" :src="logoSrc(p.logo)" alt="" class="h-8 w-8 shrink-0 rounded-lg border border-[var(--ll-border)] bg-white object-contain">
                    <span v-else class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10"><Icon name="groups" :size="18" /></span>
                    <span class="font-medium">{{ p.name }}</span>
                  </div>
                </td>
                <td class="hidden px-4 py-2.5 text-[var(--ll-muted)] md:table-cell">{{ partnerContactName(p) || '—' }}</td>
                <td class="hidden px-4 py-2.5 text-[var(--ll-muted)] lg:table-cell">{{ p.email || '—' }}</td>
                <td class="hidden px-4 py-2.5 text-[var(--ll-muted)] tabular-nums lg:table-cell">{{ p.phone || '—' }}</td>
                <td class="hidden px-4 py-2.5 text-[var(--ll-muted)] tabular-nums md:table-cell">{{ p.vat_id || '—' }}</td>
                <td class="px-4 py-2.5 text-right text-[var(--ll-muted)] tabular-nums">{{ partnerLinkCount(p.id) }}</td>
                <td class="px-4 py-2.5 text-right"><Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" /></td>
              </tr>
              <tr v-if="!filteredPartners.length"><td colspan="7" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('invoices.partners_empty') }}</td></tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- DETAIL (info + linked invoices/receipts) -->
      <div v-else-if="partnersView === 'detail' && openPartnerRec" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <Btn variant="ghost" size="sm" icon="arrow_back" :title="t('common.back')" @click="backToPartners" />
            <img v-if="logoSrc(openPartnerRec.logo)" :src="logoSrc(openPartnerRec.logo)" alt="" class="h-10 w-10 shrink-0 rounded-xl border border-[var(--ll-border)] bg-white object-contain">
            <span v-else class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10"><Icon name="groups" :size="22" /></span>
            <h2 class="min-w-0 truncate text-lg font-semibold">{{ openPartnerRec.name }}</h2>
          </div>
          <div class="flex items-center gap-2">
            <Btn variant="soft" size="sm" icon="edit" @click="editPartner(openPartnerRec)">{{ t('common.edit') }}</Btn>
            <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delPartner(openPartnerRec)" />
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <!-- Info -->
          <Card class="lg:col-span-1" :title="t('invoices.partner_info')">
            <dl class="space-y-2.5 text-sm">
              <div v-if="openPartnerRec.vat_id"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_vat') }}</dt><dd class="tabular-nums">{{ openPartnerRec.vat_id }}</dd></div>
              <div v-if="openPartnerRec.email"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_email') }}</dt><dd><a :href="'mailto:' + openPartnerRec.email" class="text-primary-600 hover:underline dark:text-primary-300">{{ openPartnerRec.email }}</a></dd></div>
              <div v-if="openPartnerRec.invoice_email"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_invoice_email') }}</dt><dd><a :href="'mailto:' + openPartnerRec.invoice_email" class="text-primary-600 hover:underline dark:text-primary-300">{{ openPartnerRec.invoice_email }}</a></dd></div>
              <div v-if="openPartnerRec.hourly_rate != null && openPartnerRec.hourly_rate !== ''"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_hourly_rate') }}</dt><dd class="tabular-nums">{{ fmtRate(openPartnerRec) }}</dd></div>
              <div v-if="openPartnerRec.currency"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_currency') }}</dt><dd>{{ openPartnerRec.currency }}</dd></div>
              <div v-if="openPartnerRec.phone"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_phone') }}</dt><dd class="tabular-nums">{{ openPartnerRec.phone }}</dd></div>
              <div v-if="openPartnerRec.url"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_url') }}</dt><dd><a :href="safeHref(openPartnerRec.url)" target="_blank" rel="noopener noreferrer" class="break-all text-primary-600 hover:underline dark:text-primary-300">{{ openPartnerRec.url }}</a></dd></div>
              <div v-if="openPartnerRec.address"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_address') }}</dt><dd class="whitespace-pre-line">{{ openPartnerRec.address }}</dd></div>
              <div v-if="openPartnerRec.category"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.receipt_category') }}</dt><dd class="mt-0.5"><Badge tone="gray">{{ openPartnerRec.category }}</Badge></dd></div>
              <div v-if="openPartnerRec.note"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.receipt_note') }}</dt><dd class="whitespace-pre-line">{{ openPartnerRec.note }}</dd></div>
            </dl>
            <!-- Contact persons -->
            <div v-if="openPartnerRec.contacts && openPartnerRec.contacts.length" class="mt-4 border-t border-[var(--ll-border)] pt-3">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('invoices.partner_contacts') }}</h3>
              <ul class="mt-2 space-y-2">
                <li v-for="(c, i) in openPartnerRec.contacts" :key="c.id ?? i" class="text-sm">
                  <div class="font-medium">{{ c.name }}<span v-if="c.role" class="ml-1 text-xs font-normal text-[var(--ll-muted)]">· {{ c.role }}</span></div>
                  <div class="text-xs text-[var(--ll-muted)]">{{ [c.email, c.phone].filter(Boolean).join(' · ') }}</div>
                </li>
              </ul>
            </div>
          </Card>

          <!-- Linked invoices + receipts -->
          <div class="space-y-4 lg:col-span-2">
            <Card :body-class="'p-0'">
              <template #header><h2 class="text-sm font-semibold">{{ t('invoices.partner_linked_invoices') }} <span class="text-[var(--ll-muted)]">({{ invoicesForPartner(openPartnerRec.id).length }})</span></h2></template>
              <div class="divide-y divide-[var(--ll-border)]">
                <button v-for="inv in invoicesForPartner(openPartnerRec.id)" :key="inv.id" type="button" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-black/[0.02] dark:hover:bg-white/5" @click="editInvoice(inv)">
                  <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="receipt_long" :size="18" /></span>
                  <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium tabular-nums">{{ inv.number || '—' }}</span><span class="block text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(inv.issue_date) }}</span></span>
                  <span class="shrink-0 text-sm tabular-nums">{{ money(Number(inv.gross ?? 0)) }}</span>
                </button>
                <p v-if="!invoicesForPartner(openPartnerRec.id).length" class="px-4 py-3 text-sm text-[var(--ll-muted)]">—</p>
              </div>
            </Card>
            <Card :body-class="'p-0'">
              <template #header><h2 class="text-sm font-semibold">{{ t('invoices.partner_linked_receipts') }} <span class="text-[var(--ll-muted)]">({{ receiptsForPartner(openPartnerRec.id).length }})</span></h2></template>
              <div class="divide-y divide-[var(--ll-border)]">
                <button v-for="r in receiptsForPartner(openPartnerRec.id)" :key="r.id" type="button" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-black/[0.02] dark:hover:bg-white/5" @click="editReceipt(r)">
                  <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="receipt" :size="18" /></span>
                  <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ r.name }}</span><span class="block text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(r.date ?? r.created_at) }}</span></span>
                  <span class="shrink-0 text-sm tabular-nums">{{ r.amount != null ? money(Number(r.amount)) : '—' }}</span>
                </button>
                <p v-if="!receiptsForPartner(openPartnerRec.id).length" class="px-4 py-3 text-sm text-[var(--ll-muted)]">—</p>
              </div>
            </Card>
          </div>
        </div>
      </div>
    </div>

    <!-- Invoice editor -->
    <Modal v-model="invDialog" :title="draft?.id ? (draft?.number || t('invoices.new')) : t('invoices.new')" width="820px">
      <div v-if="draft">
        <!-- header actions + status -->
        <div class="mb-3 flex items-center gap-1">
          <!-- Paid is terminal: once settled, the status can't be flipped back to
               open/sent from here (GoBD — a correction goes through Storno, not a
               silent status edit). Same read-only badge as an imported invoice. -->
          <Select v-if="draft.id && !draft.imported && draft.status !== 'paid'" v-model="draft.status" :options="statusOptions" class="w-40" />
          <Badge v-else-if="draft.status" :tone="statusTone(draft.status)">{{ t('invoices.status_' + draft.status) }}</Badge>
          <div v-if="draft.id && draft.number" class="ml-auto flex items-center gap-0.5">
            <Btn variant="ghost" size="sm" icon="mail" :title="t('invoices.email_send')" @click="doEmail(draft as Invoice)" />
            <Btn variant="ghost" size="sm" icon="gavel" :title="t('invoices.dun_send')" @click="doDun(draft as Invoice)" />
            <Btn v-if="draft.type !== 'credit_note'" variant="ghost" size="sm" icon="cancel" :title="t('invoices.storno')" @click="doStorno(draft as Invoice)" />
          </div>
        </div>

        <div v-if="draft.imported" class="mb-3 rounded-lg bg-blue-500/10 px-3 py-2 text-sm text-blue-600 dark:text-blue-400">{{ t('invoices.imported_readonly') }}</div>

        <fieldset :disabled="isLocked" class="m-0 border-0 p-0">
          <div class="mb-3">
            <Select
              :label="t('invoices.tab_partners')"
              :model-value="draft.partner_id ?? ''"
              :options="[{ title: '—', value: '' }, ...f.partners.map((p) => ({ title: p.name, value: p.id }))]"
              @update:model-value="applyPartnerToInvoice($event ? Number($event) : null)"
            />
          </div>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <TextField v-model="custName_" :label="t('invoices.customer')" class="col-span-2" />
            <TextField v-model="draft.issue_date" :label="t('invoices.issue_date')" type="date" />
            <TextField v-model="draft.due_date" :label="t('invoices.due_date')" type="date" />
            <TextField v-model="custAttn_" :label="t('invoices.cust_attn')" class="col-span-2" />
            <TextField v-model="custEmail_" :label="t('common.email')" type="email" class="col-span-2" />
            <TextField v-model="custVatId_" :label="t('invoices.vat_id')" class="col-span-2" />
          </div>
          <label class="mt-3 block">
            <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.cust_address') }}</span>
            <textarea
              :value="custAddress_" rows="2"
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
              @input="custAddress_ = ($event.target as HTMLTextAreaElement).value"
            />
          </label>
          <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Select
              v-model="discountType_"
              :label="t('invoices.discount')"
              :options="[{ title: t('invoices.discount_none'), value: '' }, { title: '%', value: 'percent' }, { title: draft.currency || 'EUR', value: 'amount' }]"
            />
            <TextField v-if="discountType_" inputmode="decimal" :label="t('invoices.discount_value')" :model-value="moneyInput(draft.discount_value)" @update:model-value="draft.discount_value = parseMoney($event)" />
            <TextField type="number" :label="t('invoices.skonto_percent')" :model-value="draft.skonto_percent ?? ''" @update:model-value="draft.skonto_percent = $event === '' ? null : Number($event)" />
            <TextField type="number" :label="t('invoices.skonto_days')" :model-value="draft.skonto_days ?? ''" @update:model-value="draft.skonto_days = $event === '' ? null : Number($event)" />
          </div>

          <div class="mb-1 mt-4 text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.line_desc') }}</div>
          <div v-for="(l, i) in lines" :key="i" class="mb-1.5 flex items-center gap-2">
            <TextField v-model="l.desc" :placeholder="t('invoices.line_desc')" class="flex-1" />
            <TextField type="number" class="w-20" :model-value="l.qty" @update:model-value="l.qty = Number($event)" />
            <TextField inputmode="decimal" class="w-28" :model-value="moneyInput(l.unitPrice)" @update:model-value="l.unitPrice = parseMoney($event) ?? 0" />
            <TextField type="number" class="w-20" :model-value="l.vatRate" @update:model-value="l.vatRate = Number($event)" />
            <span class="text-sm text-[var(--ll-muted)]">%</span>
            <Btn variant="ghost" size="sm" icon="close" @click="lines.splice(i, 1)" />
          </div>
          <Btn variant="ghost" size="sm" icon="add" @click="lines.push({ desc: '', qty: 1, unitPrice: 0, vatRate: 19 })">{{ t('invoices.add_line') }}</Btn>

          <label class="mt-3 block">
            <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.note') }}</span>
            <textarea
              :value="draft.note ?? ''" rows="2"
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] placeholder:text-[var(--ll-muted)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
              @input="draft.note = ($event.target as HTMLTextAreaElement).value"
            />
          </label>
        </fieldset>

        <div class="my-4 border-t border-[var(--ll-border)]" />
        <div class="flex justify-end gap-6 text-sm">
          <div><span class="text-[var(--ll-muted)]">{{ t('invoices.net') }}:</span> <span class="font-mono tabular-nums">{{ money(totals.net) }}</span></div>
          <div><span class="text-[var(--ll-muted)]">{{ t('invoices.vat') }}:</span> <span class="font-mono tabular-nums">{{ money(totals.vat) }}</span></div>
          <div class="font-medium">{{ t('invoices.gross') }}: <span class="font-mono tabular-nums">{{ money(totals.gross) }}</span></div>
        </div>
      </div>
      <template #footer>
        <div class="mr-auto flex items-center gap-2">
          <Btn v-if="draft && draft.id" variant="soft" icon="print" :loading="pdfBusy" @click="doPrintDraft">{{ t('invoices.print') }}</Btn>
          <Btn v-if="draft && draft.id && !draft.number" variant="soft" @click="finalize">{{ t('invoices.finalize') }}</Btn>
        </div>
        <Btn variant="ghost" @click="invDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveInvoice">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Payment-method editor -->
    <Modal v-model="pDialog" :title="pForm.id ? t('common.edit') : t('common.add')" width="480px">
      <div class="space-y-3">
        <TextField v-model="pForm.name" :label="t('common.name')" />
        <Select v-model="pForm.type" label="Type" :options="['bank', 'card', 'paypal', 'cash', 'other'].map((x) => ({ title: x, value: x }))" />
        <TextField v-model="pForm.holder" :label="t('invoices.pm_holder')" />
        <template v-if="pForm.type === 'bank'">
          <TextField v-model="pForm.iban" label="IBAN" />
          <div class="grid grid-cols-2 gap-3">
            <TextField v-model="pForm.bic" label="BIC" />
            <TextField v-model="pForm.account_no" :label="t('invoices.pm_account_no')" />
          </div>
          <TextField v-model="pForm.bank" :label="t('invoices.pm_bank')" />
          <label class="flex items-center gap-2 text-sm">
            <input v-model="pForm.business" type="checkbox" class="accent-primary-500">
            {{ t('invoices.pm_business') }}
          </label>
        </template>
        <template v-else-if="pForm.type === 'card'">
          <TextField v-model="pForm.card_number" :label="t('invoices.pm_card_number')" />
          <div class="grid grid-cols-2 gap-3">
            <TextField v-model="pForm.card_network" :label="t('invoices.pm_card_network')" />
            <TextField v-model="pForm.card_expiry" :label="t('invoices.pm_card_expiry')" />
          </div>
        </template>
        <TextField v-else-if="pForm.type === 'paypal'" v-model="pForm.paypal_email" label="PayPal" type="email" />
        <TextField v-model="pForm.url" :label="t('invoices.pm_url')" />
        <TextField v-model="pForm.note" :label="t('invoices.note')" />
      </div>
      <template #footer>
        <Btn variant="ghost" @click="pDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="savePP">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- CSV import status (was previously invisible — the whole import ran with no
         feedback at all while the user waited, not knowing whether anything happened). -->
    <Modal v-model="csvImportDialog" :title="t('invoices.tx_import')" width="380px">
      <div class="flex items-center gap-3 py-2">
        <Icon name="progress_activity" :size="24" class="shrink-0 animate-spin text-primary-600 dark:text-primary-300" />
        <span class="text-sm">{{ csvImportStage === 'parsing' ? t('invoices.tx_import_parsing') : t('invoices.tx_import_uploading') }}</span>
      </div>
    </Modal>

    <!-- Bank transaction editor -->
    <Modal v-model="txDialog" :title="txForm.id ? t('common.edit') : t('common.add')" width="480px">
      <div class="space-y-3">
        <Select v-model.number="txForm.payment_method_id" :label="t('invoices.tab_payments')" :options="f.paymentMethods.map((p) => ({ title: p.name, value: p.id }))" />
        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="txForm.date" :label="t('common.date')" type="date" />
          <TextField v-model="txForm.amount" :label="t('invoices.gross')" inputmode="decimal" />
        </div>
        <TextField v-model="txForm.counterparty" :label="t('invoices.tx_counterparty')" />
        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="txForm.counterparty_iban" label="IBAN" />
          <TextField v-model="txForm.bic" label="BIC" />
        </div>
        <TextField v-model="txForm.purpose" :label="t('invoices.tx_purpose')" />
        <TextField v-model="txForm.booking_text" :label="t('invoices.tx_booking_text')" />
        <Select v-model="txForm.vat_cat" :label="t('invoices.tx_vat')" :options="vatCatItems" />
        <Select
          :label="t('invoices.tab_projects')"
          :model-value="txForm.finance_project_id ?? ''"
          :options="projectSelectOptions"
          @update:model-value="txForm.finance_project_id = $event ? Number($event) : null"
        />
      </div>
      <template #footer>
        <Btn variant="ghost" @click="txDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveTx">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Bank transaction documents (receipt and/or incoming invoice) -->
    <Modal v-model="txRecDialog" :title="t('invoices.tx_documents')" width="480px">
      <div v-if="txRecTx" class="space-y-2">
        <div v-if="linkedInvoiceForTx(txRecTx)" class="flex items-center gap-2 rounded-lg border border-primary-500/30 bg-primary-500/5 px-3 py-2">
          <Icon name="receipt_long" :size="18" class="shrink-0 text-primary-600 dark:text-primary-300" />
          <span class="flex-1 truncate text-sm"><span class="font-medium">{{ linkedInvoiceForTx(txRecTx)?.number }}</span><span class="ml-1 text-[var(--ll-muted)]">{{ money(Number(linkedInvoiceForTx(txRecTx)?.gross ?? 0)) }}</span></span>
          <Btn variant="ghost" size="sm" icon="open_in_new" :title="t('common.open')" @click="openLinkedInvoice(txRecTx)" />
        </div>
        <div v-for="r in (txRecTx.receipts ?? [])" :key="r.id" class="flex items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2">
          <Icon name="receipt" :size="18" class="shrink-0 text-[var(--ll-muted)]" />
          <span class="flex-1 truncate text-sm">{{ r.name }}</span>
          <Btn variant="ghost" size="sm" icon="open_in_new" :title="t('common.open')" @click="openTxReceipt(r)" />
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delTxReceipt(r)" />
        </div>
        <div v-if="!linkedInvoiceForTx(txRecTx) && !(txRecTx.receipts?.length ?? 0) && !standaloneReceiptsForTx(txRecTx.id).length" class="py-4 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</div>
        <div v-if="standaloneReceiptsForTx(txRecTx.id).length" class="space-y-2 border-t border-[var(--ll-border)] pt-3">
          <span class="block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipts_title') }}</span>
          <div v-for="r in standaloneReceiptsForTx(txRecTx.id)" :key="'std-' + r.id" class="flex items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2">
            <Icon name="receipt_long" :size="18" class="shrink-0 text-[var(--ll-muted)]" />
            <span class="flex-1 truncate text-sm">{{ r.name }}</span>
            <span class="font-mono text-xs tabular-nums text-[var(--ll-muted)]">{{ r.amount != null ? money(Number(r.amount)) : '' }}</span>
            <Btn variant="ghost" size="sm" icon="open_in_new" :title="t('common.open')" @click="openPreview(f.receiptFileUrl(r.id), r.name, r.mime)" />
            <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600 dark:text-red-400" :title="t('invoices.receipts_unlink')" @click="unlinkStandaloneReceipt(r)" />
          </div>
        </div>
        <div class="mt-3 border-t border-[var(--ll-border)] pt-3">
          <input ref="txRecInput" type="file" accept=".pdf,image/*" class="hidden" @change="onTxReceiptFile">
          <Btn variant="soft" icon="upload" :loading="txRecBusy" @click="txRecInput?.click()">{{ t('invoices.tx_receipt_attach') }}</Btn>
        </div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="txRecDialog = false">{{ t('common.close') }}</Btn>
      </template>
    </Modal>

    <!-- Document preview: receipts + invoice PDFs, in-app instead of a new tab -->
    <Modal v-model="previewOpen" :title="previewName" width="64rem">
      <div class="flex h-[70vh] items-center justify-center overflow-auto rounded-lg bg-black/[0.03] dark:bg-white/5">
        <img v-if="previewIsImage" :src="previewUrl" class="max-h-full max-w-full object-contain" :alt="previewName">
        <iframe v-else :src="previewUrl" class="h-full w-full border-0"></iframe>
      </div>
      <template #footer>
        <Btn variant="ghost" tag="a" :href="previewUrl" target="_blank" icon="open_in_new">{{ t('invoices.preview_new_tab') }}</Btn>
        <Btn variant="ghost" tag="a" :href="previewDownloadUrl" download icon="download">{{ t('files.download') }}</Btn>
        <Btn variant="solid" @click="previewOpen = false">{{ t('common.close') }}</Btn>
      </template>
    </Modal>

    <!-- Category suggestions for receipt/partner category inputs -->
    <datalist id="fin-cats">
      <option v-for="c in f.financeCategories" :key="c.id" :value="c.name" />
    </datalist>

    <!-- Finance category manager -->
    <Modal v-model="catDialog" :title="t('invoices.categories')" width="480px">
      <div class="space-y-2">
        <div v-for="c in f.financeCategories" :key="c.id" class="flex items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2">
          <span class="h-4 w-4 shrink-0 rounded-full border border-[var(--ll-border)]" :style="{ backgroundColor: c.color || 'transparent' }" />
          <span class="min-w-0 flex-1 truncate text-sm">{{ c.name }}</span>
          <span v-if="c.account_no" class="shrink-0 rounded bg-black/[0.05] px-1.5 py-0.5 font-mono text-xs text-[var(--ll-muted)] dark:bg-white/10">{{ c.account_no }}</span>
          <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editCat(c)" />
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delCat(c)" />
        </div>
        <div v-if="!f.financeCategories.length" class="py-4 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</div>
        <div class="mt-3 border-t border-[var(--ll-border)] pt-3">
          <div class="flex items-end gap-2">
            <TextField v-model="catForm.name" :label="catForm.id ? t('common.edit') : t('common.add')" class="flex-1" />
            <input v-model="catForm.color" type="color" class="h-9 w-10 shrink-0 rounded border border-[var(--ll-border)] bg-transparent" :title="t('invoices.cat_color')">
            <Btn variant="solid" :loading="saving" :disabled="!catForm.name.trim()" @click="saveCat">{{ catForm.id ? t('common.save') : t('common.add') }}</Btn>
            <Btn v-if="catForm.id" variant="ghost" @click="resetCat">{{ t('common.cancel') }}</Btn>
          </div>
          <TextField v-model="catForm.account_no" :label="t('invoices.cat_account_no')" :hint="t('invoices.cat_account_no_hint')" class="mt-2" />
        </div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="catDialog = false">{{ t('common.close') }}</Btn>
      </template>
    </Modal>

    <!-- Business-partner editor (all fields + multiple contact persons + logo pull) -->
    <Modal v-model="pDlg" :title="partnerForm.id ? t('common.edit') : t('invoices.partner_add')" width="920px">
      <div class="space-y-3">
        <TextField v-model="partnerForm.name" :label="t('invoices.partner_name') + ' *'" />

        <!-- Website + logo pull -->
        <div>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.partner_url') }}</span>
          <div class="flex items-center gap-2">
            <img v-if="logoSrc(partnerForm.logo)" :src="logoSrc(partnerForm.logo)" alt="" class="h-9 w-9 shrink-0 rounded-lg border border-[var(--ll-border)] bg-white object-contain">
            <span v-else class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10"><Icon name="groups" :size="18" /></span>
            <TextField v-model="partnerForm.url" type="url" placeholder="https://…" class="min-w-0 flex-1" />
            <Btn variant="soft" size="sm" icon="download" :loading="logoBusy" @click="loadPartnerLogo">{{ t('invoices.partner_load_logo') }}</Btn>
          </div>
          <span class="mt-1 block text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_url_hint') }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="partnerForm.email" :label="t('invoices.partner_email')" type="email" />
          <TextField v-model="partnerForm.phone" :label="t('invoices.partner_phone')" type="tel" />
        </div>

        <TextField v-model="partnerForm.invoice_email" :label="t('invoices.partner_invoice_email')" type="email" :hint="t('invoices.partner_invoice_email_hint')" />

        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="partnerForm.hourly_rate" :label="t('invoices.partner_hourly_rate')" inputmode="decimal" :placeholder="moneyPlaceholder" />
          <Select v-model="partnerForm.currency" :label="t('invoices.partner_currency')" :options="currencyOptions" />
        </div>

        <div class="flex items-end gap-2">
          <TextField v-model="partnerForm.vat_id" :label="t('invoices.partner_vat')" placeholder="DE…" class="min-w-0 flex-1" />
          <input ref="partnerOcrInput" type="file" accept="application/pdf,image/*" class="hidden" @change="scanPartnerDoc">
          <Btn variant="soft" size="sm" icon="document_scanner" :loading="partnerOcrBusy" :title="t('invoices.partner_scan_doc')" @click="partnerOcrInput?.click()">{{ t('invoices.partner_scan_doc') }}</Btn>
        </div>

        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.partner_address') }}</span>
          <textarea
            v-model="partnerForm.address" rows="2"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          />
        </label>

        <!-- Contact persons (Ansprechpartner) — multiple -->
        <div>
          <div class="mb-1.5 flex items-center justify-between">
            <span class="text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.partner_contacts') }}</span>
            <Btn variant="ghost" size="xs" icon="add" @click="addContact">{{ t('invoices.partner_contact_add') }}</Btn>
          </div>
          <div class="space-y-2">
            <div v-for="(c, i) in partnerForm.contacts" :key="c.id" class="rounded-lg border border-[var(--ll-border)] p-2">
              <div class="flex items-center gap-2">
                <TextField v-model="c.name" :placeholder="t('invoices.partner_contact_person')" class="min-w-0 flex-1" />
                <TextField v-model="c.role" :placeholder="t('invoices.partner_contact_role')" class="w-28 shrink-0" />
                <Btn variant="ghost" size="sm" icon="delete" class="shrink-0 text-red-600 dark:text-red-400" :title="t('common.delete')" @click="removeContact(i)" />
              </div>
              <div class="mt-2 grid grid-cols-2 gap-2">
                <TextField v-model="c.email" :placeholder="t('invoices.partner_email')" type="email" />
                <TextField v-model="c.phone" :placeholder="t('invoices.partner_phone')" type="tel" />
              </div>
            </div>
          </div>
        </div>

        <TextField v-model="partnerForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" list="fin-cats" />

        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipt_note') }}</span>
          <textarea
            v-model="partnerForm.note" rows="2"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          />
        </label>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="pDlg = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="savePartnerForm">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Receipt editor -->
    <Modal v-model="rDialog" :title="rForm.id ? t('common.edit') : t('invoices.receipt_standalone')" width="520px">
      <div class="space-y-3">
        <label v-if="!rForm.id" class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipt') }}</span>
          <div class="flex items-center gap-2">
            <input
              type="file" accept="application/pdf,image/*"
              class="block w-full flex-1 text-sm text-[var(--ll-fg)] file:mr-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 hover:file:bg-primary-500/15 dark:file:text-primary-300"
              @change="rFile = ($event.target as HTMLInputElement).files?.[0] ?? null"
            >
            <Btn v-if="rFile" variant="soft" size="sm" icon="document_scanner" :loading="ocrBusy" @click="scanReceipt">{{ t('invoices.ocr_scan') }}</Btn>
          </div>
        </label>
        <div v-if="rForm.id" class="flex justify-end">
          <Btn variant="soft" size="sm" icon="document_scanner" :loading="rescanBusy" @click="rescanReceipt">{{ t('invoices.ocr_rescan') }}</Btn>
        </div>
        <TextField v-model="rForm.name" :label="t('invoices.receipt_rename')" />
        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="rForm.amount" :label="t('invoices.gross')" inputmode="decimal" />
          <TextField v-model="rForm.date" :label="t('common.date')" type="date" />
        </div>
        <TextField v-model="rForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" list="fin-cats" />
        <div>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipt_tags') }}</span>
          <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-[var(--ll-border)] px-2 py-1.5">
            <span v-for="(tag, i) in rForm.tags" :key="i" class="inline-flex items-center gap-1 rounded-md bg-primary-500/12 px-2 py-0.5 text-xs font-medium text-primary-600 dark:text-primary-300">
              {{ tag }}
              <button type="button" class="grid place-items-center" @click="rForm.tags.splice(i, 1)"><Icon name="close" :size="14" /></button>
            </span>
            <input
              class="min-w-[6rem] flex-1 bg-transparent text-sm text-[var(--ll-fg)] focus:outline-none"
              @keyup.enter="(($event.target as HTMLInputElement).value.trim()) && (rForm.tags.push(($event.target as HTMLInputElement).value.trim()), (($event.target as HTMLInputElement).value = ''))"
            >
          </div>
        </div>
        <Select
          :label="t('invoices.tab_partners')"
          :model-value="rForm.partner_id ?? ''"
          :options="[{ title: '—', value: '' }, ...partnerOptions.map((o) => ({ title: o.name, value: o.id }))]"
          @update:model-value="rForm.partner_id = $event ? Number($event) : null"
        />
        <Select
          :label="t('invoices.tab_projects')"
          :model-value="rForm.finance_project_id ?? ''"
          :options="projectSelectOptions"
          @update:model-value="rForm.finance_project_id = $event ? Number($event) : null"
        />
        <TextField v-model="rForm.vat" :label="t('invoices.vat')" />
        <div>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.tx_receipts') }}</span>
          <div v-if="rForm.bank_transaction_id != null" class="flex items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2">
            <Icon name="link" :size="16" class="shrink-0 text-green-600 dark:text-green-400" />
            <span class="flex-1 truncate text-sm">{{ txSummary(rForm.bank_transaction_id) }}</span>
            <Btn variant="ghost" size="sm" @click="openAssignTx">{{ t('common.edit') }}</Btn>
            <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600 dark:text-red-400" :title="t('invoices.receipts_unlink')" @click="unassignTx" />
          </div>
          <div v-else-if="rForm.linked_transaction_ids?.length" class="space-y-1.5 rounded-lg border border-[var(--ll-border)] px-3 py-2">
            <div class="text-xs text-[var(--ll-muted)]">{{ t('invoices.receipts_split_hint') }}</div>
            <div v-for="txId in rForm.linked_transaction_ids" :key="txId" class="flex items-center gap-2">
              <Icon name="link" :size="16" class="shrink-0 text-green-600 dark:text-green-400" />
              <span class="flex-1 truncate text-sm">{{ txSummary(txId) }}</span>
            </div>
            <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600 dark:text-red-400" :title="t('invoices.receipts_unlink')" @click="unassignSplitTx">{{ t('invoices.receipts_unlink') }}</Btn>
          </div>
          <Btn v-else variant="soft" size="sm" icon="link" @click="openAssignTx">{{ t('invoices.receipts_assign_tx') }}</Btn>
        </div>
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipt_note') }}</span>
          <textarea
            v-model="rForm.note" rows="2"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          />
        </label>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="rDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveReceipt">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Receipt workspace: opened by clicking a row. Preview + edit fields side by
         side in ONE surface — no bouncing between a read-only preview modal and a
         separate edit modal via two different buttons. -->
    <Modal v-model="rWorkspace" :title="rForm.name || t('common.edit')" width="1100px">
      <div class="flex flex-col gap-5 lg:flex-row">
        <div class="min-w-0 flex-1">
          <div class="grid h-[60vh] place-items-center overflow-hidden rounded-lg border border-[var(--ll-border)] bg-black/[0.02] dark:bg-white/5">
            <img v-if="rWorkspaceIsImage" :src="f.receiptFileUrl(rForm.id ?? 0)" class="max-h-full max-w-full object-contain" :alt="rForm.name">
            <iframe v-else-if="rWorkspaceIsPdf" :src="f.receiptFileUrl(rForm.id ?? 0)" class="h-full w-full border-0"></iframe>
            <div v-else class="p-6 text-center text-sm text-[var(--ll-muted)]">
              <Icon name="description" :size="32" class="mx-auto mb-2" />
              {{ t('invoices.receipt_no_preview') }}
            </div>
          </div>
          <div class="mt-2 flex justify-end">
            <Btn variant="ghost" size="sm" tag="a" :href="f.receiptFileUrl(rForm.id ?? 0)" target="_blank" icon="open_in_new">{{ t('invoices.preview_new_tab') }}</Btn>
          </div>
        </div>
        <div class="w-full space-y-3 lg:w-80 lg:shrink-0">
          <div class="flex justify-end">
            <Btn variant="soft" size="sm" icon="document_scanner" :loading="rescanBusy" @click="rescanReceipt">{{ t('invoices.ocr_rescan') }}</Btn>
          </div>
          <TextField v-model="rForm.name" :label="t('invoices.receipt_rename')" />
          <div class="grid grid-cols-2 gap-3">
            <TextField v-model="rForm.amount" :label="t('invoices.gross')" inputmode="decimal" />
            <Select v-model="rForm.currency" :label="t('invoices.currency')" :options="CURRENCY_OPTIONS" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <TextField v-model="rForm.date" :label="t('common.date')" type="date" />
            <TextField v-model="rForm.doc_number" :label="t('invoices.receipt_doc_number')" />
          </div>
          <TextField v-model="rForm.order_ref" :label="t('invoices.receipt_order_ref')" :placeholder="t('invoices.receipt_order_ref_ph')" />
          <div>
            <TextField v-model="rForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" list="fin-cats" />
            <div v-if="catAccountNo(rForm.category)" class="mt-1 text-xs text-[var(--ll-muted)]">{{ t('invoices.cat_account_no') }}: <span class="font-mono">{{ catAccountNo(rForm.category) }}</span></div>
          </div>
          <div>
            <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipt_tags') }}</span>
            <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-[var(--ll-border)] px-2 py-1.5">
              <span v-for="(tag, i) in rForm.tags" :key="i" class="inline-flex items-center gap-1 rounded-md bg-primary-500/12 px-2 py-0.5 text-xs font-medium text-primary-600 dark:text-primary-300">
                {{ tag }}
                <button type="button" class="grid place-items-center" @click="rForm.tags.splice(i, 1)"><Icon name="close" :size="14" /></button>
              </span>
              <input
                class="min-w-[6rem] flex-1 bg-transparent text-sm text-[var(--ll-fg)] focus:outline-none"
                @keyup.enter="(($event.target as HTMLInputElement).value.trim()) && (rForm.tags.push(($event.target as HTMLInputElement).value.trim()), (($event.target as HTMLInputElement).value = ''))"
              >
            </div>
          </div>
          <div>
            <Select
              :label="t('invoices.tab_partners')"
              :model-value="rForm.partner_id ?? ''"
              :options="[{ title: '—', value: '' }, ...partnerOptions.map((o) => ({ title: o.name, value: o.id }))]"
              @update:model-value="rForm.partner_id = $event ? Number($event) : null"
            />
            <div v-if="partnerVatId(rForm.partner_id)" class="mt-1 text-xs text-[var(--ll-muted)]">{{ t('invoices.vat_id') }}: <span class="font-mono">{{ partnerVatId(rForm.partner_id) }}</span></div>
          </div>
          <Select
            :label="t('invoices.tab_projects')"
            :model-value="rForm.finance_project_id ?? ''"
            :options="projectSelectOptions"
            @update:model-value="rForm.finance_project_id = $event ? Number($event) : null"
          />
          <TextField v-model="rForm.vat" :label="t('invoices.vat')" />
          <div>
            <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.tx_receipts') }}</span>
            <div v-if="rForm.bank_transaction_id != null" class="flex items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2">
              <Icon name="link" :size="16" class="shrink-0 text-green-600 dark:text-green-400" />
              <span class="flex-1 truncate text-sm">{{ txSummary(rForm.bank_transaction_id) }}</span>
              <Btn variant="ghost" size="sm" @click="openAssignTx">{{ t('common.edit') }}</Btn>
              <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600 dark:text-red-400" :title="t('invoices.receipts_unlink')" @click="unassignTx" />
            </div>
            <div v-else-if="rForm.linked_transaction_ids?.length" class="space-y-1.5 rounded-lg border border-[var(--ll-border)] px-3 py-2">
              <div class="text-xs text-[var(--ll-muted)]">{{ t('invoices.receipts_split_hint') }}</div>
              <div v-for="txId in rForm.linked_transaction_ids" :key="txId" class="flex items-center gap-2">
                <Icon name="link" :size="16" class="shrink-0 text-green-600 dark:text-green-400" />
                <span class="flex-1 truncate text-sm">{{ txSummary(txId) }}</span>
              </div>
              <Btn variant="ghost" size="sm" icon="link_off" class="text-red-600 dark:text-red-400" :title="t('invoices.receipts_unlink')" @click="unassignSplitTx">{{ t('invoices.receipts_unlink') }}</Btn>
            </div>
            <Btn v-else variant="soft" size="sm" icon="link" @click="openAssignTx">{{ t('invoices.receipts_assign_tx') }}</Btn>
          </div>
          <label class="block">
            <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.receipt_note') }}</span>
            <textarea
              v-model="rForm.note" rows="2"
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            />
          </label>
        </div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="rWorkspace = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveReceipt">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Bulk rescan: re-run OCR recognition against every already-uploaded receipt's
         stored text and review what changed, per document, before applying. -->
    <Modal v-model="bulkRescanDialog" :title="t('invoices.ocr_rescan_all')" width="640px">
      <div class="space-y-3">
        <label
          v-for="row in bulkRescanRows" :key="row.id"
          class="flex items-start gap-3 rounded-lg border border-[var(--ll-border)] px-3 py-2.5"
        >
          <input v-model="row.accepted" type="checkbox" class="mt-1">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium">{{ row.name }}</div>
            <div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-[var(--ll-muted)]">
              <div v-if="row.date"><span class="font-medium text-[var(--ll-fg)]">{{ t('common.date') }}:</span> {{ fmtDate(row.date) }}</div>
              <div v-if="row.total != null"><span class="font-medium text-[var(--ll-fg)]">{{ t('invoices.gross') }}:</span> {{ money(row.total) }}</div>
              <div v-if="row.category"><span class="font-medium text-[var(--ll-fg)]">{{ t('invoices.receipt_category') }}:</span> {{ row.category }}</div>
              <div v-if="row.number"><span class="font-medium text-[var(--ll-fg)]">{{ t('invoices.receipt_doc_number') }}:</span> {{ row.number }}</div>
              <div v-if="row.merchant"><span class="font-medium text-[var(--ll-fg)]">{{ t('invoices.receipt_partner') }}:</span> {{ row.merchant }}</div>
              <div v-if="row.suggestedName && row.suggestedName !== row.name" class="col-span-2 truncate"><span class="font-medium text-[var(--ll-fg)]">{{ t('invoices.receipt_rename') }}:</span> {{ row.suggestedName }}</div>
            </div>
          </div>
        </label>
        <div v-if="!bulkRescanRows.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('invoices.ocr_rescan_all_none') }}</div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="bulkRescanDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn v-if="bulkRescanRows.length" variant="solid" :loading="bulkRescanApplying" @click="applyBulkRescan">{{ t('invoices.match_apply') }}</Btn>
      </template>
    </Modal>

    <!-- Manual "assign a booking" picker: ranked suggestions (amount known) or a
         searchable list of every unlinked transaction (amount unknown). -->
    <Modal v-model="assignTxDialog" :title="t('invoices.receipts_assign_tx')" width="480px">
      <div class="space-y-2">
        <TextField v-model="assignTxQuery" :placeholder="t('common.search')" />
        <div class="max-h-80 space-y-1.5 overflow-y-auto">
          <button
            v-for="s in assignTxCandidates" :key="s.t.id" type="button"
            class="flex w-full items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2 text-left hover:bg-black/[0.02] dark:hover:bg-white/5"
            @click="pickAssignTx(s.t.id)"
          >
            <Badge v-if="s.kind === 'fx'" tone="warning">{{ t('invoices.match_reason_fx') }}</Badge>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm">{{ fmtDate(s.t.date) }} · {{ (f.transactions.find((x) => x.id === s.t.id))?.counterparty || '—' }}</div>
            </div>
            <span class="font-mono text-sm tabular-nums">{{ money(s.t.amount) }}</span>
          </button>
          <div v-if="!assignTxCandidates.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('invoices.receipts_assign_tx_none') }}</div>
        </div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="assignTxDialog = false">{{ t('common.close') }}</Btn>
      </template>
    </Modal>

    <!-- Auto-Zuordnen: review + apply server-suggested receipt<->transaction links, both
         directions — several receipts summing to one charge (e.g. a split Amazon
         order) AND one receipt summing to several charges (e.g. one INWX invoice
         settled by two separate bookings). -->
    <Modal v-model="matchDialog" :title="t('invoices.auto_match')" width="640px">
      <div class="space-y-3">
        <div v-if="matchDuplicates.length" class="rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
          {{ t('invoices.match_duplicates_found', { count: String(matchDuplicates.length) }) }}
        </div>
        <label
          v-for="g in matchGroups" :key="g.transaction_id + '-' + g.receipt_ids.join(',')"
          class="flex items-start gap-3 rounded-lg border border-[var(--ll-border)] px-3 py-2.5"
        >
          <input v-model="g.accepted" type="checkbox" class="mt-1">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium">{{ txSummary(g.transaction_id) }}</span>
              <Badge tone="info">{{ matchReasonLabel(g.reason) }}</Badge>
            </div>
            <div class="mt-0.5 text-xs text-[var(--ll-muted)]">
              {{ g.receipt_ids.map((id) => receiptLabel(id)).join(', ') }}
            </div>
          </div>
          <span class="shrink-0 font-mono text-sm tabular-nums">{{ money(g.total) }}</span>
        </label>
        <!-- The inverse case: one receipt that no single transaction matches, but a
             combination of several does (e.g. one INWX invoice settled by two
             separate charges). Applying links the receipt to ALL of them. -->
        <label
          v-for="g in splitGroups" :key="'split-' + g.receipt_id"
          class="flex items-start gap-3 rounded-lg border border-[var(--ll-border)] px-3 py-2.5"
        >
          <input v-model="g.accepted" type="checkbox" class="mt-1">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium">{{ receiptLabel(g.receipt_id) }}</span>
              <Badge tone="info">{{ t('invoices.match_reason_split') }}</Badge>
            </div>
            <div class="mt-0.5 text-xs text-[var(--ll-muted)]">
              {{ g.transaction_ids.map((id) => txSummary(id)).join(' + ') }}
            </div>
          </div>
          <span class="shrink-0 font-mono text-sm tabular-nums">{{ money(g.total) }}</span>
        </label>
        <div v-if="!matchGroups.length && !splitGroups.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('invoices.match_none') }}</div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="matchDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn v-if="matchGroups.length || splitGroups.length" variant="solid" :loading="matchApplying" @click="applyMatches">{{ t('invoices.match_apply') }}</Btn>
      </template>
    </Modal>

    <!-- Auto-Zuordnen: review + apply invoice<->transaction links (marks the
         invoice paid, or for an already-paid one just backfills the link). -->
    <Modal v-model="invMatchDialog" :title="t('invoices.match_run')" width="640px">
      <div class="space-y-3">
        <label
          v-for="m in invMatches" :key="m.invoiceId + '-' + m.txId"
          class="flex items-start gap-3 rounded-lg border border-[var(--ll-border)] px-3 py-2.5"
        >
          <input v-model="m.accepted" type="checkbox" class="mt-1">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium">{{ invoiceLabel(m.invoiceId) }}</span>
              <Badge tone="info">{{ invMatchReasonLabel(m.reason) }}</Badge>
              <Badge v-if="!m.markPaid" tone="gray">{{ t('invoices.linked_badge') }}</Badge>
            </div>
            <div class="mt-0.5 text-xs text-[var(--ll-muted)]">{{ txSummary(m.txId) }}</div>
          </div>
        </label>
        <div v-if="!invMatches.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('invoices.inv_match_none') }}</div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="invMatchDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn v-if="invMatches.length" variant="solid" :loading="invMatchApplying" @click="applyInvoiceMatches">{{ t('invoices.match_apply') }}</Btn>
      </template>
    </Modal>

    <!-- Project editor -->
    <Modal v-model="prjDialog" :title="prjForm.id ? t('invoices.project_edit') : t('invoices.project_add')" width="480px">
      <div class="space-y-3">
        <TextField v-model="prjForm.name" :label="t('invoices.project_name')" />
        <Select
          :label="t('invoices.project_parent')"
          :model-value="prjForm.parent_id ?? ''"
          :options="[{ title: t('invoices.project_parent_none'), value: '' }, ...parentOptions.map((o) => ({ title: o.name, value: o.id }))]"
          @update:model-value="prjForm.parent_id = $event ? Number($event) : null"
        />
        <Select
          v-model="prjForm.kind"
          :label="t('invoices.project_kind')"
          :options="[{ title: t('invoices.project_kind_business'), value: 'business' }, { title: t('invoices.project_kind_private'), value: 'private' }]"
        />
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.project_note') }}</span>
          <textarea
            v-model="prjForm.note" rows="2"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          />
        </label>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="prjDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveProject">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Ledger row (hand-typed Ab-/Zubuchung) -->
    <Modal v-model="expDialog" :title="expForm.direction === 'in' ? t('invoices.project_add_in') : t('invoices.project_add_out')" width="480px">
      <div class="space-y-3">
        <Select
          v-model="expForm.direction"
          :label="t('invoices.project_direction')"
          :options="[{ title: t('invoices.project_add_out'), value: 'out' }, { title: t('invoices.project_add_in'), value: 'in' }]"
        />
        <TextField v-model="expForm.title" :label="t('invoices.project_title')" :placeholder="t('invoices.project_title_ph')" />
        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="expForm.amount" :label="t('invoices.gross')" inputmode="decimal" :placeholder="moneyPlaceholder" />
          <TextField v-model="expForm.date" :label="t('common.date')" type="date" />
        </div>
        <TextField v-model="expForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" list="fin-cats" />
        <div v-if="catAccountNo(expForm.category)" class="-mt-2 text-xs text-[var(--ll-muted)]">{{ t('invoices.cat_account_no') }}: <span class="font-mono">{{ catAccountNo(expForm.category) }}</span></div>
        <Select
          :label="t('invoices.project_account')"
          :model-value="expForm.payment_method_id ?? ''"
          :options="[{ title: '—', value: '' }, ...f.paymentMethods.map((p) => ({ title: p.name, value: p.id }))]"
          @update:model-value="expForm.payment_method_id = $event ? Number($event) : null"
        />
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.project_description') }}</span>
          <textarea
            v-model="expForm.note" rows="3"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          />
        </label>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="expDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveExpense">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Assign an existing bank transaction to the open project -->
    <Modal v-model="assignPrjTxDialog" :title="t('invoices.project_assign_tx')" width="640px">
      <TextField v-model="assignPrjTxQuery" :placeholder="t('invoices.tx_search_ph')" icon="search" class="mb-3" />
      <div class="max-h-96 divide-y divide-[var(--ll-border)] overflow-y-auto rounded-lg border border-[var(--ll-border)]">
        <button
          v-for="tx in assignPrjTxCandidates" :key="tx.id" type="button"
          class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-black/[0.02] dark:hover:bg-white/5"
          @click="assignTxToProject(tx)"
        >
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ tx.counterparty || '—' }}</span>
            <span class="block truncate text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(tx.date) }} · {{ tx.purpose || '—' }}</span>
          </span>
          <!-- Already on ANOTHER project: assigning moves it here, so say so. -->
          <Badge v-if="tx.finance_project_id != null" tone="warning">{{ projectName(tx.finance_project_id) }}</Badge>
          <span class="shrink-0 font-mono text-sm tabular-nums">{{ money(tx.amount) }}</span>
        </button>
        <p v-if="!assignPrjTxCandidates.length" class="px-3 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </div>
      <template #footer><Btn variant="ghost" @click="assignPrjTxDialog = false">{{ t('common.close') }}</Btn></template>
    </Modal>

    <!-- Bundle an existing receipt into the open project -->
    <Modal v-model="assignPrjRcDialog" :title="t('invoices.project_assign_receipt')" width="640px">
      <TextField v-model="assignPrjRcQuery" :placeholder="t('common.search')" icon="search" class="mb-3" />
      <div class="max-h-96 divide-y divide-[var(--ll-border)] overflow-y-auto rounded-lg border border-[var(--ll-border)]">
        <button
          v-for="r in assignPrjRcCandidates" :key="r.id" type="button"
          class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-black/[0.02] dark:hover:bg-white/5"
          @click="assignReceiptToProject(r)"
        >
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ r.name }}</span>
            <span class="block truncate text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(r.date ?? r.created_at) }}<template v-if="r.category"> · {{ r.category }}</template></span>
          </span>
          <Badge v-if="r.finance_project_id != null" tone="warning">{{ projectName(r.finance_project_id) }}</Badge>
          <span class="shrink-0 font-mono text-sm tabular-nums">{{ r.amount != null ? money(Number(r.amount)) : '—' }}</span>
        </button>
        <p v-if="!assignPrjRcCandidates.length" class="px-3 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </div>
      <template #footer><Btn variant="ghost" @click="assignPrjRcDialog = false">{{ t('common.close') }}</Btn></template>
    </Modal>

    <!-- Project trash (soft-deleted projects; children keep their parent_id) -->
    <Modal v-model="prjTrashDialog" :title="t('invoices.project_trash')" width="560px">
      <div class="divide-y divide-[var(--ll-border)] rounded-lg border border-[var(--ll-border)]">
        <div v-for="p in trashedProjects" :key="p.id" class="flex items-center gap-3 px-3 py-2">
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ p.name }}</span>
            <span class="block text-xs text-[var(--ll-muted)] tabular-nums">{{ fmtDate(p.deleted_at) }}</span>
          </span>
          <Btn variant="ghost" size="sm" icon="restore_from_trash" :title="t('common.restore')" @click="restoreProject(p)" />
          <Btn variant="ghost" size="sm" icon="delete_forever" class="text-red-600 dark:text-red-400" :title="t('invoices.project_delete_forever')" @click="forceProject(p)" />
        </div>
        <p v-if="!trashedProjects.length" class="px-3 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('invoices.project_trash_empty') }}</p>
      </div>
      <template #footer><Btn variant="ghost" @click="prjTrashDialog = false">{{ t('common.close') }}</Btn></template>
    </Modal>
      </div>
    </div>

    <!-- ============ Off-screen invoice print sheet (rasterised to PDF) ============ -->
    <div
      v-if="printInv"
      id="spa-invoice-print"
      :class="{ 'has-inv-font': !!printCompany.font }"
      :style="[{ position: 'fixed', left: '-10000px', top: '0', width: '794px', background: '#fff', zIndex: '-1' }, printCompany.font ? { ['--inv-font']: printCompany.font } : {}]"
    >
      <!-- ---------- MODERN (accent band + cards) ---------- -->
      <div v-if="printTpl === 'modern'" style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:10.5px; line-height:1.5; color:#1f2937;">
        <div style="color:#fff; padding:22px 16mm 20px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px;" :style="'background:' + printCompany.accent">
          <div>
            <img v-if="printCompany.logo" :src="printCompany.logo" alt="" style="max-height:40px; margin-bottom:8px;">
            <div style="font-weight:800; font-size:16px; letter-spacing:-.01em;">{{ printCompany.name }}</div>
            <div style="opacity:.85; font-size:9.5px; margin-top:2px;">{{ [printCompany.address ? printCompany.address.replace(/\n/g, ' · ') : '', printCompany.email, printCompany.phone].filter(Boolean).join(' · ') }}</div>
          </div>
          <div style="text-align:right; white-space:nowrap;">
            <div style="font-size:26px; font-weight:800; letter-spacing:.02em; line-height:1; text-transform:uppercase;">{{ docTitle(printInv) }}</div>
            <div style="opacity:.9; margin-top:4px;" class="tabular-nums">{{ pl('invoice_number') + ' ' + (printInv.number || '—') }}</div>
          </div>
        </div>
        <div style="padding:22px 16mm 24px;">
          <div style="display:flex; gap:14px; align-items:stretch;">
            <div style="flex:1; background:#f5f6fb; border-radius:12px; padding:12px 14px;">
              <div style="font-size:8px; text-transform:uppercase; letter-spacing:.1em; font-weight:700;" :style="'color:' + printCompany.heading">{{ pl('bill_to') }}</div>
              <div style="font-weight:700; font-size:12px; margin-top:4px;">{{ printInv.customer?.name }}</div>
              <div v-show="printInv.customer?.attn" style="color:#4b5563;">{{ printInv.customer?.attn }}</div>
              <div style="color:#4b5563; white-space:pre-line;">{{ printInv.customer?.address }}</div>
              <div v-show="printInv.customer?.email" style="color:#4b5563;">{{ printInv.customer?.email }}</div>
              <div v-show="printInv.customer?.vatId" style="color:#4b5563;">{{ pl('vat_id_label') + ': ' + printInv.customer?.vatId }}</div>
            </div>
            <div style="width:200px; background:#f5f6fb; border-radius:12px; padding:12px 14px;">
              <div style="display:flex; justify-content:space-between; padding:2px 0;"><span :style="'color:' + printCompany.heading">{{ pl('invoice_date') }}</span><span class="tabular-nums" style="font-weight:600;">{{ printInv.issueDate }}</span></div>
              <div style="display:flex; justify-content:space-between; padding:2px 0;"><span :style="'color:' + printCompany.heading">{{ pl('due') }}</span><span class="tabular-nums" style="font-weight:600;">{{ printInv.dueDate }}</span></div>
              <div v-show="printCompany.vat_id" style="display:flex; justify-content:space-between; padding:2px 0;"><span :style="'color:' + printCompany.heading">{{ pl('vat_id_label') }}</span><span class="tabular-nums">{{ printCompany.vat_id }}</span></div>
            </div>
          </div>
          <table style="width:100%; margin-top:22px; border-collapse:collapse;">
            <thead><tr style="text-align:left; font-size:8.5px; text-transform:uppercase; letter-spacing:.07em; font-weight:700;" :style="'color:' + printCompany.heading + '; border-bottom:2px solid ' + printCompany.accent">
              <th style="padding:0 8px 8px 0;">{{ pl('line_desc') }}</th>
              <th style="padding:0 8px 8px; text-align:right;">{{ pl('line_qty') }}</th>
              <th style="padding:0 8px 8px; text-align:right;">{{ pl('line_price') }}</th>
              <th style="padding:0 0 8px 8px; text-align:right;">{{ pl('amount') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="(l, i) in printInv.lines" :key="i" style="border-bottom:1px solid #eef0f4;">
                <td style="padding:9px 8px 9px 0; font-weight:500; vertical-align:top; white-space:pre-line;">{{ l.desc }}</td>
                <td style="padding:9px 8px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ fmtQty(l.qty, printInv.lang) + (l.unit ? ' ' + l.unit : '') }}</td>
                <td style="padding:9px 8px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ pmoney(Number(l.unitPrice), printInv.currency, printInv.lang) }}</td>
                <td style="padding:9px 0 9px 8px; text-align:right; white-space:nowrap; font-weight:600; vertical-align:top;" class="tabular-nums">{{ pmoney(lineNet(l), printInv.currency, printInv.lang) }}</td>
              </tr>
            </tbody>
          </table>
          <div style="display:flex; justify-content:flex-end; margin-top:18px;">
            <div style="width:250px;">
              <div style="display:flex; justify-content:space-between; padding:3px 12px; color:#6b7280;"><span>{{ pl('subtotal') }}</span><span class="tabular-nums">{{ pmoney(hasDiscount(printInv) ? printTotals.grossNet : printTotals.net, printInv.currency, printInv.lang) }}</span></div>
              <div v-show="hasDiscount(printInv)" style="display:flex; justify-content:space-between; padding:3px 12px; color:#6b7280;"><span>{{ pl('discount') }}</span><span class="tabular-nums">{{ '−' + pmoney(Math.abs(printTotals.discount), printInv.currency, printInv.lang) }}</span></div>
              <div v-for="rate in printVatRates" :key="rate" style="display:flex; justify-content:space-between; padding:3px 12px; color:#6b7280;"><span>{{ pl('vat_at').replace(':rate', String(rate)) }}</span><span class="tabular-nums">{{ pmoney(printTotals.vatByRate[rate], printInv.currency, printInv.lang) }}</span></div>
              <div style="display:flex; justify-content:space-between; padding:10px 12px; margin-top:6px; color:#fff; border-radius:10px; font-weight:800; font-size:13px;" :style="'background:' + printCompany.accent"><span>{{ pl('gross') }}</span><span class="tabular-nums">{{ pmoney(printTotals.gross, printInv.currency, printInv.lang) }}</span></div>
              <div v-show="printCompany.small_business" style="margin-top:8px; font-size:10px; color:#6b7280;">{{ pl('vat_kleinunternehmer_note') }}</div>
            </div>
          </div>
          <div v-show="printInv.note" style="margin-top:20px;">
            <div style="font-size:8px; text-transform:uppercase; letter-spacing:.08em; font-weight:700;" :style="'color:' + printCompany.heading">{{ pl('notes_heading') }}</div>
            <div style="white-space:pre-line; color:#4b5563; margin-top:2px;">{{ printInv.note }}</div>
          </div>
          <div style="margin-top:28px; padding-top:12px; border-top:1px solid #eef0f4; display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; font-size:9px; color:#4b5563;">
            <div v-show="printCompany.payment_terms_text"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:8px;" :style="'color:' + printCompany.heading">{{ pl('payment_terms_heading') }}</div><div style="white-space:pre-line;">{{ printCompany.payment_terms_text }}</div></div>
            <div v-show="skontoDate(printInv)" style="margin-top:4px; font-weight:600;">{{ pl('skonto_note').replace(':percent', String(printInv.skontoPercent)).replace(':date', skontoDate(printInv)) }}</div>
            <div v-show="printCompany.payment_methods"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:8px;" :style="'color:' + printCompany.heading">{{ pl('payment_methods_heading') }}</div><div style="white-space:pre-line;">{{ printCompany.payment_methods }}</div></div>
            <div v-show="printCompany.bank_name || printCompany.iban"><div style="font-weight:700; text-transform:uppercase; letter-spacing:.06em; font-size:8px;" :style="'color:' + printCompany.heading">{{ pl('bank_details') }}</div><div>{{ [printCompany.bank_name, printCompany.iban ? 'IBAN ' + printCompany.iban : '', printCompany.bic ? 'BIC ' + printCompany.bic : ''].filter(Boolean).join(' · ') }}</div></div>
          </div>
          <div v-show="printQr" style="margin-top:10px; text-align:center;"><img :src="printQr" style="width:80px; height:80px;"><div style="font-size:8px; color:#8a8a8a; margin-top:2px;">{{ pl('giro_hint') }}</div></div>
          <div v-show="printInv.footer || printCompany.footer_text" style="margin-top:12px; text-align:center; font-size:9px; color:#6b7280; white-space:pre-line;">{{ printInv.footer || printCompany.footer_text }}</div>
        </div>
      </div>

      <!-- ---------- ELEGANT (serif + minimal) ---------- -->
      <div v-else-if="printTpl === 'elegant'" style="font-family:Georgia,'Times New Roman',serif; font-size:10.5px; line-height:1.55; color:#2b2b2b; padding:20mm;">
        <div style="display:flex; justify-content:space-between; align-items:baseline; border-bottom:1px solid #222; padding-bottom:10px;">
          <div style="font-size:16px; font-weight:700; letter-spacing:.01em;">{{ printCompany.name }}</div>
          <div style="font-size:17px; letter-spacing:.3em; text-transform:uppercase;" :style="'color:' + printCompany.accent">{{ docTitle(printInv) }}</div>
        </div>
        <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#777; font-size:8.5px; margin-top:6px; letter-spacing:.02em;">{{ [printCompany.address ? printCompany.address.replace(/\n/g, ' · ') : '', printCompany.email, printCompany.phone, printCompany.vat_id ? pl('vat_id_label') + ' ' + printCompany.vat_id : ''].filter(Boolean).join(' · ') }}</div>
        <div style="display:flex; justify-content:space-between; gap:24px; margin-top:26px;">
          <div>
            <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:8px; text-transform:uppercase; letter-spacing:.16em; color:#9a9a9a;">{{ pl('bill_to') }}</div>
            <div style="font-weight:700; font-size:12.5px; margin-top:3px;">{{ printInv.customer?.name }}</div>
            <div v-show="printInv.customer?.attn" style="color:#555;">{{ printInv.customer?.attn }}</div>
            <div style="color:#555; white-space:pre-line;">{{ printInv.customer?.address }}</div>
            <div v-show="printInv.customer?.email" style="color:#555;">{{ printInv.customer?.email }}</div>
            <div v-show="printInv.customer?.vatId" style="color:#555;">{{ pl('vat_id_label') + ' ' + printInv.customer?.vatId }}</div>
          </div>
          <table style="font-size:10px; border-collapse:collapse; height:fit-content;">
            <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:right; padding:1px 16px 1px 0; color:#9a9a9a; letter-spacing:.04em;">{{ pl('invoice_number') }}</td><td style="text-align:right; font-weight:700;" class="tabular-nums">{{ printInv.number || '—' }}</td></tr>
            <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:right; padding:1px 16px 1px 0; color:#9a9a9a; letter-spacing:.04em;">{{ pl('invoice_date') }}</td><td style="text-align:right;" class="tabular-nums">{{ printInv.issueDate }}</td></tr>
            <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:right; padding:1px 16px 1px 0; color:#9a9a9a; letter-spacing:.04em;">{{ pl('due') }}</td><td style="text-align:right;" class="tabular-nums">{{ printInv.dueDate }}</td></tr>
          </table>
        </div>
        <table style="width:100%; margin-top:28px; border-collapse:collapse;">
          <thead><tr style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; text-align:left; font-size:8px; text-transform:uppercase; letter-spacing:.14em; color:#9a9a9a; border-bottom:1px solid #cfcfcf;">
            <th style="padding:0 6px 7px 0; font-weight:600;">{{ pl('line_desc') }}</th>
            <th style="padding:0 6px 7px; text-align:right; font-weight:600;">{{ pl('line_qty') }}</th>
            <th style="padding:0 6px 7px; text-align:right; font-weight:600;">{{ pl('line_price') }}</th>
            <th style="padding:0 0 7px 6px; text-align:right; font-weight:600;">{{ pl('amount') }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="(l, i) in printInv.lines" :key="i" style="border-bottom:1px solid #ededed;">
              <td style="padding:9px 6px 9px 0; vertical-align:top; white-space:pre-line;">{{ l.desc }}</td>
              <td style="padding:9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ fmtQty(l.qty, printInv.lang) + (l.unit ? ' ' + l.unit : '') }}</td>
              <td style="padding:9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ pmoney(Number(l.unitPrice), printInv.currency, printInv.lang) }}</td>
              <td style="padding:9px 0 9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ pmoney(lineNet(l), printInv.currency, printInv.lang) }}</td>
            </tr>
          </tbody>
        </table>
        <div style="display:flex; justify-content:flex-end; margin-top:18px;">
          <table style="min-width:250px; border-collapse:collapse;">
            <tr><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding:3px 6px; color:#777;">{{ pl('subtotal') }}</td><td style="padding:3px 0 3px 6px; text-align:right;" class="tabular-nums">{{ pmoney(hasDiscount(printInv) ? printTotals.grossNet : printTotals.net, printInv.currency, printInv.lang) }}</td></tr>
            <tr v-show="hasDiscount(printInv)"><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding:3px 6px; color:#777;">{{ pl('discount') }}</td><td style="padding:3px 0 3px 6px; text-align:right;" class="tabular-nums">{{ '−' + pmoney(Math.abs(printTotals.discount), printInv.currency, printInv.lang) }}</td></tr>
            <tr v-for="rate in printVatRates" :key="rate"><td style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; padding:3px 6px; color:#777;">{{ pl('vat_at').replace(':rate', String(rate)) }}</td><td style="padding:3px 0 3px 6px; text-align:right;" class="tabular-nums">{{ pmoney(printTotals.vatByRate[rate], printInv.currency, printInv.lang) }}</td></tr>
            <tr style="border-top:1px solid #222;"><td style="padding:7px 6px; letter-spacing:.1em; text-transform:uppercase;" :style="'color:' + printCompany.accent">{{ pl('gross') }}</td><td style="padding:7px 0 7px 6px; text-align:right; font-weight:700; font-size:13px;" :style="'color:' + printCompany.accent" class="tabular-nums">{{ pmoney(printTotals.gross, printInv.currency, printInv.lang) }}</td></tr>
            <tr v-show="printCompany.small_business"><td colspan="2" style="padding:6px 6px 0; font-size:10px; color:#6b7280;">{{ pl('vat_kleinunternehmer_note') }}</td></tr>
          </table>
        </div>
        <div v-show="printInv.note || printInv.footer || printCompany.footer_text" style="margin-top:34px; text-align:center; font-style:italic; color:#555; white-space:pre-line;">{{ printInv.note || printInv.footer || printCompany.footer_text }}</div>
        <div v-show="printQr" style="margin-top:16px; text-align:center;"><img :src="printQr" style="width:82px; height:82px;"><div style="font-size:8px; color:#8a8a8a; margin-top:2px;">{{ pl('giro_hint') }}</div></div>
        <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; margin-top:20px; padding-top:10px; border-top:1px solid #ededed; text-align:center; font-size:8.5px; color:#8a8a8a; letter-spacing:.02em;">{{ [printCompany.payment_terms_text, printCompany.payment_methods, printCompany.bank_name, printCompany.iban ? 'IBAN ' + printCompany.iban : '', printCompany.bic ? 'BIC ' + printCompany.bic : ''].filter(Boolean).join(' · ') }}</div>
      </div>

      <!-- ---------- EDITORIAL (single-ink, accent rule) ---------- -->
      <div v-else-if="printTpl === 'editorial'" class="ie" :style="{ '--ac': printCompany.accent }">
        <div class="ie-page">
          <div class="ie-header">
            <div class="ie-brand">
              <div v-if="printCompany.logo" class="ie-logo"><img :src="printCompany.logo" alt=""></div>
              <div v-else class="ie-brand-text"><div class="ie-co-name">{{ printCompany.name }}</div></div>
            </div>
            <div class="ie-doc-meta">
              <div class="ie-doc-kind">{{ docTitle(printInv) }}</div>
              <div class="ie-doc-no num">{{ printInv.number || '—' }}</div>
            </div>
          </div>
          <div class="ie-meta-grid">
            <div class="ie-meta-cell"><div class="ie-m-lbl">{{ pl('invoice_date') }}</div><div class="ie-m-val num">{{ printInv.issueDate }}</div></div>
            <div class="ie-meta-cell"><div class="ie-m-lbl">{{ pl('due') }}</div><div class="ie-m-val num">{{ printInv.dueDate }}</div></div>
            <div class="ie-meta-cell"><div class="ie-m-lbl">{{ pl('status_label') }}</div><div class="ie-m-val"><span class="ie-pill" :class="'ie-' + printInv.status">{{ statusLabelP(printInv.status) }}</span></div></div>
          </div>
          <div class="ie-parties">
            <div class="ie-party">
              <div class="ie-p-lbl">{{ pl('invoice_from') }}</div>
              <div class="ie-p-name">{{ printCompany.name }}</div>
              <div v-for="(ln, i) in [...(printCompany.address || '').split('\n'), [printCompany.email, printCompany.phone].filter(Boolean).join(' · '), printCompany.vat_id ? pl('vat_id_label') + ' ' + printCompany.vat_id : ''].filter(Boolean)" :key="i" class="ie-p-line">{{ ln }}</div>
            </div>
            <div class="ie-party">
              <div class="ie-p-lbl">{{ pl('bill_to') }}</div>
              <div class="ie-p-name">{{ printInv.customer?.name }}</div>
              <div v-for="(ln, i) in [printInv.customer?.attn, ...((printInv.customer?.address || '').split('\n')), printInv.customer?.email, printInv.customer?.vatId ? pl('vat_id_label') + ' ' + printInv.customer.vatId : ''].filter(Boolean)" :key="i" class="ie-p-line">{{ ln }}</div>
            </div>
          </div>
          <div class="ie-tbl-wrap">
            <table>
              <thead><tr>
                <th>{{ pl('line_desc') }}</th>
                <th class="r">{{ pl('line_qty') }}</th>
                <th class="r">{{ pl('line_price') }}</th>
                <th class="r">{{ pl('amount') }}</th>
              </tr></thead>
              <tbody>
                <tr v-for="(l, i) in printInv.lines" :key="i">
                  <td><div class="ie-d-title" style="white-space:pre-line;">{{ l.desc }}</div></td>
                  <td class="r num">{{ fmtQty(l.qty, printInv.lang) + (l.unit ? ' ' + l.unit : '') }}</td>
                  <td class="r num">{{ pmoney(Number(l.unitPrice), printInv.currency, printInv.lang) }}</td>
                  <td class="r num ie-amt">{{ pmoney(lineNet(l), printInv.currency, printInv.lang) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="ie-sum-area"><div class="ie-sum">
            <div class="ie-sr"><span class="l">{{ pl('subtotal') }}</span><span class="v num">{{ pmoney(hasDiscount(printInv) ? printTotals.grossNet : printTotals.net, printInv.currency, printInv.lang) }}</span></div>
            <div v-show="hasDiscount(printInv)" class="ie-sr"><span class="l">{{ pl('discount') }}</span><span class="v num">{{ '−' + pmoney(Math.abs(printTotals.discount), printInv.currency, printInv.lang) }}</span></div>
            <div v-for="rate in printVatRates" :key="rate" class="ie-sr"><span class="l">{{ pl('vat_at').replace(':rate', String(rate)) }}</span><span class="v num">{{ pmoney(printTotals.vatByRate[rate], printInv.currency, printInv.lang) }}</span></div>
            <div class="ie-grand"><span class="ie-gl">{{ pl('gross') }}</span><span class="ie-gv num">{{ pmoney(printTotals.gross, printInv.currency, printInv.lang) }}</span></div>
            <div v-show="printCompany.small_business" style="margin-top:8px; font-size:10px; color:#6b7280;">{{ pl('vat_kleinunternehmer_note') }}</div>
          </div></div>
          <div v-show="printInv.footer || printCompany.footer_text" class="ie-notice">{{ printInv.footer || printCompany.footer_text }}</div>
          <div v-show="printInv.note" class="ie-notes-area">
            <div class="ie-n-lbl">{{ pl('notes_heading') }}</div>
            <div class="ie-note-text">{{ printInv.note }}</div>
          </div>
          <div v-show="printCompany.payment_terms_text || printCompany.payment_methods || printCompany.bank_name || printCompany.iban" class="ie-pay-area">
            <div class="ie-pay-grid">
              <div v-show="printCompany.payment_terms_text"><div class="ie-pc-lbl">{{ pl('payment_terms_heading') }}</div><div class="ie-pc-val">{{ printCompany.payment_terms_text }}</div></div>
              <div v-show="skontoDate(printInv)" class="ie-pc-val" style="font-weight:600;">{{ pl('skonto_note').replace(':percent', String(printInv.skontoPercent)).replace(':date', skontoDate(printInv)) }}</div>
              <div v-show="printCompany.payment_methods"><div class="ie-pc-lbl">{{ pl('payment_methods_heading') }}</div><div class="ie-pc-val">{{ printCompany.payment_methods }}</div></div>
              <div v-show="printCompany.bank_name || printCompany.iban"><div class="ie-pc-lbl">{{ pl('bank_details') }}</div><div class="ie-pc-val"><span>{{ printCompany.bank_name }}</span><span v-if="printCompany.iban"><br v-show="printCompany.bank_name">IBAN: <span>{{ printCompany.iban }}</span></span><span v-if="printCompany.bic"><br>BIC: <span>{{ printCompany.bic }}</span></span></div></div>
              <div v-show="printQr"><img :src="printQr" style="width:78px; height:78px;"><div class="ie-pc-lbl" style="margin-top:2px;">{{ pl('giro_hint') }}</div></div>
            </div>
          </div>
        </div>
        <div class="ie-foot"><strong>{{ printCompany.name }}</strong><span>{{ [printCompany.address ? ' · ' + printCompany.address.replace(/\n/g, ', ') : '', printCompany.email ? ' · ' + printCompany.email : '', printCompany.phone ? ' · ' + printCompany.phone : ''].join('') }}</span></div>
      </div>

      <!-- ---------- KLASSISCH (traditional German business sheet) ---------- -->
      <div v-else-if="printTpl === 'klassisch'" style="font-family:Arial,'Helvetica Neue',Helvetica,sans-serif; font-size:10.5px; line-height:1.5; color:#222; padding:20mm 20mm 12mm 25mm;">
        <table style="width:100%; border-collapse:collapse;"><tbody><tr><td style="padding:0; vertical-align:top;">
          <div style="min-height:56px; text-align:right; margin-bottom:30px;">
            <img v-if="printCompany.logo" :src="printCompany.logo" alt="" style="max-height:60px; display:inline-block;">
            <div v-else style="font-size:26px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#2c3542;">{{ printCompany.name }}</div>
          </div>

          <div style="font-size:8.5px; color:#333; border-bottom:1px solid #222; padding-bottom:2px; display:inline-block;">{{ [printCompany.name, printCompany.address ? printCompany.address.replace(/\n/g, ', ') : ''].filter(Boolean).join(', ') }}</div>

          <div style="margin-top:8px; line-height:1.55;">
            <div>{{ printInv.customer?.name }}</div>
            <div v-show="printInv.customer?.attn">{{ printInv.customer?.attn }}</div>
            <div style="white-space:pre-line;">{{ printInv.customer?.address }}</div>
          </div>

          <div style="display:flex; justify-content:flex-end; margin-top:44px;">
            <div style="width:270px;">
              <div style="font-size:15px; font-weight:700; border-bottom:1px solid #222; padding-bottom:3px; margin-bottom:5px;">{{ docTitle(printInv) }}</div>
              <table style="width:100%; border-collapse:collapse; font-size:10px;">
                <tr><td style="padding:1px 0; color:#333;">{{ pl('invoice_number') + ':' }}</td><td style="padding:1px 0; text-align:right;" class="tabular-nums">{{ printInv.number || '—' }}</td></tr>
                <tr v-show="printInv.customer?.vatId"><td style="padding:1px 0; color:#333;">{{ pl('vat_id_label') + ':' }}</td><td style="padding:1px 0; text-align:right;">{{ printInv.customer?.vatId }}</td></tr>
                <tr><td style="padding:1px 0; color:#333;">{{ pl('invoice_date') + ':' }}</td><td style="padding:1px 0; text-align:right;" class="tabular-nums">{{ printInv.issueDate }}</td></tr>
                <tr><td style="padding:1px 0; color:#333;">{{ pl('due') + ':' }}</td><td style="padding:1px 0; text-align:right;" class="tabular-nums">{{ printInv.dueDate }}</td></tr>
              </table>
            </div>
          </div>

          <table style="width:100%; margin-top:34px; border-collapse:collapse; font-size:10px;">
            <thead><tr style="background:#ededed; text-align:left;">
              <th style="border:1px solid #cfcfcf; padding:6px 7px; font-weight:600;">{{ pl('line_desc') }}</th>
              <th style="border:1px solid #cfcfcf; padding:6px 7px; font-weight:600; text-align:right; white-space:nowrap;">{{ pl('line_qty') }}</th>
              <th style="border:1px solid #cfcfcf; padding:6px 7px; font-weight:600; white-space:nowrap;">{{ pl('line_unit') }}</th>
              <th style="border:1px solid #cfcfcf; padding:6px 7px; font-weight:600; text-align:right; white-space:nowrap;">{{ pl('line_price') }}</th>
              <th style="border:1px solid #cfcfcf; padding:6px 7px; font-weight:600; text-align:right; white-space:nowrap;">{{ pl('amount') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="(l, i) in printInv.lines" :key="i">
                <td style="border:1px solid #cfcfcf; padding:6px 7px; vertical-align:top; white-space:pre-line;">{{ l.desc }}</td>
                <td style="border:1px solid #cfcfcf; padding:6px 7px; text-align:right; vertical-align:top; white-space:nowrap;" class="tabular-nums">{{ fmtQty(l.qty, printInv.lang) }}</td>
                <td style="border:1px solid #cfcfcf; padding:6px 7px; vertical-align:top; white-space:nowrap;">{{ l.unit || '' }}</td>
                <td style="border:1px solid #cfcfcf; padding:6px 7px; text-align:right; vertical-align:top; white-space:nowrap;" class="tabular-nums">{{ pmoney(Number(l.unitPrice), printInv.currency, printInv.lang) }}</td>
                <td style="border:1px solid #cfcfcf; padding:6px 7px; text-align:right; vertical-align:top; white-space:nowrap;" class="tabular-nums">{{ pmoney(lineNet(l), printInv.currency, printInv.lang) }}</td>
              </tr>
            </tbody>
          </table>

          <div style="display:flex; justify-content:flex-end; margin-top:14px;">
            <div style="width:300px; font-size:10px;">
              <div style="display:flex; justify-content:space-between; padding:2px 0; color:#333;"><span>{{ pl('subtotal') }}</span><span class="tabular-nums">{{ pmoney(printTotals.subtotal, printInv.currency, printInv.lang) }}</span></div>
              <div v-show="printTotals.discountAmount > 0" style="display:flex; justify-content:space-between; padding:2px 0; color:#333;"><span>{{ pl('discount') }}</span><span class="tabular-nums">{{ '−' + pmoney(printTotals.discountAmount, printInv.currency, printInv.lang) }}</span></div>
              <div v-for="rate in printVatRates" :key="rate" style="display:flex; justify-content:space-between; padding:2px 0; color:#333;"><span>{{ pl('vat_at').replace(':rate', String(rate)) }}</span><span class="tabular-nums">{{ pmoney(printTotals.vatByRate[rate], printInv.currency, printInv.lang) }}</span></div>
              <div style="display:flex; justify-content:space-between; padding:6px 0 2px; margin-top:2px; font-weight:700; font-size:12.5px;"><span>{{ pl('payable') }}</span><span class="tabular-nums">{{ pmoney(printTotals.gross, printInv.currency, printInv.lang) }}</span></div>
            </div>
          </div>

          <div style="margin-top:30px; color:#333; line-height:1.55;">
            <div v-if="printInv.note" style="white-space:pre-line;">{{ printInv.note }}</div>
            <div v-else>
              <div>{{ pl('thanks_line') }}</div>
              <div>{{ pl('pay_until_line').replace(':date', printInv.dueDate || '') }}</div>
            </div>
            <div v-show="printInv.footer || printCompany.footer_text" style="margin-top:8px; white-space:pre-line;">{{ printInv.footer || printCompany.footer_text }}</div>
          </div>

          <div v-show="printQr" style="margin-top:20px;"><img :src="printQr" style="width:84px; height:84px;"><div style="font-size:8px; color:#666; margin-top:2px;">{{ pl('giro_hint') }}</div></div>
        </td></tr></tbody>
        <tfoot><tr><td style="height:24mm; padding-top:16px; vertical-align:bottom;">
          <table class="inv-foot" style="width:100%; border-top:1px solid #cfcfcf; border-collapse:collapse; font-size:8px; color:#555; line-height:1.5;">
            <tr style="vertical-align:top;">
              <td style="width:34%; padding:8px 8px 0 0;">
                <div style="font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#374151;">{{ printCompany.name }}</div>
                <div v-show="printCompany.address" style="white-space:pre-line;">{{ printCompany.address }}</div>
                <div v-show="printCompany.vat_id">{{ pl('vat_id_label') + ' ' + printCompany.vat_id }}</div>
              </td>
              <td style="width:33%; padding:8px 8px 0;">
                <div v-show="printCompany.website">{{ printCompany.website }}</div>
                <div v-show="printCompany.email">{{ printCompany.email }}</div>
                <div v-show="printCompany.phone">{{ printCompany.phone }}</div>
                <div v-for="(c, ci) in (printCompany.contacts || [])" :key="ci">{{ [c.name, c.role].filter(Boolean).join(' · ') }}</div>
              </td>
              <td v-show="printCompany.bank_name || printCompany.iban" style="width:33%; padding:8px 0 0 8px;">
                <div v-show="printCompany.iban">{{ 'IBAN: ' + printCompany.iban }}</div>
                <div v-show="printCompany.bic">{{ 'BIC: ' + printCompany.bic }}</div>
                <div v-show="printCompany.bank_name">{{ 'Bank: ' + printCompany.bank_name }}</div>
              </td>
            </tr>
          </table>
        </td></tr></tfoot></table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue';
import { fmtDate as libDate, todayYmd } from '@spa/lib/datetime';
import { fmtMoney, moneyInput, moneyLocale, parseMoney } from '@spa/lib/money';
import { safeHref } from '@spa/lib/url';
import { useRoute, useRouter } from 'vue-router';
import { trans as t, getActiveLanguage } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal, Chart, SortLabel, Pager, SectionNav, type SectionNavItem } from '@spa/ui';
import type { AlignedData, Options } from 'uplot';
import { useFinanceStore, type Invoice, type InvoiceLine, type Partner, type PaymentMethod, type Project, type Receipt, type BankTransaction, type FinanceCategory, type DuplicateGroup, type CategorySuggestion, type NumberGapGroup, type ReceiptMatchGroup, type SplitPaymentGroup, type ReceiptDuplicate } from '@spa/stores/finance';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { api, ApiError, VersionConflict } from '@spa/api/client';
import {
  projectTree, descendantIds, normaliseLedger, ledgerTotals, ownTotals, rolledTotals,
  blankExpense, type ProjectExpense, type Totals,
} from '@spa/shared/finance-projects';
import { analyzeReceiptText, buildReceiptName } from '@spa/shared/receipt-ocr';
import { autoPick, suggestBookings, type BookingSuggestion } from '@spa/shared/receipt-match';
import { fileSig } from '@spa/shared/file-sig';
import { matchInvoices, type InvoiceMatch } from '@spa/shared/invoice-match';
import { parseBankCsv } from '@spa/shared/bank-csv';
import {
  type PrintInvoice, type PrintCompany, type PrintLine,
  computeTotals as printComputeTotals, vatRatesOf as printVatRatesOf,
  lineNet, fmtMoney as pmoney, fmtQty, hasDiscount, skontoDate,
  epcQrDataUrl, renderInvoicePdfBlob, ensureInvoiceFonts,
} from '@spa/shared/invoice-print';

const f = useFinanceStore();
const { success, error } = useToast();
const route = useRoute();
const router = useRouter();
const VALID = ['dashboard', 'documents', 'invoices', 'payments', 'bank', 'receipts', 'projects', 'partners', 'stats'];
const requestedSection = computed(() => String(route.params.section || 'dashboard'));
const tab = computed(() => {
  const s = requestedSection.value;
  // 'stats' merged into 'dashboard' (one consolidated overview) — kept as an alias
  // so a bookmarked/shared `/finance/stats` link still lands somewhere sensible.
  if (s === 'stats') return 'dashboard';
  // The former separate document lists stay addressable, but deliberately land in
  // the shared review queue with the applicable direction preselected.
  if (s === 'invoices' || s === 'receipts') return 'documents';
  return VALID.includes(s) ? s : 'dashboard';
});
function go(v: unknown) { router.push(`/finance/${String(v)}`); }

// In-page left submenu sections (mirrors the Profile/Settings hub layout).
const sections = ['dashboard', 'documents', 'payments', 'bank', 'projects', 'partners'] as const;
const secIcon: Record<string, string> = {
  dashboard: 'space_dashboard', documents: 'inbox', invoices: 'receipt_long', payments: 'account_balance_wallet',
  bank: 'account_balance', receipts: 'receipt', projects: 'account_tree', partners: 'groups',
};
const financeNavGroups = computed(() => [{
  id: 'finance',
  items: sections.map((id) => ({ id, icon: secIcon[id], label: t('invoices.tab_' + id) })),
}]);
function isFinanceSectionActive(item: SectionNavItem): boolean { return tab.value === item.id; }

const kpis = ref<{ year: number; net: number; count: number; growthPct: number | null } | null>(null);
const openGross = ref(0);
const vatPayable = ref(0);

// Report state (stats tab)
const years = ref<number[]>([]);
const statsYear = ref<number>(new Date().getFullYear());
const months = ref<{ month: number; net: number }[]>([]);
const customers = ref<{ name: string; net: number; gross: number; count: number }[]>([]);
const agingBuckets = ref<Record<string, { count: number; gross: number }>>({});
const vatAdv = ref<{ outputVat?: number; inputVat?: number; payable?: number } | null>(null);
const euerData = ref<{ income?: { total?: number }; expenses?: { total?: number }; profit?: number } | null>(null);

const invDialog = ref(false);
const draft = ref<Partial<Invoice> | null>(null);
const lines = ref<InvoiceLine[]>([]);
const custName_ = ref('');
const custAttn_ = ref('');
const custEmail_ = ref('');
const custVatId_ = ref('');
const custAddress_ = ref('');
const discountType_ = computed<string>({
  get: () => draft.value?.discount_type ?? '',
  set: (v) => {
    if (!draft.value) return;
    draft.value.discount_type = (v || null) as 'percent' | 'amount' | null;
    if (!v) draft.value.discount_value = null;
  },
});
// Prefill customer + link from a business partner.
function applyPartnerToInvoice(pid: number | null) {
  if (!draft.value) return;
  draft.value.partner_id = pid;
  if (!pid) return;
  const p = f.partners.find((x) => x.id === pid);
  if (!p) return;
  if (!custName_.value) custName_.value = p.name;
  if (!custAddress_.value && p.address) custAddress_.value = p.address;
  if (!custEmail_.value && (p.invoice_email || p.email)) custEmail_.value = p.invoice_email || p.email || '';
  if (!custVatId_.value && p.vat_id) custVatId_.value = p.vat_id;
}
function loadCustomerFields(c: Record<string, unknown> | null | undefined) {
  const o = (c ?? {}) as Record<string, unknown>;
  custName_.value = typeof o.name === 'string' ? o.name : '';
  custAttn_.value = typeof o.attn === 'string' ? o.attn : '';
  custEmail_.value = typeof o.email === 'string' ? o.email : '';
  custVatId_.value = typeof o.vatId === 'string' ? o.vatId : '';
  custAddress_.value = typeof o.address === 'string' ? o.address : '';
}
const saving = ref(false);

const pDialog = ref(false);
interface PPForm {
  id?: number; version?: number; name: string; type?: string;
  holder?: string | null; business?: boolean; url?: string | null; note?: string | null;
  iban?: string | null; bic?: string | null; bank?: string | null; account_no?: string | null;
  card_number?: string | null; card_network?: string | null; card_expiry?: string | null; paypal_email?: string | null;
}
const pForm = reactive<PPForm>({ name: '', type: 'bank', business: false });

// Receipt form
const rDialog = ref(false);
const rFile = ref<File | File[] | null>(null);
const ocrBusy = ref(false);
const lastOcrText = ref('');
// ---- Sortable-table helper (all finance tables): a shared comparator +
// click-to-sort toggle, used by every sortable <thead> — clicking a column
// header sorts by it (desc first; a second click on the same column flips to
// asc). Invoices/receipts default to their own document date desc (issue_date
// / recognised receipt date, not upload created_at — a batch of invoices or
// scanned receipts uploaded on the same day would otherwise all cluster on
// that one day instead of sorting by when they actually happened).
type SortDir = 'asc' | 'desc';
function sortCmp(a: unknown, b: unknown): number {
  if (a == null && b == null) return 0;
  if (a == null) return -1;
  if (b == null) return 1;
  if (typeof a === 'number' && typeof b === 'number') return a - b;
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}
function toggleSort(state: { key: string; dir: SortDir }, key: string) {
  if (state.key === key) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
  else { state.key = key; state.dir = 'desc'; }
}

// The receipts table + its edit dialog used to be two disconnected surfaces — a
const rWorkspace = ref(false);
const rWorkspaceMime = computed(() => f.standaloneReceipts.find((x) => x.id === rForm.id)?.mime ?? null);
const rWorkspaceIsImage = computed(() => !!rWorkspaceMime.value && rWorkspaceMime.value.startsWith('image/'));
const rWorkspaceIsPdf = computed(() => rWorkspaceMime.value === 'application/pdf');
function openReceiptWorkspace(r: Receipt) { editReceipt(r); rWorkspace.value = true; }
interface RForm {
  id?: number; version?: number; name: string; category: string; tags: string[]; vat: string; note: string; partner_id: number | null;
  amount: string; currency: string; date: string; order_ref: string; doc_number: string; bank_transaction_id: number | null;
  linked_transaction_ids: number[] | null; finance_project_id: number | null;
}
const rForm = reactive<RForm>({
  name: '', category: '', tags: [], vat: '', note: '', partner_id: null,
  amount: '', currency: '', date: '', order_ref: '', doc_number: '', bank_transaction_id: null, linked_transaction_ids: null,
  finance_project_id: null,
});
const CURRENCY_OPTIONS = [
  { title: '—', value: '' }, { title: 'EUR', value: 'EUR' }, { title: 'USD', value: 'USD' },
  { title: 'GBP', value: 'GBP' }, { title: 'CHF', value: 'CHF' },
];

// ---- Assign-a-booking picker (manual fallback when nothing auto-matched) ----
const assignTxDialog = ref(false);
const assignTxQuery = ref('');
function openAssignTx() { assignTxQuery.value = ''; assignTxDialog.value = true; }
function pickAssignTx(id: number) { rForm.bank_transaction_id = id; rForm.linked_transaction_ids = null; assignTxDialog.value = false; }
function unassignTx() { rForm.bank_transaction_id = null; }
function unassignSplitTx() { rForm.linked_transaction_ids = null; }

// Project form
const prjDialog = ref(false);
interface PrjForm { id?: number; version?: number; name: string; parent_id: number | null; kind: 'business' | 'private'; note: string }
const prjForm = reactive<PrjForm>({ name: '', parent_id: null, kind: 'business', note: '' });

onMounted(async () => { await f.load(); await loadReports(); void loadPrintCompany(); });

async function loadReports(year?: number) {
  try {
    const r = await f.reports(year) as {
      kpis?: typeof kpis.value; aging?: { openGross?: number; buckets?: Record<string, { count: number; gross: number }> };
      currentVat?: { payable?: number }; years?: number[]; year?: number;
      months?: { month: number; net: number }[]; customers?: { name: string; net: number; gross: number; count: number }[];
    };
    kpis.value = r.kpis ?? null;
    openGross.value = r.aging?.openGross ?? 0;
    agingBuckets.value = r.aging?.buckets ?? {};
    years.value = r.years ?? [];
    statsYear.value = r.year ?? statsYear.value;
    months.value = r.months ?? [];
    customers.value = r.customers ?? [];
    const va = await f.vatAdvance(year) as typeof vatAdv.value;
    vatAdv.value = va;
    if (!year) vatPayable.value = va?.payable ?? r.currentVat?.payable ?? 0;
    euerData.value = await f.euer(year) as typeof euerData.value;
    const dup = await f.duplicates();
    dupInvoices.value = dup.invoices ?? [];
    dupTransactions.value = dup.transactions ?? [];
    catSuggestions.value = (await f.categorySuggestions()).suggestions ?? [];
    numberGapGroups.value = (await f.numberGaps()).groups ?? [];
  } catch { /* ignore */ }
}
// Duplicate + category-suggestion + number-gap read-outs (stats tab).
const dupInvoices = ref<DuplicateGroup[]>([]);
const dupTransactions = ref<DuplicateGroup[]>([]);
const catSuggestions = ref<CategorySuggestion[]>([]);
const numberGapGroups = ref<NumberGapGroup[]>([]);
function gapGroupLabel(group: string) { return group.startsWith('year:') ? group.slice(5) : t('invoices.gap_sequence'); }
function invoiceLabel(id: number) { const i = f.invoices.find((x) => x.id === id); return i?.number || `#${id}`; }
function txLabel(id: number) { const x = f.transactions.find((tx) => tx.id === id); return x ? `${x.date ?? ''} ${money(x.amount)}`.trim() : `#${id}`; }
function openInvoiceById(id: number) { const i = f.invoices.find((x) => x.id === id); if (i) { go('invoices'); editInvoice(i); } }
function onStatsYear(v: unknown) { void loadReports(Number(v)); }

function money(n: number) { return fmtMoney(n); }
// "0,00" under a German UI, "0.00" under an English one — a placeholder that
// shows the wrong separator is how the field teaches the wrong input.
const moneyPlaceholder = computed(() => (moneyLocale().startsWith('en') ? '0.00' : '0,00'));
function fmtDate(s?: string | null) { return s ? libDate(String(s).slice(0, 10)) : '—'; }
function statusTone(s: string): 'success' | 'info' | 'warning' | 'gray' { return s === 'paid' ? 'success' : s === 'sent' ? 'info' : s === 'final' ? 'warning' : 'gray'; }
// A numbered invoice can never revert to draft (GoBD; the server also blocks it).
const statusOptions = computed(() => {
  const opts = draft.value?.number
    ? []
    : [{ title: t('invoices.status_draft'), value: 'draft' }];
  return [
    ...opts,
    { title: t('invoices.status_final'), value: 'final' },
    { title: t('invoices.status_sent'), value: 'sent' },
    { title: t('invoices.status_paid'), value: 'paid' },
  ];
});
function custName(i: Invoice) { const c = i.customer as { name?: string } | null; return c?.name ?? '—'; }
function agingGross(k: string) { return Number(agingBuckets.value[k]?.gross ?? 0); }
function monthLabel(m: number) { return new Intl.DateTimeFormat(document.documentElement.lang || 'de', { month: 'short' }).format(new Date(2000, m - 1, 1)); }
const monthMax = computed(() => months.value.reduce((mx, m) => Math.max(mx, m.net || 0), 0));
function monthPct(net: number) { return monthMax.value > 0 ? Math.round(((net || 0) / monthMax.value) * 100) : 0; }

// ---- Dashboard charts (uPlot, lazy-loaded — see ui/Chart.vue) ----
const CHART_INK = '#6d4aff'; // --color-primary-500
const monthlyChartData = computed<AlignedData>(() => [
  months.value.map((m) => m.month),
  months.value.map((m) => Number(m.net ?? 0)),
]);
const monthlyChartOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [
    {},
    { label: t('invoices.stat_monthly'), stroke: CHART_INK, fill: CHART_INK + '33' },
  ],
  axes: [
    { stroke: '#625d69', font: '600 12px ui-monospace, SFMono-Regular, Menlo, monospace', grid: { show: false }, values: (_u, vals) => vals.map((v) => monthLabel(v)) },
    { stroke: '#625d69', font: '600 12px ui-monospace, SFMono-Regular, Menlo, monospace', grid: { stroke: 'rgba(128,128,128,.24)' }, size: 96, values: (_u, vals) => vals.map((v) => money(v)) },
  ],
  scales: { x: { time: false } },
}));

// Category breakdown (from standalone receipts — the source that actually carries a
// category) for the currently selected year, summed by absolute gross amount.
const categoryBreakdown = computed(() => {
  const byCat = new Map<string, number>();
  for (const r of f.standaloneReceipts) {
    if (!r.category || r.amount == null) continue;
    if (r.date && new Date(r.date).getFullYear() !== statsYear.value) continue;
    byCat.set(r.category, (byCat.get(r.category) ?? 0) + Math.abs(Number(r.amount)));
  }
  return [...byCat.entries()].sort((a, b) => b[1] - a[1]).slice(0, 10);
});
const categoryChartData = computed<AlignedData>(() => [
  categoryBreakdown.value.map((_, i) => i),
  categoryBreakdown.value.map(([, sum]) => sum),
]);
const categoryChartOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [
    {},
    { label: t('invoices.receipt_category'), stroke: CHART_INK, fill: CHART_INK + '33' },
  ],
  axes: [
    { stroke: '#625d69', font: '600 12px ui-monospace, SFMono-Regular, Menlo, monospace', grid: { show: false }, values: (_u, vals) => vals.map((v) => categoryBreakdown.value[v]?.[0] ?? '') },
    { stroke: '#625d69', font: '600 12px ui-monospace, SFMono-Regular, Menlo, monospace', grid: { stroke: 'rgba(128,128,128,.24)' }, size: 96, values: (_u, vals) => vals.map((v) => money(v)) },
  ],
  scales: { x: { time: false } },
}));

type FinanceDocument = { key: string; kind: 'invoice' | 'receipt'; direction: 'income' | 'expense'; date: string | null; partner: string; reference: string; amount: number; matched: boolean; item: Invoice | Receipt };
const documentQuery = ref('');
const documentDirectionFilter = ref<'all' | FinanceDocument['direction']>('all');
const documentMatchFilter = ref<'all' | 'matched' | 'unmatched'>('all');
const documentFrom = ref('');
const documentTo = ref('');
const documentSort = reactive<{ key: string; dir: SortDir }>({ key: 'date', dir: 'desc' });
const documentDirectionOptions = computed(() => [
  { title: t('invoices.document_all'), value: 'all' },
  { title: t('invoices.document_income'), value: 'income' },
  { title: t('invoices.document_expense'), value: 'expense' },
]);
const documentMatchOptions = computed(() => [
  { title: t('invoices.document_matching_all'), value: 'all' },
  { title: t('invoices.document_matched'), value: 'matched' },
  { title: t('invoices.document_unmatched'), value: 'unmatched' },
]);
const documentDirection = computed<FinanceDocument['direction'] | null>(() => {
  if (requestedSection.value === 'invoices') return 'income';
  if (requestedSection.value === 'receipts') return 'expense';
  return documentDirectionFilter.value === 'all' ? null : documentDirectionFilter.value;
});
const documentFiltersActive = computed(() => documentQuery.value !== '' || documentDirectionFilter.value !== 'all'
  || documentMatchFilter.value !== 'all' || documentFrom.value !== '' || documentTo.value !== '');
function resetDocumentFilters() {
  documentQuery.value = '';
  documentDirectionFilter.value = 'all';
  documentMatchFilter.value = 'all';
  documentFrom.value = '';
  documentTo.value = '';
}
function documentSortBy(key: string) { toggleSort(documentSort, key); }
function documentSortValue(document: FinanceDocument, key: string): unknown {
  switch (key) {
    case 'date': return document.date;
    case 'direction': return document.direction;
    case 'partner': return document.partner;
    case 'amount': return document.amount;
    case 'matched': return document.matched ? 1 : 0;
    default: return null;
  }
}
const documentRows = computed<FinanceDocument[]>(() => {
  const rows: FinanceDocument[] = [
    ...f.invoices.map((item): FinanceDocument => ({
      key: `invoice-${item.id}`, kind: 'invoice', direction: 'income', date: item.issue_date,
      partner: custName(item), reference: item.number || '—', amount: Number(item.gross ?? 0), matched: isInvoiceLinked(item.id), item,
    })),
    ...f.standaloneReceipts.map((item): FinanceDocument => ({
      key: `receipt-${item.id}`, kind: 'receipt', direction: 'expense', date: item.date,
      partner: partnerOptions.value.find((p) => p.id === item.partner_id)?.name ?? item.name,
      reference: item.doc_number || item.name, amount: Math.abs(Number(item.amount ?? 0)),
      matched: item.bank_transaction_id != null || (item.linked_transaction_ids?.length ?? 0) > 0, item,
    })),
  ];
  const needle = documentQuery.value.trim().toLowerCase();
  const dirMul = documentSort.dir === 'asc' ? 1 : -1;
  return rows.filter((row) => {
    const receipt = row.kind === 'receipt' ? row.item as Receipt : null;
    const haystack = `${row.partner} ${row.reference} ${receipt?.category ?? ''} ${receipt?.order_ref ?? ''}`.toLowerCase();
    const date = row.date?.slice(0, 10) ?? '';
    return (!documentDirection.value || row.direction === documentDirection.value)
      && (documentMatchFilter.value === 'all' || (documentMatchFilter.value === 'matched') === row.matched)
      && (!documentFrom.value || date >= documentFrom.value)
      && (!documentTo.value || date <= documentTo.value)
      && (!needle || haystack.includes(needle));
  }).sort((a, b) => sortCmp(documentSortValue(a, documentSort.key), documentSortValue(b, documentSort.key)) * dirMul);
});
function openDocument(doc: FinanceDocument) {
  if (doc.kind === 'invoice') openInvoiceDocument(doc.item as Invoice);
  else openReceiptWorkspace(doc.item as Receipt);
}
async function runAllDocumentMatching() {
  await runInvoiceAutoMatch();
  await runAutoMatch();
}

const isLocked = computed(() => !!(draft.value?.imported || (draft.value?.number && draft.value?.status !== 'draft')));
const totals = computed(() => {
  let net = 0; let vat = 0;
  for (const l of lines.value) { const ln = (l.qty || 0) * (l.unitPrice || 0); net += ln; vat += ln * ((l.vatRate || 0) / 100); }
  net = Math.round(net * 100) / 100; vat = Math.round(vat * 100) / 100;
  return { net, vat, gross: Math.round((net + vat) * 100) / 100 };
});

// Indented project tree (roots + children by parent_id; unknown parents surface as roots).
// Sorting happens WITHIN each parent's children, never across the whole flat
// list — a subproject torn out of its parent by amount would read as a root.
const prjSort = reactive<{ key: string; dir: SortDir }>({ key: 'name', dir: 'asc' });
function prjSortBy(key: string) { toggleSort(prjSort, key); prjPage.value = 1; }
function prjSortValue(p: Project, key: string): unknown {
  const t = rolledFor(p.id);
  switch (key) {
    case 'name': return p.name;
    case 'out': return t.out;
    case 'in': return t.in;
    case 'net': return t.net;
    default: return null;
  }
}
const projectRows = computed(() => {
  const dirMul = prjSort.dir === 'asc' ? 1 : -1;
  const sorted = [...f.projects].sort((a, b) => sortCmp(prjSortValue(a, prjSort.key), prjSortValue(b, prjSort.key)) * dirMul);
  return projectTree(sorted);
});
const prjPage = ref(1);
const prjPerPage = ref(25);
const pagedProjectRows = computed(() => projectRows.value.slice((prjPage.value - 1) * prjPerPage.value, prjPage.value * prjPerPage.value));
// Deleting the last row of the last page must not leave the table blank.
watch([() => projectRows.value.length, prjPerPage], () => {
  prjPage.value = Math.min(prjPage.value, Math.max(1, Math.ceil(projectRows.value.length / prjPerPage.value)));
});
// A project can't be moved under itself or under one of its own descendants —
// the server rejects it (422 cycle), so don't offer it.
const parentOptions = computed(() => {
  const blocked = prjForm.id ? descendantIds(f.projects, prjForm.id) : new Set<number>();
  return f.projects.filter((p) => p.id !== prjForm.id && !blocked.has(p.id)).map((p) => ({ id: p.id, name: p.name }));
});
function projectName(id: number | null | undefined): string {
  return id == null ? '' : (f.projects.find((p) => p.id === id)?.name ?? `#${id}`);
}
function projectParentName(p: Project): string { return projectName(p.parent_id); }
function paymentName(id: number | null | undefined): string {
  return id == null ? '' : (f.paymentMethods.find((m) => m.id === id)?.name ?? '');
}
/** Rolled-up totals per project id — computed once per data change, not per row. */
const rolledTotalsById = computed(() => {
  const map = new Map<number, Totals>();
  for (const p of f.projects) map.set(p.id, rolledTotals(f.projects, p.id, f.transactions, f.standaloneReceipts));
  return map;
});
const EMPTY_TOTALS: Totals = { out: 0, in: 0, net: 0 };
function rolledFor(id: number): Totals { return rolledTotalsById.value.get(id) ?? EMPTY_TOTALS; }
// Roots only: summing every row would count each subproject twice.
const projectGrandTotal = computed<Totals>(() => {
  const ids = new Set(f.projects.map((p) => p.id));
  let out = 0; let inn = 0;
  for (const p of f.projects) {
    if (p.parent_id != null && ids.has(p.parent_id)) continue;
    const t = rolledFor(p.id);
    out += t.out; inn += t.in;
  }
  return { out: Math.round(out * 100) / 100, in: Math.round(inn * 100) / 100, net: Math.round((out - inn) * 100) / 100 };
});
const partnerOptions = computed(() => f.partners.map((p) => ({ id: p.id, name: p.name })));
/** Project picker options (indented so the hierarchy is visible in a flat select). */
const projectSelectOptions = computed(() => [
  { title: '—', value: '' },
  ...projectRows.value.map((r) => ({ title: `${'\u00a0\u00a0'.repeat(r.depth)}${r.p.name}`, value: r.p.id })),
]);

function conflict() { void f.load(); error(t('common.error')); }

function newInvoice() {
  draft.value = { status: 'draft', currency: 'EUR', issue_date: todayYmd(), customer: {}, lines: [] };
  lines.value = [{ desc: '', qty: 1, unitPrice: 0, vatRate: 19 }];
  loadCustomerFields(null);
  invDialog.value = true;
}
function editInvoice(i: Invoice) {
  // issue_date/due_date come back from the API as full ISO datetimes (Laravel
  // serialises `date`-cast columns the same as `datetime`-cast ones) — a native
  // <input type="date"> silently renders blank unless fed exactly YYYY-MM-DD.
  draft.value = {
    ...i,
    issue_date: i.issue_date ? String(i.issue_date).slice(0, 10) : i.issue_date,
    due_date: i.due_date ? String(i.due_date).slice(0, 10) : i.due_date,
  };
  lines.value = Array.isArray(i.lines) ? i.lines.map((l) => ({ ...l })) : [];
  loadCustomerFields(i.customer);
  invDialog.value = true;
}
function openInvoiceDocument(i: Invoice) {
  if (i.pdf_path) {
    openPreview(f.invoicePdfUrl(i.id), (i.number || 'invoice') + '.pdf', 'application/pdf');
    return;
  }
  editInvoice(i);
}
async function saveInvoice() {
  if (!draft.value) return;
  saving.value = true;
  const body: Record<string, unknown> = {
    status: draft.value.status, currency: draft.value.currency || 'EUR',
    issue_date: draft.value.issue_date, due_date: draft.value.due_date,
    customer: {
      ...(draft.value.customer ?? {}),
      name: custName_.value, attn: custAttn_.value, email: custEmail_.value,
      vatId: custVatId_.value, address: custAddress_.value,
    },
    lines: lines.value, note: draft.value.note,
    net: totals.value.net, vat: totals.value.vat, gross: totals.value.gross,
    vat_rate: lines.value[0]?.vatRate ?? 19,
    partner_id: draft.value.partner_id ?? null,
    discount_type: draft.value.discount_type ?? null,
    discount_value: draft.value.discount_value ?? null,
    skonto_percent: draft.value.skonto_percent ?? null,
    skonto_days: draft.value.skonto_days ?? null,
  };
  if (draft.value.id) body.version = draft.value.version;
  try {
    if (draft.value.id) await f.updateInvoice(draft.value.id, body);
    else await f.createInvoice(body);
    invDialog.value = false; await f.load(); await loadReports(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function finalize() {
  if (!draft.value?.id) return;
  try { const r = await f.finalizeInvoice(draft.value.id); draft.value = { ...r.invoice }; await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}
async function delInvoice(i: Invoice) { if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return; await f.deleteInvoice(i.id); await f.load(); await loadReports(); }

async function doStorno(i: Invoice) {
  try { await f.stornoInvoice(i.id); invDialog.value = false; await f.load(); await loadReports(); success(t('invoices.storno_created')); }
  catch { error(t('invoices.storno_failed')); }
}
async function doEmail(i: Invoice) {
  try { await f.emailInvoice(i.id); success(t('invoices.email_sent')); }
  catch { error(t('invoices.email_failed')); }
}
async function doDun(i: Invoice) {
  try { await f.dunInvoice(i.id); success(t('invoices.dun_sent')); }
  catch { error(t('invoices.dun_failed')); }
}

function resetForm(o: PPForm) {
  Object.assign(pForm, {
    id: undefined, version: undefined, name: '', type: 'bank', holder: '', business: false, url: '', note: '',
    iban: '', bic: '', bank: '', account_no: '', card_number: '', card_network: '', card_expiry: '', paypal_email: '',
  }, o);
}
function newPayment() { resetForm({ name: '', type: 'bank' }); pDialog.value = true; }
function editPayment(p: PaymentMethod) {
  resetForm({
    id: p.id, version: p.version, name: p.name, type: p.type,
    holder: p.holder ?? '', business: !!p.business, url: p.url ?? '', note: p.note ?? '',
    iban: p.iban ?? '', bic: p.bic ?? '', bank: p.bank ?? '', account_no: p.account_no ?? '',
    card_number: p.card_number ?? '', card_network: p.card_network ?? '', card_expiry: p.card_expiry ?? '', paypal_email: p.paypal_email ?? '',
  });
  pDialog.value = true;
}
async function savePP() {
  saving.value = true;
  try {
    await f.savePayment(pForm as unknown as Partial<PaymentMethod>);
    pDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}

// ---- Bank transactions ----
const bankAccount = ref(0); // 0 = all accounts
const bankCsvInput = ref<HTMLInputElement | null>(null);
const bankAccountItems = computed(() => [
  { title: t('invoices.tx_all_accounts'), value: 0 },
  ...f.paymentMethods.map((p) => ({ title: p.name, value: p.id })),
]);
const txSort = reactive<{ key: string; dir: SortDir }>({ key: 'date', dir: 'desc' });
function txSortBy(key: string) { toggleSort(txSort, key); }
function txSortValue(tx: BankTransaction, key: string): unknown {
  switch (key) {
    case 'date': return tx.date;
    case 'counterparty': return tx.counterparty;
    case 'purpose': return tx.purpose;
    case 'amount': return tx.amount;
    default: return null;
  }
}
// Search + filter: free-text across every identifying field a transaction
// carries (not just what's shown in the table — counterparty IBAN/BIC,
// booking text, end-to-end reference, and the linked invoice number all
// count as a match, since that's exactly what someone hunting for "did the
// INWX invoice actually get paid" would search by), plus a direction
// (income/expense) and a documentation-status (has a receipt or invoice linked) filter.
// Substring match against a transaction's signed/absolute amount, accepting
// either "." or "," as the decimal separator (so a German "9,52" search hits
// an amount stored as -9.52 just like "9.52" or "-9.52" would).
function amountMatches(amount: number, query: string): boolean {
  const q = query.trim().replace(',', '.');
  if (!/^-?\d/.test(q)) return false;
  return amount.toFixed(2).includes(q) || Math.abs(amount).toFixed(2).includes(q);
}
const txQuery = ref('');
const txDirection = ref<'all' | 'income' | 'expense'>('all');
const txDocFilter = ref<'all' | 'documented' | 'undocumented'>('all');
const txDirectionItems = computed(() => [
  { title: t('invoices.tx_dir_all'), value: 'all' },
  { title: t('invoices.tx_dir_in'), value: 'income' },
  { title: t('invoices.tx_dir_out'), value: 'expense' },
]);
const txDocItems = computed(() => [
  { title: t('invoices.tx_doc_all'), value: 'all' },
  { title: t('invoices.tx_doc_documented'), value: 'documented' },
  { title: t('invoices.tx_doc_undocumented'), value: 'undocumented' },
]);
const bankTransactions = computed(() => {
  let base = bankAccount.value ? f.transactions.filter((x) => x.payment_method_id === bankAccount.value) : f.transactions;
  if (txDirection.value === 'income') base = base.filter((x) => x.amount >= 0);
  else if (txDirection.value === 'expense') base = base.filter((x) => x.amount < 0);
  if (txDocFilter.value === 'documented') base = base.filter((x) => isTxDocumented(x));
  else if (txDocFilter.value === 'undocumented') base = base.filter((x) => !isTxDocumented(x));
  const s = txQuery.value.trim().toLowerCase();
  if (s) {
    base = base.filter((x) => [
      x.counterparty, x.counterparty_iban, x.bic, x.purpose, x.booking_text, x.eref, x.invoice_number,
    ].some((v) => String(v ?? '').toLowerCase().includes(s)) || amountMatches(x.amount, s));
  }
  const dirMul = txSort.dir === 'asc' ? 1 : -1;
  return [...base].sort((a, b) => sortCmp(txSortValue(a, txSort.key), txSortValue(b, txSort.key)) * dirMul);
});

// ---- Receipt <-> transaction linking (auto/manual assignment) ----
// Standalone receipts ("Fremdbelege") linked to a transaction either via
// bank_transaction_id (the common single-link case) or, for a split payment (one
// receipt settled by several separate charges), via linked_transaction_ids —
// distinct from a transaction's own embedded receipts[] (direct-upload reconcile).
function standaloneReceiptsForTx(txId: number): Receipt[] {
  return f.standaloneReceipts.filter((r) => r.bank_transaction_id === txId || (r.linked_transaction_ids ?? []).includes(txId));
}
function linkedInvoiceForTx(tx: BankTransaction): Invoice | undefined { return tx.invoice_id == null ? undefined : f.invoices.find((invoice) => invoice.id === tx.invoice_id); }
function txDocumentCount(tx: BankTransaction): number { return (linkedInvoiceForTx(tx) ? 1 : 0) + (tx.receipts?.length ?? 0) + standaloneReceiptsForTx(tx.id).length; }
function isTxDocumented(tx: BankTransaction): boolean { return txDocumentCount(tx) > 0; }
function openLinkedInvoice(tx: BankTransaction) { const invoice = linkedInvoiceForTx(tx); if (invoice) openInvoiceDocument(invoice); }
// Candidate pool for auto-pick/suggestions: expense transactions with no receipt yet.
const unlinkedTransactions = computed<BankTransaction[]>(() => f.transactions.filter((t) => t.amount < 0 && !isTxDocumented(t)));
function txSummary(id: number): string {
  const t = f.transactions.find((x) => x.id === id);
  return t ? `${fmtDate(t.date)} · ${t.counterparty || '—'} · ${money(t.amount)}` : `#${id}`;
}
// The receipts table's link badge: either the single linked transaction, or —
// for a split payment — every transaction it's linked to, joined together.
function receiptLinkSummary(r: Receipt): string | null {
  if (r.bank_transaction_id != null) return txSummary(r.bank_transaction_id);
  if (r.linked_transaction_ids?.length) return r.linked_transaction_ids.map((id) => txSummary(id)).join(' + ');
  return null;
}
// Manual "Buchung zuordnen" picker: ranked suggestions when the receipt has a
// recognised amount (fuzzy/FX-aware, a human reviews it here), else every unlinked
// transaction filtered by the free-text search, newest first.
const assignTxCandidates = computed<BookingSuggestion[]>(() => {
  const q = assignTxQuery.value.trim().toLowerCase();
  const matchesQuery = (t: BankTransaction) => !q || (t.counterparty || '').toLowerCase().includes(q) || (t.purpose || '').toLowerCase().includes(q);
  const amount = parseMoney(rForm.amount);
  if (amount != null && Number.isFinite(amount)) {
    return suggestBookings({ total: amount, date: rForm.date || null, currency: rForm.currency || null }, unlinkedTransactions.value.filter(matchesQuery), { limit: 30, rates: f.fxRates });
  }
  return unlinkedTransactions.value
    .filter(matchesQuery)
    .sort((a, b) => (b.date || '').localeCompare(a.date || ''))
    .slice(0, 30)
    .map((t) => ({ t, kind: 'exact' as const, dd: null, score: 0 }));
});

const vatCatItems = computed(() => [
  { title: '—', value: '' },
  { title: '19%', value: '19' }, { title: '7%', value: '7' }, { title: '0%', value: '0' },
  { title: t('invoices.vatcat_private'), value: 'private' },
]);

interface TxForm {
  id?: number; version?: number; payment_method_id: number | null;
  date: string; amount: string; counterparty: string; counterparty_iban: string;
  bic: string; purpose: string; booking_text: string; vat_cat: string; finance_project_id: number | null;
}
function blankTx(): TxForm {
  return {
    payment_method_id: bankAccount.value || (f.paymentMethods[0]?.id ?? null),
    date: todayYmd(), amount: '', counterparty: '', counterparty_iban: '',
    bic: '', purpose: '', booking_text: '', vat_cat: '', finance_project_id: null,
  };
}
const txForm = reactive<TxForm>(blankTx());
const txDialog = ref(false);
function newTx() { Object.assign(txForm, blankTx()); txDialog.value = true; }
function editTx(tx: BankTransaction) {
  Object.assign(txForm, {
    id: tx.id, version: tx.version, payment_method_id: tx.payment_method_id,
    // Same full-ISO-datetime-vs-<input type="date"> mismatch as invoices/receipts.
    date: tx.date ? String(tx.date).slice(0, 10) : '', amount: moneyInput(tx.amount), counterparty: tx.counterparty ?? '',
    counterparty_iban: tx.counterparty_iban ?? '', bic: tx.bic ?? '', purpose: tx.purpose ?? '',
    booking_text: tx.booking_text ?? '', vat_cat: tx.vat_cat ?? '', finance_project_id: tx.finance_project_id,
  });
  txDialog.value = true;
}
async function saveTx() {
  saving.value = true;
  try {
    const body = {
      payment_method_id: txForm.payment_method_id, date: txForm.date, amount: parseMoney(txForm.amount) ?? 0,
      counterparty: txForm.counterparty, counterparty_iban: txForm.counterparty_iban, bic: txForm.bic,
      purpose: txForm.purpose, booking_text: txForm.booking_text, vat_cat: txForm.vat_cat || null,
      finance_project_id: txForm.finance_project_id, version: txForm.version,
    };
    if (txForm.id) await f.updateTransaction(txForm.id, body);
    else await f.createTransaction(body);
    txDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delTx(tx: BankTransaction) {
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await f.deleteTransaction(tx.id); await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}

// CSV bank-statement import — parsing lives in shared/bank-csv.ts (pure + unit
// tested; a previous inline version here mis-parsed ISO dates and silently
// corrupted 351 real production transactions, see CLAUDE.md changelog).
const csvImportDialog = ref(false);
const csvImportStage = ref<'parsing' | 'uploading'>('parsing');
async function onBankCsv(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;

  // The account dropdown doubles as "which account do new rows belong to" — on
  // the ambiguous "All accounts" selection with more than one account, refuse
  // to silently guess (this is exactly how a bulk import once landed unnoticed
  // under the wrong/first account). Auto-use the account only when it is the
  // single unambiguous choice.
  let acct: number | null = bankAccount.value || null;
  if (!acct) {
    if (f.paymentMethods.length === 0) { error(t('invoices.tx_import_no_account')); return; }
    if (f.paymentMethods.length > 1) { error(t('invoices.tx_import_pick_account')); return; }
    acct = f.paymentMethods[0].id;
  }

  csvImportDialog.value = true;
  csvImportStage.value = 'parsing';
  try {
    const { rows, skipped: parseSkipped } = parseBankCsv(await file.text());
    if (!rows.length) { error(t('invoices.tx_import_empty')); return; }
    csvImportStage.value = 'uploading';
    const res = await f.bulkTransactions(acct, rows as unknown as Record<string, unknown>[]);
    await f.load();
    const skipped = res.skipped + parseSkipped;
    success(t('invoices.tx_import_done', { created: String(res.created), skipped: String(skipped) }));
  } catch { error(t('common.error')); } finally { csvImportDialog.value = false; }
}

// ---- Document preview (receipts + invoice PDFs), in-app instead of a new tab ----
const previewOpen = ref(false);
const previewUrl = ref('');
const previewName = ref('');
const previewIsImage = ref(false);
function openPreview(url: string, name: string, mime?: string | null) {
  previewUrl.value = url;
  previewName.value = name;
  previewIsImage.value = !!mime && mime.startsWith('image/');
  previewOpen.value = true;
}
const previewDownloadUrl = computed(() => previewUrl.value + (previewUrl.value.includes('?') ? '&' : '?') + 'download=1');

// ---- Bank-transaction receipts (reconcile: attach receipt documents to a booking) ----
const txRecDialog = ref(false);
const txRecTx = ref<BankTransaction | null>(null);
const txRecInput = ref<HTMLInputElement | null>(null);
const txRecBusy = ref(false);
function openTxReceipts(tx: BankTransaction) { txRecTx.value = tx; txRecDialog.value = true; }
function openTxReceipt(r: import('@spa/stores/finance').TxReceipt) {
  if (txRecTx.value) openPreview(f.txReceiptUrl(txRecTx.value.id, r.id), r.name, r.mime);
}
async function onTxReceiptFile(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file || !txRecTx.value) return;
  txRecBusy.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const r = await f.attachTxReceipt(txRecTx.value.id, fd);
    txRecTx.value = r.transaction;
    await f.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { txRecBusy.value = false; }
}
async function delTxReceipt(r: import('@spa/stores/finance').TxReceipt) {
  if (!txRecTx.value) return;
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try {
    await f.deleteTxReceipt(txRecTx.value.id, r.id);
    if (txRecTx.value) txRecTx.value.receipts = (txRecTx.value.receipts ?? []).filter((x) => x.id !== r.id);
    await f.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}
async function unlinkStandaloneReceipt(r: Receipt) {
  // Clears whichever link is set — the single bank_transaction_id, or (for a split
  // payment) linked_transaction_ids — regardless of which transaction's modal this
  // was clicked from: unlinking undocuments the receipt entirely, not per-charge.
  try { await f.updateReceipt(r.id, { bank_transaction_id: null, linked_transaction_ids: null, version: r.version }); await f.load(); success(t('common.saved')); }
  catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); }
}

// ---- Finance categories (managed list feeding the receipt/partner category datalist) ----
const catDialog = ref(false);
const catForm = reactive<{ id?: number; version?: number; name: string; color: string; account_no: string }>({ name: '', color: '#6750a4', account_no: '' });
function resetCat() { Object.assign(catForm, { id: undefined, version: undefined, name: '', color: '#6750a4', account_no: '' }); }
function editCat(c: FinanceCategory) { Object.assign(catForm, { id: c.id, version: c.version, name: c.name, color: c.color ?? '#6750a4', account_no: c.account_no ?? '' }); }
async function saveCat() {
  const name = catForm.name.trim();
  if (!name) return;
  saving.value = true;
  try {
    const body = { name, color: catForm.color, account_no: catForm.account_no.trim() || null, version: catForm.version };
    if (catForm.id) await f.updateCategory(catForm.id, body);
    else await f.createCategory(body);
    resetCat(); await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delCat(c: FinanceCategory) {
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await f.deleteCategory(c.id); await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}

// ---- Business partners (Geschäftspartner): list ↔ detail, rich editor ----
const partnersView = ref<'list' | 'detail'>('list');
const openPartnerId = ref<number | null>(null);
const partnerSearch = ref('');
const pDlg = ref(false);
const logoBusy = ref(false);

interface PContact { id: string; name: string; email: string; phone: string; role: string }
interface PartnerForm {
  id?: number; version?: number;
  name: string; url: string; logo: string; email: string; invoice_email: string;
  phone: string; hourly_rate: string; currency: string; vat_id: string;
  address: string; category: string; note: string; contacts: PContact[];
}
function blankPartnerForm(): PartnerForm {
  return { name: '', url: '', logo: '', email: '', invoice_email: '', phone: '', hourly_rate: '', currency: '', vat_id: '', address: '', category: '', note: '', contacts: [] };
}
const partnerForm = reactive<PartnerForm>(blankPartnerForm());

const currencyOptions = computed(() => [
  { title: t('invoices.partner_currency_default'), value: '' },
  ...['EUR', 'USD', 'GBP', 'CHF', 'JPY'].map((c) => ({ title: c, value: c })),
]);

function uid(): string { return globalThis.crypto?.randomUUID?.() ?? `${Date.now()}${Math.random().toString(16).slice(2)}`; }
function logoSrc(v?: string | null): string { return typeof v === 'string' && /^(data:|https?:)/.test(v) ? v : ''; }
function partnerContactName(p: Partner): string { return p.contacts?.[0]?.name ?? ''; }

const partnerSort = reactive<{ key: string; dir: SortDir }>({ key: 'name', dir: 'asc' });
function partnerSortBy(key: string) { toggleSort(partnerSort, key); }
function partnerSortValue(p: Partner, key: string): unknown {
  switch (key) {
    case 'name': return p.name;
    case 'contact': return partnerContactName(p);
    case 'email': return p.email;
    case 'phone': return p.phone;
    case 'vat': return p.vat_id;
    case 'links': return partnerLinkCount(p.id);
    default: return null;
  }
}
const filteredPartners = computed<Partner[]>(() => {
  const s = partnerSearch.value.trim().toLowerCase();
  const base = !s ? f.partners : f.partners.filter((p) => [p.name, p.email, p.invoice_email, p.phone, p.vat_id, p.address, p.url, p.category, p.note, ...(p.contacts?.map((c) => c.name) ?? [])]
    .some((v) => String(v ?? '').toLowerCase().includes(s)));
  const dirMul = partnerSort.dir === 'asc' ? 1 : -1;
  return [...base].sort((a, b) => sortCmp(partnerSortValue(a, partnerSort.key), partnerSortValue(b, partnerSort.key)) * dirMul);
});
const openPartnerRec = computed<Partner | null>(() => f.partners.find((p) => p.id === openPartnerId.value) ?? null);

function invoicesForPartner(id: number): Invoice[] {
  return f.invoices
    .filter((i) => i.partner_id === id || (i.customer as { partnerId?: number } | null)?.partnerId === id)
    .sort((a, b) => String(b.issue_date ?? '').localeCompare(String(a.issue_date ?? '')));
}
function receiptsForPartner(id: number): Receipt[] { return f.standaloneReceipts.filter((r) => r.partner_id === id); }
function partnerLinkCount(id: number): number { return invoicesForPartner(id).length + receiptsForPartner(id).length; }

function fmtRate(p: Partner): string {
  const n = Number(p.hourly_rate ?? 0);
  const cur = p.currency || 'EUR';
  return fmtMoney(n, cur);
}

function openPartner(p: Partner) { openPartnerId.value = p.id; partnersView.value = 'detail'; }
function backToPartners() { partnersView.value = 'list'; openPartnerId.value = null; }

function newPartner() { Object.assign(partnerForm, blankPartnerForm()); pDlg.value = true; }
function editPartner(p: Partner) {
  Object.assign(partnerForm, blankPartnerForm(), {
    id: p.id, version: p.version, name: p.name ?? '', url: p.url ?? '', logo: p.logo ?? '',
    email: p.email ?? '', invoice_email: p.invoice_email ?? '', phone: p.phone ?? '',
    hourly_rate: moneyInput(p.hourly_rate), currency: p.currency ?? '',
    vat_id: p.vat_id ?? '', address: p.address ?? '', category: p.category ?? '', note: p.note ?? '',
    contacts: Array.isArray(p.contacts)
      ? p.contacts.map((c) => ({ id: c.id ?? uid(), name: c.name ?? '', email: c.email ?? '', phone: c.phone ?? '', role: c.role ?? '' }))
      : [],
  });
  pDlg.value = true;
}
function addContact() { partnerForm.contacts.push({ id: uid(), name: '', email: '', phone: '', role: '' }); }
function removeContact(i: number) { partnerForm.contacts.splice(i, 1); }

// Derive a host from the partner URL, else the email domain (used to pull the favicon).
function partnerHost(...vals: (string | undefined)[]): string {
  for (const raw of vals) {
    const s = (raw ?? '').trim();
    if (!s) continue;
    if (s.includes('@')) { const d = s.split('@').pop()?.trim(); if (d?.includes('.')) return d; continue; }
    if (!s.includes('.')) continue;
    try { return new URL(/^https?:\/\//i.test(s) ? s : `https://${s}`).hostname; } catch { /* try next */ }
  }
  return '';
}
async function loadPartnerLogo() {
  const host = partnerHost(partnerForm.url, partnerForm.email, partnerForm.invoice_email);
  if (!host) { error(t('common.error')); return; }
  logoBusy.value = true;
  try {
    const r = await api.get<{ icon: string | null }>(`/api/v1/passwords/icon?domain=${encodeURIComponent(host)}`);
    const icon = r?.icon ?? null;
    if (!icon) { error(t('invoices.partner_logo_none')); return; }
    partnerForm.logo = icon;
  } catch { error(t('common.error')); } finally { logoBusy.value = false; }
}

// Complete this partner's profile from a scanned document (invoice/letterhead/business
// card) — fills only EMPTY fields (address, VAT-ID), never overwrites what's there.
const partnerOcrInput = ref<HTMLInputElement | null>(null);
const partnerOcrBusy = ref(false);
async function scanPartnerDoc(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  partnerOcrBusy.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const res = await api.upload<{ text: string }>('/api/v1/invoices/ocr', fd);
    const a = analyzeReceiptText(res.text, ownNames.value);
    let filled = false;
    if (a.vatId && !partnerForm.vat_id) { partnerForm.vat_id = a.vatId; filled = true; }
    if (!partnerForm.category && a.category) { partnerForm.category = a.category; filled = true; }
    success(filled ? t('invoices.ocr_done') : t('invoices.ocr_nothing_new'));
  } catch { error(t('invoices.ocr_failed')); } finally { partnerOcrBusy.value = false; }
}

async function savePartnerForm() {
  if (!partnerForm.name.trim()) { error(t('common.error')); return; }
  saving.value = true;
  const body: Partial<Partner> & { version?: number } = {
    name: partnerForm.name.trim(),
    url: partnerForm.url || null, logo: partnerForm.logo || null,
    email: partnerForm.email || null, invoice_email: partnerForm.invoice_email || null,
    phone: partnerForm.phone || null, vat_id: partnerForm.vat_id || null,
    currency: partnerForm.currency || null, address: partnerForm.address || null,
    category: partnerForm.category || null, note: partnerForm.note || null,
    hourly_rate: parseMoney(partnerForm.hourly_rate),
    contacts: partnerForm.contacts
      .filter((c) => `${c.name}${c.email}${c.phone}${c.role}`.trim() !== '')
      .map((c) => ({ id: c.id, name: c.name, email: c.email, phone: c.phone, role: c.role })),
  };
  if (partnerForm.id) { body.id = partnerForm.id; body.version = partnerForm.version; }
  try {
    await f.savePartner(body);
    pDlg.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delPartner(p: Partner) {
  if (!await confirmAsk(t('invoices.partner_delete_confirm'), { danger: true })) return;
  try { await f.deletePartner(p.id); backToPartners(); await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}

// ---- Receipts ----
function newReceipt() {
  Object.assign(rForm, {
    id: undefined, version: undefined, name: '', category: '', tags: [], vat: '', note: '', partner_id: null,
    amount: '', currency: '', date: '', order_ref: '', doc_number: '', bank_transaction_id: null, linked_transaction_ids: null,
    finance_project_id: null,
  });
  rFile.value = null; lastOcrText.value = ''; rDialog.value = true;
}

// Normalise a merchant/partner name for matching: lowercase, strip legal-form suffixes
// (GmbH/AG/UG/…) and punctuation, so "IntellyTec GmbH" and "IntellyTec" match.
function normMerchant(name: string): string {
  return name.toLowerCase()
    .replace(/\b(gmbh|mbh|ug|ag|kg|ohg|gbr|ltd|limited|llc|inc|corp|e\.?v\.?|& co)\b/g, '')
    .replace(/[^a-z0-9äöüß]+/g, '').trim();
}

/**
 * Find an existing partner matching the scanned merchant name, or create one. If a
 * match is found and it is missing a VAT-ID that the scan discovered, complete it
 * (never overwrites a VAT-ID the owner already entered). Returns the partner id, or
 * null when there is no merchant name to act on.
 */
async function autoPartner(merchant: string, vatId: string): Promise<number | null> {
  const key = normMerchant(merchant);
  if (!key) return null;
  const existing = f.partners.find((p) => normMerchant(p.name) === key);
  if (existing) {
    if (vatId && !existing.vat_id) {
      try { await f.savePartner({ id: existing.id, version: existing.version, vat_id: vatId }); await f.load(); } catch { /* best-effort completion */ }
    }
    return existing.id;
  }
  try {
    const res = await f.savePartner({ name: merchant, vat_id: vatId || null }) as { partner: Partner };
    await f.load();
    return res.partner.id;
  } catch { return null; }
}

/** Scan the selected receipt file server-side (text only) and autofill the form. */
async function scanReceipt() {
  const file = Array.isArray(rFile.value) ? rFile.value[0] : rFile.value;
  if (!file) return;
  ocrBusy.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const res = await api.upload<{ text: string }>('/api/v1/invoices/ocr', fd);
    lastOcrText.value = res.text;
    const a = analyzeReceiptText(res.text, ownNames.value);
    if (a.category && !rForm.category) rForm.category = a.category;
    if (a.vat && !rForm.vat) rForm.vat = a.vat;
    if (a.total != null && !rForm.amount) rForm.amount = String(a.total);
    if (a.currency && !rForm.currency) rForm.currency = a.currency;
    if (a.date && !rForm.date) rForm.date = a.date;
    if (a.orderRef && !rForm.order_ref) rForm.order_ref = a.orderRef;
    if (a.number && !rForm.doc_number) rForm.doc_number = a.number;
    if (!rForm.name && (a.date || a.merchant || a.number)) rForm.name = buildReceiptName(a.date, a.merchant, a.number, a.eigenbelegType);
    for (const tag of a.tags) if (!rForm.tags.includes(tag)) rForm.tags.push(tag);
    if (a.merchant && rForm.partner_id == null) rForm.partner_id = await autoPartner(a.merchant, a.vatId);
    // Immediate "Autozuweisung": an unambiguous cent-exact match auto-links right
    // away; anything ambiguous is left for the "Buchung zuordnen" picker or the
    // batch Auto-Zuordnen pass (which also handles the multi-receipt-per-charge case).
    if (rForm.bank_transaction_id == null && a.total != null) {
      const pick = autoPick({ total: a.total, date: a.date || null }, unlinkedTransactions.value);
      if (pick) rForm.bank_transaction_id = pick.id;
    }
    success(t('invoices.ocr_done'));
  } catch { error(t('invoices.ocr_failed')); } finally { ocrBusy.value = false; }
}

const rescanBusy = ref(false);
/**
 * Re-run recognition on an ALREADY-uploaded receipt, from its stored OCR text
 * (no re-upload, no server call — the text was captured once at first scan).
 * Empty fields are filled in as usual, but the NAME and the invoice NUMBER
 * are always refreshed: an existing receipt's name is almost always still
 * the original uploaded filename (so "only fill if empty" would never
 * trigger the one thing the name part of this button exists for), and the
 * invoice number has repeatedly been reported wrong/missing from an older,
 * weaker extraction — a stale wrong value must not survive a fill-only-if-
 * empty guard forever when the user explicitly asks to try again.
 */
async function rescanReceipt() {
  if (!rForm.id) return;
  const current = f.standaloneReceipts.find((x) => x.id === rForm.id);
  const text = current?.ocr ?? '';
  if (!text) { error(t('invoices.ocr_no_text')); return; }
  rescanBusy.value = true;
  try {
    const a = analyzeReceiptText(text, ownNames.value);
    if (a.category && !rForm.category) rForm.category = a.category;
    if (a.vat && !rForm.vat) rForm.vat = a.vat;
    if (a.total != null && !rForm.amount) rForm.amount = String(a.total);
    if (a.currency && !rForm.currency) rForm.currency = a.currency;
    if (a.date && !rForm.date) rForm.date = a.date;
    if (a.orderRef && !rForm.order_ref) rForm.order_ref = a.orderRef;
    if (a.number) rForm.doc_number = a.number;
    for (const tag of a.tags) if (!rForm.tags.includes(tag)) rForm.tags.push(tag);
    const suggested = buildReceiptName(a.date, a.merchant, a.number, a.eigenbelegType);
    if (suggested) rForm.name = suggested;
    success(t('invoices.ocr_done'));
  } finally { rescanBusy.value = false; }
}

// ---- Bulk rescan: re-run recognition against every already-uploaded receipt's
// stored OCR text (no re-upload) and review what it found, per document, before
// applying — the batch counterpart to the single-receipt rescan above. ----
interface BulkRescanRow {
  id: number; name: string; suggestedName: string; date: string | null; total: number | null;
  category: string | null; number: string | null; merchant: string | null; vatId: string;
  accepted: boolean;
}
const bulkRescanDialog = ref(false);
const bulkRescanBusy = ref(false);
const bulkRescanApplying = ref(false);
const bulkRescanRows = ref<BulkRescanRow[]>([]);
function runBulkRescan() {
  bulkRescanBusy.value = true;
  try {
    const rows: BulkRescanRow[] = [];
    for (const r of f.standaloneReceipts) {
      if (!r.ocr) continue;
      const a = analyzeReceiptText(r.ocr, ownNames.value);
      const suggestedName = buildReceiptName(a.date, a.merchant, a.number, a.eigenbelegType) || r.name;
      const fillsDate = !r.date && !!a.date;
      const fillsTotal = r.amount == null && a.total != null;
      const fillsCategory = !r.category && !!a.category;
      // A receipt from before the merchant→partner auto-link existed, or one
      // that was created/corrected outside the normal upload flow (e.g. a
      // direct data fix), never got its partner_id set even though a partner
      // of that exact name may exist (or now exists) — resolved the same way
      // as a fresh upload, via autoPartner (fuzzy name match, creates one if
      // none exists yet).
      const fillsPartner = !r.partner_id && !!a.merchant;
      // Unlike the other fields (fill-only-if-empty), the invoice number is
      // proposed whenever the fresh scan disagrees with what's stored — this
      // is a human-reviewed batch (every row needs an explicit checkbox
      // accept before applyBulkRescan writes anything), so it's safe to also
      // surface a correction of an already-populated-but-wrong value here.
      const fillsNumber = !!a.number && a.number !== r.doc_number;
      if (!fillsDate && !fillsTotal && !fillsCategory && !fillsNumber && !fillsPartner && suggestedName === r.name) continue;
      rows.push({
        id: r.id, name: r.name, suggestedName,
        date: fillsDate ? a.date : null, total: fillsTotal ? a.total : null, category: fillsCategory ? a.category : null,
        number: fillsNumber ? a.number : null, merchant: fillsPartner ? a.merchant : null, vatId: a.vatId,
        accepted: true,
      });
    }
    bulkRescanRows.value = rows;
    if (!rows.length) { success(t('invoices.ocr_rescan_all_none')); return; }
    bulkRescanDialog.value = true;
  } finally { bulkRescanBusy.value = false; }
}
async function applyBulkRescan() {
  bulkRescanApplying.value = true;
  let updated = 0; let failed = 0;
  try {
    for (const row of bulkRescanRows.value) {
      if (!row.accepted) continue;
      const r = f.standaloneReceipts.find((x) => x.id === row.id);
      if (!r) continue;
      const body: Record<string, unknown> = { name: row.suggestedName, version: r.version };
      if (row.date) body.date = row.date;
      if (row.total != null) body.amount = row.total;
      if (row.category) body.category = row.category;
      if (row.number) body.doc_number = row.number;
      if (row.merchant) {
        const partnerId = await autoPartner(row.merchant, row.vatId);
        if (partnerId != null) body.partner_id = partnerId;
      }
      try { await f.updateReceipt(row.id, body); updated++; } catch { failed++; }
    }
    await f.load();
    bulkRescanDialog.value = false;
    if (failed) error(t('invoices.match_some_failed', { failed: String(failed) }));
    else success(t('invoices.ocr_rescan_all_done', { n: String(updated) }));
  } finally { bulkRescanApplying.value = false; }
}

// ---- Receipt inbox: drop/pick one or many files, each is captured automatically
// (OCR'd, recognised, auto-matched/created a partner) without opening a dialog per
// ---- Uploading a document: incoming receipt OR outgoing invoice ----
// The two are different records (finance_receipts vs invoices), so which one a
// dropped PDF becomes cannot be guessed from the file: the drop overlay asks by
// splitting into two halves, and the toolbar has one button each.
const docDrag = ref(false);
const invUploadInput = ref<HTMLInputElement | null>(null);
const invUploadBusy = ref(false);
const invUploadDone = ref(0);
const invUploadTotal = ref(0);

function onDocDragOver() { docDrag.value = true; }
function onDocDragLeave() { docDrag.value = false; }
function droppedFiles(e: DragEvent, pdfOnly: boolean): File[] {
  return Array.from(e.dataTransfer?.files ?? [])
    .filter((file) => (pdfOnly ? file.type === 'application/pdf' : /^application\/pdf$|^image\//.test(file.type)));
}
function onDropReceipts(e: DragEvent) {
  docDrag.value = false;
  const files = droppedFiles(e, false);
  if (files.length) void processInboxFiles(files);
  else error(t('invoices.upload_no_supported_file'));
}
function onDropInvoices(e: DragEvent) {
  docDrag.value = false;
  const files = droppedFiles(e, true);
  if (files.length) void uploadInvoicePdfs(files);
  else error(t('invoices.upload_pdf_only'));
}
function onInvoicePick(e: Event) {
  const input = e.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  input.value = '';
  if (files.length) void uploadInvoicePdfs(files);
}

/**
 * An outgoing invoice that already exists as a PDF (written elsewhere, or an
 * older one being filed): create the record, attach the document, and prefill
 * from the same OCR recogniser the receipt inbox uses.
 *
 * The record is created as a plain DRAFT, not `imported`, on purpose: an
 * imported invoice is read-only in the editor, so the numbers the recogniser
 * missed could never be filled in. The PDF stays the authoritative document —
 * the editor links to it while the owner completes the fields.
 */
async function uploadInvoicePdfs(files: File[]) {
  invUploadBusy.value = true;
  invUploadTotal.value = files.length;
  invUploadDone.value = 0;
  let failed = 0;
  let lastId: number | null = null;
  for (const file of files) {
    try {
      // Best-effort recognition: a failure here must not cost the upload, the
      // document is worth filing even with every field left blank.
      let a: ReturnType<typeof analyzeReceiptText> | null = null;
      try {
        const fd = new FormData();
        fd.append('file', file);
        const res = await api.upload<{ text: string }>('/api/v1/invoices/ocr', fd);
        a = analyzeReceiptText(res.text, ownNames.value);
      } catch { /* keep going without a prefill */ }

      const vatRate = a?.vat != null && a.vat !== '' ? Number(a.vat) : 19;
      const gross = a?.total ?? null;
      // One synthetic net line so the editor's own totals agree with the gross
      // read off the document (net = gross / (1 + rate/100)); the owner replaces
      // it with the real positions if they want them itemised.
      const net = gross != null ? Math.round((gross / (1 + vatRate / 100)) * 100) / 100 : 0;
      const created = await f.createInvoice({
        status: 'draft',
        issue_date: a?.date || todayYmd(),
        currency: a?.currency || 'EUR',
        vat_rate: vatRate,
        customer: {},
        lines: gross != null ? [{ desc: file.name.replace(/\.pdf$/i, ''), qty: 1, unitPrice: net, vatRate }] : [],
        note: null,
      });
      const id = created.invoice.id;
      const pdf = new FormData();
      pdf.append('file', file);
      await f.uploadInvoicePdf(id, pdf);
      lastId = id;
    } catch { failed++; }
    invUploadDone.value++;
  }
  await f.load();
  invUploadBusy.value = false;
  if (failed) error(t('invoices.inbox_some_failed', { failed: String(failed), total: String(files.length) }));
  else success(t('common.saved'));
  // Land in the editor of the last upload so the missing fields are right there.
  const fresh = lastId != null ? f.invoices.find((i) => i.id === lastId) : null;
  if (fresh) editInvoice(fresh);
}

// file — the Candis-style "drop a batch, review afterwards" flow.
const inboxDrag = ref(false);
const inboxBusy = ref(false);
const inboxDone = ref(0);
const inboxTotal = ref(0);
const inboxInput = ref<HTMLInputElement | null>(null);

function onInboxDrop(e: DragEvent) {
  inboxDrag.value = false;
  const files = Array.from(e.dataTransfer?.files ?? []).filter((f) => /^application\/pdf$|^image\//.test(f.type));
  if (files.length) void processInboxFiles(files);
}
function onInboxPick(e: Event) {
  const input = e.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  input.value = '';
  if (files.length) void processInboxFiles(files);
}

async function processInboxFiles(files: File[]) {
  inboxBusy.value = true;
  inboxTotal.value = files.length;
  inboxDone.value = 0;
  let failed = 0;
  let skippedDupes = 0;
  // Content-dedup: a byte-identical file already on file (or repeated within
  // THIS same drop, e.g. the same file picked twice) never re-triggers an OCR
  // call or upload — real case: 27 files dropped, several hit the OCR rate
  // limit and failed, so the whole 27 got re-dropped to retry, silently
  // re-uploading the ones that had already succeeded. Seeded from the already
  // loaded receipts; growed as this batch's own uploads land.
  const knownSigs = new Set(f.standaloneReceipts.map((r) => r.sig).filter((s): s is string => !!s));
  // Track transactions this batch already claimed so two files in the same drop
  // can't both auto-pick the same booking (the batch Auto-Zuordnen pass afterwards
  // still catches anything ambiguous, incl. several receipts summing to one charge).
  const claimed = new Set<number>();
  for (const file of files) {
    try {
      const sig = await fileSig(file);
      if (knownSigs.has(sig)) { skippedDupes++; inboxDone.value++; continue; }

      const ocrFd = new FormData();
      ocrFd.append('file', file);
      const res = await api.upload<{ text: string }>('/api/v1/invoices/ocr', ocrFd);
      const a = analyzeReceiptText(res.text, ownNames.value);
      const partnerId = a.merchant ? await autoPartner(a.merchant, a.vatId) : null;
      const pick = a.total != null
        ? autoPick({ total: a.total, date: a.date || null }, unlinkedTransactions.value.filter((t) => !claimed.has(t.id)))
        : null;
      if (pick) claimed.add(pick.id);

      const suggestedName = buildReceiptName(a.date, a.merchant, a.number, a.eigenbelegType);
      const fd = new FormData();
      fd.append('file', file);
      fd.append('sig', sig);
      if (suggestedName) fd.append('name', suggestedName);
      if (a.category) fd.append('category', a.category);
      if (a.vat) fd.append('vat', a.vat);
      if (a.total != null) fd.append('amount', String(a.total));
      if (a.currency) fd.append('currency', a.currency);
      if (a.date) fd.append('date', a.date);
      if (a.orderRef) fd.append('order_ref', a.orderRef);
      if (a.number) fd.append('doc_number', a.number);
      if (partnerId != null) fd.append('partner_id', String(partnerId));
      if (pick) fd.append('bank_transaction_id', String(pick.id));
      fd.append('ocr', res.text.slice(0, 200000));
      for (const tag of a.tags) fd.append('tags[]', tag);
      const created = await f.createReceipt(fd);
      if (created.duplicate) skippedDupes++;
      knownSigs.add(sig);
    } catch { failed++; }
    inboxDone.value++;
  }
  await f.load();
  inboxBusy.value = false;
  if (failed) error(t('invoices.inbox_some_failed', { failed: String(failed), total: String(files.length) }));
  else if (skippedDupes) success(t('invoices.inbox_done_with_dupes', { count: String(files.length - skippedDupes), dupes: String(skippedDupes) }));
  else success(t('invoices.inbox_done', { count: String(files.length) }));
}
function editReceipt(r: Receipt) {
  Object.assign(rForm, {
    id: r.id, version: r.version, name: r.name, category: r.category ?? '',
    tags: Array.isArray(r.tags) ? [...r.tags] : [], vat: r.vat ?? '', note: r.note ?? '', partner_id: r.partner_id,
    amount: moneyInput(r.amount), currency: r.currency ?? '',
    // Same full-ISO-datetime-vs-<input type="date"> mismatch as invoices/transactions.
    date: r.date ? String(r.date).slice(0, 10) : '',
    order_ref: r.order_ref ?? '', doc_number: r.doc_number ?? '', bank_transaction_id: r.bank_transaction_id,
    linked_transaction_ids: r.linked_transaction_ids ?? null, finance_project_id: r.finance_project_id,
  });
  rFile.value = null;
}
async function saveReceipt() {
  saving.value = true;
  try {
    if (rForm.id) {
      const body: Record<string, unknown> = {
        name: rForm.name, category: rForm.category || null, tags: rForm.tags,
        vat: rForm.vat || null, note: rForm.note || null, partner_id: rForm.partner_id,
        amount: parseMoney(rForm.amount), currency: rForm.currency || null,
        date: rForm.date || null, order_ref: rForm.order_ref || null, doc_number: rForm.doc_number || null,
        bank_transaction_id: rForm.bank_transaction_id, linked_transaction_ids: rForm.linked_transaction_ids,
        finance_project_id: rForm.finance_project_id, version: rForm.version,
      };
      await f.updateReceipt(rForm.id, body);
    } else {
      const file = Array.isArray(rFile.value) ? rFile.value[0] : rFile.value;
      if (!file) { error(t('common.error')); saving.value = false; return; }
      const fd = new FormData();
      fd.append('file', file);
      fd.append('sig', await fileSig(file));
      if (rForm.name) fd.append('name', rForm.name);
      if (rForm.category) fd.append('category', rForm.category);
      if (rForm.vat) fd.append('vat', rForm.vat);
      if (rForm.note) fd.append('note', rForm.note);
      { const parsed = parseMoney(rForm.amount); if (parsed != null) fd.append('amount', String(parsed)); }
      if (rForm.currency) fd.append('currency', rForm.currency);
      if (rForm.date) fd.append('date', rForm.date);
      if (rForm.order_ref) fd.append('order_ref', rForm.order_ref);
      if (rForm.doc_number) fd.append('doc_number', rForm.doc_number);
      if (rForm.partner_id != null) fd.append('partner_id', String(rForm.partner_id));
      if (rForm.bank_transaction_id != null) fd.append('bank_transaction_id', String(rForm.bank_transaction_id));
      if (rForm.finance_project_id != null) fd.append('finance_project_id', String(rForm.finance_project_id));
      if (lastOcrText.value) fd.append('ocr', lastOcrText.value.slice(0, 200000));
      for (const tag of rForm.tags) fd.append('tags[]', tag);
      const created = await f.createReceipt(fd);
      if (created.duplicate) {
        rDialog.value = false; rWorkspace.value = false; await f.load();
        success(t('invoices.receipt_already_uploaded', { name: created.receipt.name }));
        return;
      }
    }
    rDialog.value = false; rWorkspace.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delReceipt(r: Receipt) {
  if (!await confirmAsk(t('invoices.receipt_delete_confirm'), { danger: true })) return;
  await f.deleteReceipt(r.id);
  rWorkspace.value = false;
  await f.load();
}

// ---- Auto-Zuordnen: batch, retroactive receipt<->transaction linking (server-side
// ReceiptMatcher — handles the many-receipts-for-one-charge case, e.g. Amazon
// splitting an order into several shipment invoices settled by one card charge). ----
interface ReviewGroup extends ReceiptMatchGroup { accepted: boolean }
interface SplitReviewGroup extends SplitPaymentGroup { accepted: boolean }
const matchDialog = ref(false);
const matchBusy = ref(false);
const matchApplying = ref(false);
const matchGroups = ref<ReviewGroup[]>([]);
const splitGroups = ref<SplitReviewGroup[]>([]);
const matchDuplicates = ref<ReceiptDuplicate[]>([]);
function receiptLabel(id: number): string { const r = f.standaloneReceipts.find((x) => x.id === id); return r ? `${r.name} (${money(Number(r.amount ?? 0))})` : `#${id}`; }
function matchReasonLabel(reason: string): string {
  return reason === 'order_ref' ? t('invoices.match_reason_order_ref')
    : reason === 'sum' ? t('invoices.match_reason_sum')
      : t('invoices.match_reason_exact');
}
/**
 * Recognition fields (amount/date/order_ref/doc_number) only started being
 * persisted with this feature — a receipt captured before it exists with those
 * fields empty even though its OCR text (from the original scan) is already
 * stored, so the matcher has nothing to work with for it. Re-running the same
 * client-side recognition against the already-stored text (no re-fetch, no
 * server OCR call) backfills them for free before every auto-match pass.
 */
async function backfillReceiptFields(): Promise<number> {
  const targets = f.standaloneReceipts.filter((r) => r.amount == null && r.ocr);
  let updated = 0;
  for (const r of targets) {
    const a = analyzeReceiptText(r.ocr ?? '', ownNames.value);
    if (a.total == null && !a.date && !a.orderRef && !a.number) continue;
    try {
      await f.updateReceipt(r.id, {
        amount: a.total, date: a.date || null, order_ref: a.orderRef || null, doc_number: a.number || null,
        version: r.version,
      });
      updated++;
    } catch { /* best-effort — a stale version just leaves this receipt for a later pass */ }
  }
  if (updated) await f.load();
  return updated;
}
async function runAutoMatch() {
  matchBusy.value = true;
  try {
    await backfillReceiptFields();
    const res = await f.receiptMatches();
    matchGroups.value = res.groups.map((g) => ({ ...g, accepted: true }));
    splitGroups.value = res.splitPayments.map((g) => ({ ...g, accepted: true }));
    matchDuplicates.value = res.duplicates;
    if (!matchGroups.value.length && !splitGroups.value.length && !matchDuplicates.value.length) { success(t('invoices.match_none')); return; }
    matchDialog.value = true;
  } catch { error(t('common.error')); } finally { matchBusy.value = false; }
}
async function applyMatches() {
  matchApplying.value = true;
  let linked = 0; let failed = 0;
  try {
    for (const g of matchGroups.value) {
      if (!g.accepted) continue;
      for (const id of g.receipt_ids) {
        const r = f.standaloneReceipts.find((x) => x.id === id);
        if (!r) continue;
        try { await f.updateReceipt(id, { bank_transaction_id: g.transaction_id, version: r.version }); linked++; }
        catch { failed++; }
      }
    }
    for (const g of splitGroups.value) {
      if (!g.accepted) continue;
      const r = f.standaloneReceipts.find((x) => x.id === g.receipt_id);
      if (!r) continue;
      try { await f.updateReceipt(g.receipt_id, { linked_transaction_ids: g.transaction_ids, version: r.version }); linked++; }
      catch { failed++; }
    }
    await f.load();
    matchDialog.value = false;
    if (failed) error(t('invoices.match_some_failed', { failed: String(failed) }));
    else success(t('invoices.match_applied', { count: String(linked) }));
  } finally { matchApplying.value = false; }
}

// ---- Invoice <-> bank transaction auto-assign (the invoice-side mirror of the
// receipt matcher above: links an issued invoice to the incoming payment that
// settled it, and marks it paid — or, for an invoice already marked paid on
// import, just backfills the missing link without touching its status). ----
function isInvoiceLinked(id: number): boolean { return f.transactions.some((t) => t.invoice_id === id); }
interface InvReviewRow extends InvoiceMatch { accepted: boolean }
const invMatchDialog = ref(false);
const invMatchBusy = ref(false);
const invMatchApplying = ref(false);
const invMatches = ref<InvReviewRow[]>([]);
function invMatchReasonLabel(reason: string): string {
  return reason === 'number_ref' ? t('invoices.match_reason_number_ref') : t('invoices.match_reason_exact');
}
function runInvoiceAutoMatch() {
  invMatchBusy.value = true;
  try {
    const found = matchInvoices(f.invoices, f.transactions);
    invMatches.value = found.map((m) => ({ ...m, accepted: true }));
    if (!invMatches.value.length) { success(t('invoices.inv_match_none')); return; }
    invMatchDialog.value = true;
  } finally { invMatchBusy.value = false; }
}
async function applyInvoiceMatches() {
  invMatchApplying.value = true;
  let linked = 0; let failed = 0;
  try {
    for (const m of invMatches.value) {
      if (!m.accepted) continue;
      const tx = f.transactions.find((x) => x.id === m.txId);
      const inv = f.invoices.find((x) => x.id === m.invoiceId);
      if (!tx || !inv) continue;
      try {
        await f.updateTransaction(tx.id, { invoice_id: inv.id, invoice_number: inv.number, version: tx.version });
        if (m.markPaid) {
          const account = f.paymentMethods.find((p) => p.id === tx.payment_method_id)?.name ?? null;
          await f.updateInvoice(inv.id, { status: 'paid', paid_at: tx.date, payment_account: account, version: inv.version });
        }
        linked++;
      } catch { failed++; }
    }
    await f.load();
    invMatchDialog.value = false;
    if (failed) error(t('invoices.match_some_failed', { failed: String(failed) }));
    else success(t('invoices.match_done', { n: String(linked) }));
  } finally { invMatchApplying.value = false; }
}

// ---- Projects ----
// List ↔ detail, like partners. The detail view is where a project gets its
// numbers: hand-typed rows in `expenses`, plus bank transactions / receipts
// pointed at it via finance_project_id.
const projectsView = ref<'list' | 'detail'>('list');
const openProjectId = ref<number | null>(null);
const openProjectRec = computed<Project | null>(() => f.projects.find((p) => p.id === openProjectId.value) ?? null);

function openProject(p: Project) { openProjectId.value = p.id; projectsView.value = 'detail'; expPage.value = 1; }
function backToProjects() { projectsView.value = 'list'; openProjectId.value = null; }

// Newest entry first by default: the date the owner typed on the row, not the
// order the rows happen to sit in the JSON column.
const expSort = reactive<{ key: string; dir: SortDir }>({ key: 'date', dir: 'desc' });
function expSortBy(key: string) { toggleSort(expSort, key); expPage.value = 1; }
function expSortValue(row: ProjectExpense, key: string): unknown {
  switch (key) {
    case 'date': return row.date;
    case 'title': return row.title;
    case 'category': return row.category;
    case 'account': return paymentName(row.payment_method_id);
    // Sort by the signed effect, so credits and debits do not interleave by size.
    case 'amount': return row.direction === 'in' ? row.amount : -row.amount;
    default: return null;
  }
}
const projectLedger = computed(() => {
  const rows = normaliseLedger(openProjectRec.value?.expenses);
  const dirMul = expSort.dir === 'asc' ? 1 : -1;
  return rows.sort((a, b) => sortCmp(expSortValue(a, expSort.key), expSortValue(b, expSort.key)) * dirMul);
});
const expPage = ref(1);
const expPerPage = ref(25);
const pagedLedger = computed(() => projectLedger.value.slice((expPage.value - 1) * expPerPage.value, expPage.value * expPerPage.value));
watch([() => projectLedger.value.length, expPerPage], () => {
  expPage.value = Math.min(expPage.value, Math.max(1, Math.ceil(projectLedger.value.length / expPerPage.value)));
});
const projectLedgerTotals = computed<Totals>(() => ledgerTotals(projectLedger.value));
const openProjectOwn = computed<Totals>(() => (openProjectRec.value
  ? ownTotals(openProjectRec.value, f.transactions, f.standaloneReceipts)
  : EMPTY_TOTALS));
const openProjectRolled = computed<Totals>(() => (openProjectRec.value ? rolledFor(openProjectRec.value.id) : EMPTY_TOTALS));
const projectChildren = computed(() => (openProjectId.value == null ? [] : f.projects.filter((p) => p.parent_id === openProjectId.value)));
const projectTxs = computed(() => f.transactions
  .filter((tx) => tx.finance_project_id === openProjectId.value)
  .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? '')));
const projectReceipts = computed(() => f.standaloneReceipts
  .filter((r) => r.finance_project_id === openProjectId.value)
  .sort((a, b) => String(b.date ?? b.created_at ?? '').localeCompare(String(a.date ?? a.created_at ?? ''))));

function newProject() { Object.assign(prjForm, { id: undefined, version: undefined, name: '', parent_id: null, kind: 'business', note: '' }); prjDialog.value = true; }
function newSubProject() {
  Object.assign(prjForm, {
    id: undefined, version: undefined, name: '', parent_id: openProjectId.value,
    // A subproject inherits its parent's business/private nature by default.
    kind: openProjectRec.value?.kind ?? 'business', note: '',
  });
  prjDialog.value = true;
}
function editProject(p: Project) {
  Object.assign(prjForm, { id: p.id, version: p.version, name: p.name, parent_id: p.parent_id, kind: p.kind ?? 'business', note: p.note ?? '' });
  prjDialog.value = true;
}
async function saveProject() {
  saving.value = true;
  try {
    const stored = prjForm.id ? f.projects.find((p) => p.id === prjForm.id) : null;
    const parentChanged = !!stored && (stored.parent_id ?? null) !== (prjForm.parent_id ?? null);
    const body: Record<string, unknown> = { name: prjForm.name, kind: prjForm.kind, note: prjForm.note || null };
    if (prjForm.id) {
      // Reparent through the cycle-guarded move endpoint; a plain update would
      // happily write a parent that loops back into this project's own subtree.
      if (parentChanged) await f.moveProject(prjForm.id, prjForm.parent_id);
      await f.saveProject({ ...body, id: prjForm.id, version: prjForm.version } as Partial<Project>);
    } else {
      await f.saveProject({ ...body, parent_id: prjForm.parent_id } as Partial<Project>);
    }
    prjDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) {
    if (e instanceof VersionConflict) conflict();
    else if (e instanceof ApiError && e.status === 422) error(t('invoices.project_cycle'));
    else error(t('common.error'));
  } finally { saving.value = false; }
}
async function delProject(p: Project) {
  if (!await confirmAsk(t('invoices.project_delete_confirm'), { danger: true })) return;
  try {
    await f.deleteProject(p.id);
    if (openProjectId.value === p.id) backToProjects();
    await f.load(); success(t('common.deleted'));
  } catch { error(t('common.error')); }
}

// ---- Project trash ----
const prjTrashDialog = ref(false);
const trashedProjects = ref<Project[]>([]);
async function openProjectTrash() {
  prjTrashDialog.value = true;
  try { trashedProjects.value = (await f.loadTrash()).projects ?? []; } catch { trashedProjects.value = []; }
}
async function restoreProject(p: Project) {
  try { await f.restoreProject(p.id); trashedProjects.value = trashedProjects.value.filter((x) => x.id !== p.id); await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}
async function forceProject(p: Project) {
  if (!await confirmAsk(t('invoices.project_delete_forever_confirm'), { danger: true })) return;
  try { await f.forceProject(p.id); trashedProjects.value = trashedProjects.value.filter((x) => x.id !== p.id); await f.load(); success(t('common.deleted')); }
  catch { error(t('common.error')); }
}

// ---- Project ledger (hand-typed Ab-/Zubuchungen inside `expenses`) ----
const expDialog = ref(false);
interface ExpForm {
  id: string; direction: 'out' | 'in'; amount: string; date: string;
  title: string; note: string; category: string; payment_method_id: number | null;
}
const expForm = reactive<ExpForm>({ id: '', direction: 'out', amount: '', date: '', title: '', note: '', category: '', payment_method_id: null });

function newExpense(direction: 'out' | 'in') {
  const row = blankExpense(direction, todayYmd());
  Object.assign(expForm, { id: row.id, direction, amount: '', date: row.date ?? todayYmd(), title: '', note: '', category: '', payment_method_id: null });
  expDialog.value = true;
}
function editExpense(row: ProjectExpense) {
  Object.assign(expForm, {
    id: row.id, direction: row.direction, amount: moneyInput(row.amount), date: row.date ?? '',
    title: row.title ?? '', note: row.note ?? '', category: row.category ?? '', payment_method_id: row.payment_method_id,
  });
  expDialog.value = true;
}
/** Persist the whole array — `expenses` is one JSON column, there is no per-row endpoint. */
async function persistLedger(rows: ProjectExpense[]) {
  const p = openProjectRec.value;
  if (!p) return;
  await f.saveProject({ id: p.id, version: p.version, expenses: rows } as Partial<Project>);
  await f.load();
}
async function saveExpense() {
  const amount = parseMoney(expForm.amount);
  if (amount == null || amount === 0) { error(t('invoices.project_amount_required')); return; }
  saving.value = true;
  try {
    const row: ProjectExpense = {
      id: expForm.id || blankExpense(expForm.direction, todayYmd()).id,
      direction: expForm.direction,
      amount: Math.abs(amount),
      date: expForm.date || null,
      title: expForm.title || null,
      note: expForm.note || null,
      category: expForm.category || null,
      payment_method_id: expForm.payment_method_id,
    };
    const rows = projectLedger.value.slice();
    const i = rows.findIndex((r) => r.id === row.id);
    if (i >= 0) rows[i] = row; else rows.push(row);
    await persistLedger(rows);
    expDialog.value = false; success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delExpense(row: ProjectExpense) {
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await persistLedger(projectLedger.value.filter((r) => r.id !== row.id)); success(t('common.deleted')); }
  catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); }
}

// ---- Assigning real bookings/receipts to the open project ----
const assignPrjTxDialog = ref(false);
const assignPrjTxQuery = ref('');
function openAssignProjectTx() { assignPrjTxQuery.value = ''; assignPrjTxDialog.value = true; }
const assignPrjTxCandidates = computed(() => {
  const s = assignPrjTxQuery.value.trim().toLowerCase();
  return f.transactions
    .filter((tx) => tx.finance_project_id !== openProjectId.value)
    .filter((tx) => !s || [tx.counterparty, tx.purpose, tx.booking_text, tx.date, String(tx.amount)]
      .some((v) => String(v ?? '').toLowerCase().includes(s)))
    .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''))
    .slice(0, 100);
});
async function assignTxToProject(tx: BankTransaction) {
  try {
    await f.updateTransaction(tx.id, { finance_project_id: openProjectId.value, version: tx.version });
    assignPrjTxDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); }
}
async function unassignTxFromProject(tx: BankTransaction) {
  try { await f.updateTransaction(tx.id, { finance_project_id: null, version: tx.version }); await f.load(); success(t('common.saved')); }
  catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); }
}

const assignPrjRcDialog = ref(false);
const assignPrjRcQuery = ref('');
function openAssignProjectReceipt() { assignPrjRcQuery.value = ''; assignPrjRcDialog.value = true; }
const assignPrjRcCandidates = computed(() => {
  const s = assignPrjRcQuery.value.trim().toLowerCase();
  return f.standaloneReceipts
    .filter((r) => r.finance_project_id !== openProjectId.value)
    .filter((r) => !s || [r.name, r.category, r.date, r.doc_number, String(r.amount ?? '')]
      .some((v) => String(v ?? '').toLowerCase().includes(s)))
    .sort((a, b) => String(b.date ?? b.created_at ?? '').localeCompare(String(a.date ?? a.created_at ?? '')))
    .slice(0, 100);
});
async function assignReceiptToProject(r: Receipt) {
  try {
    await f.updateReceipt(r.id, { finance_project_id: openProjectId.value, version: r.version });
    assignPrjRcDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); }
}
async function unassignReceiptFromProject(r: Receipt) {
  try { await f.updateReceipt(r.id, { finance_project_id: null, version: r.version }); await f.load(); success(t('common.saved')); }
  catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); }
}

// ---- Invoice PDF generation (client-side, ported from the Blade print pipeline) ----
interface CompanyProfile {
  company_name?: string | null; company_address?: string | null; company_email?: string | null;
  company_phone?: string | null; company_vat_id?: string | null; company_iban?: string | null;
  company_bic?: string | null; company_bank_name?: string | null; company_website?: string | null;
  company_contacts?: { name?: string; role?: string; email?: string; phone?: string }[] | null;
  invoice_accent_color?: string | null; invoice_heading_color?: string | null;
  invoice_template?: string | null; invoice_font?: string | null; invoice_footer_text?: string | null;
  invoice_payment_terms_text?: string | null; invoice_payment_methods?: string | null;
  small_business?: boolean; has_logo?: boolean;
}

const printCompany = reactive<PrintCompany>({
  name: '', address: '', email: '', phone: '', vat_id: '', iban: '', bic: '', bank_name: '',
  website: '', contacts: [], logo: null, accent: '#111827', heading: '#6b7280',
  template: 'editorial', font: '', footer_text: '', payment_terms_text: '', payment_methods: '',
  small_business: false, currency: 'EUR',
});
const pdfBusy = ref(false);
const printingId = ref<number | null>(null);
const printInv = ref<PrintInvoice | null>(null);
const printQr = ref('');

// The current invoice's template (schlicht is rendered with the elegant sheet).
const printTpl = computed(() => (printCompany.template === 'schlicht' ? 'elegant' : (printCompany.template || 'editorial')));
const printTotals = computed(() => printComputeTotals(printInv.value));
const printVatRates = computed(() => printVatRatesOf(printInv.value, printCompany.small_business));

// The viewer's own name(s)/company — passed to the OCR recogniser so a merged
// multi-column letterhead never misreads the document's OWNER as its own merchant.
const ownNames = computed(() => [printCompany.name, ...printCompany.contacts.map((c) => c.name ?? '')].filter(Boolean));

/** The owner-entered Sachkonto for a category name, if any managed category carries one. */
function catAccountNo(name: string | null | undefined): string {
  if (!name) return '';
  return f.financeCategories.find((c) => c.name === name)?.account_no ?? '';
}
/** A linked partner's VAT-ID — the recogniser writes it onto the PARTNER (autoPartner),
 * not per-receipt, since it's a vendor attribute; shown here read-only for visibility. */
function partnerVatId(partnerId: number | null | undefined): string {
  if (partnerId == null) return '';
  return f.partners.find((p) => p.id === partnerId)?.vat_id ?? '';
}

async function loadPrintCompany() {
  try {
    const res = await api.get<{ company: CompanyProfile }>('/api/v1/company');
    const c = res.company ?? {};
    Object.assign(printCompany, {
      name: c.company_name ?? '', address: c.company_address ?? '', email: c.company_email ?? '',
      phone: c.company_phone ?? '', vat_id: c.company_vat_id ?? '', iban: c.company_iban ?? '',
      bic: c.company_bic ?? '', bank_name: c.company_bank_name ?? '', website: c.company_website ?? '',
      contacts: Array.isArray(c.company_contacts) ? c.company_contacts : [],
      accent: c.invoice_accent_color || '#111827', heading: c.invoice_heading_color || '#6b7280',
      template: c.invoice_template || 'editorial', font: c.invoice_font || '',
      footer_text: c.invoice_footer_text ?? '', payment_terms_text: c.invoice_payment_terms_text ?? '',
      payment_methods: c.invoice_payment_methods ?? '', small_business: !!c.small_business, currency: 'EUR',
    });
    if (c.has_logo) void loadPrintLogo();
  } catch { /* ignore — printing still works without company defaults */ }
}
async function loadPrintLogo() {
  try {
    const res = await fetch(api.streamUrl('/api/v1/company/logo'));
    if (!res.ok) return;
    const blob = await res.blob();
    printCompany.logo = await new Promise<string>((resolve, reject) => {
      const fr = new FileReader();
      fr.onload = () => resolve(String(fr.result));
      fr.onerror = () => reject(fr.error);
      fr.readAsDataURL(blob);
    });
  } catch { /* ignore */ }
}

const printLang = (): 'de' | 'en' => (getActiveLanguage() === 'en' ? 'en' : 'de');
const PL_FALLBACK: Record<'de' | 'en', Record<string, string>> = {
  de: { print_title_credit: 'Gutschrift', thanks_line: 'Vielen Dank für Ihren Auftrag.', pay_until_line: 'Bitte überweisen Sie den Betrag bis zum :date.' },
  en: { print_title_credit: 'Credit note', thanks_line: 'Thank you for your business.', pay_until_line: 'Please transfer the amount by :date.' },
};
// Print label: the invoices.* key, with a fallback for the few print keys not in the lang files.
function pl(key: string): string {
  const full = 'invoices.' + key;
  const s = t(full);
  if (s && s !== full && s !== key) return s;
  return PL_FALLBACK[printLang()][key] ?? key;
}
function docTitle(inv: PrintInvoice | null): string { return pl(inv?.type === 'credit_note' ? 'print_title_credit' : 'print_title'); }
function statusLabelP(s: string): string { return t('invoices.status_' + s); }

function readCustomer(c: Record<string, unknown> | null | undefined) {
  const o = (c ?? {}) as Record<string, unknown>;
  const str = (v: unknown): string => (v == null ? '' : String(v));
  return { name: str(o.name), attn: str(o.attn), address: str(o.address), email: str(o.email), vatId: str(o.vatId ?? o.vat_id) };
}
function toPrintLine(l: InvoiceLine & { unit?: string }): PrintLine {
  return { desc: String(l.desc ?? ''), qty: Number(l.qty) || 0, unit: l.unit ? String(l.unit) : '', unitPrice: Number(l.unitPrice) || 0, vatRate: Number(l.vatRate) || 0 };
}
function buildPrintInvoice(src: Partial<Invoice>, lineRows: (InvoiceLine & { unit?: string })[], customerName?: string): PrintInvoice {
  const cust = readCustomer(src.customer as Record<string, unknown> | null);
  if (customerName != null) cust.name = customerName;
  return {
    number: src.number ?? null,
    status: src.status ?? 'draft',
    type: src.type ?? 'invoice',
    issueDate: String(src.issue_date ?? '').slice(0, 10),
    dueDate: String(src.due_date ?? '').slice(0, 10),
    currency: src.currency || 'EUR',
    lang: printLang(),
    customer: cust,
    lines: (lineRows ?? []).map(toPrintLine),
    note: src.note ?? '',
    footer: '',
    imported: !!src.imported,
    gross: src.gross ?? null,
    vatRate: src.vat_rate ?? null,
    discountType: null, discountValue: null, skontoPercent: null, skontoDays: null,
  };
}

async function generateAndUpload(snap: PrintInvoice, id: number) {
  pdfBusy.value = true;
  printingId.value = id;
  try {
    await ensureInvoiceFonts();
    printQr.value = await epcQrDataUrl(snap, printCompany, printComputeTotals(snap));
    printInv.value = snap;
    await nextTick();
    await new Promise((r) => setTimeout(r, 80));
    const node = document.getElementById('spa-invoice-print');
    if (!node) { printInv.value = null; return; }
    const blob = await renderInvoicePdfBlob(node);
    printInv.value = null;
    const fd = new FormData();
    fd.append('file', new File([blob], `${snap.number || 'invoice'}.pdf`, { type: 'application/pdf' }));
    await api.upload(`/api/v1/finance/invoices/${id}/pdf`, fd);
    await f.load();
    success(t('common.saved'));
    openPreview(f.invoicePdfUrl(id), `${snap.number || 'invoice'}.pdf`, 'application/pdf');
  } catch { printInv.value = null; error(t('common.error')); }
  finally { pdfBusy.value = false; printingId.value = null; }
}
// Generate + upload the PDF for a list-row invoice.
async function doPrint(inv: Invoice) {
  await generateAndUpload(buildPrintInvoice(inv, (Array.isArray(inv.lines) ? inv.lines : []) as InvoiceLine[]), inv.id);
}
// Generate + upload the PDF for the invoice open in the editor (uses on-screen edits).
async function doPrintDraft() {
  if (!draft.value?.id) return;
  await generateAndUpload(buildPrintInvoice(draft.value, lines.value, custName_.value), draft.value.id);
}

</script>

<!-- Editorial invoice-template styles (scope-prefixed to the off-screen print sheet;
     rasterised by html2canvas). Ported from resources/views/invoices/index.blade.php. -->
<style>
#spa-invoice-print.has-inv-font, #spa-invoice-print.has-inv-font * { font-family: var(--inv-font) !important; }
#spa-invoice-print .ie { font-family:'Inter','SF Pro Text',system-ui,-apple-system,sans-serif; color:#313a4a; background:#fff; font-size:10px; line-height:1.55; --ink:#0b1220; --body:#313a4a; --soft:#5d6878; --faint:#97a1b1; --hair:#e6eaef; --wash:#f6f8fb; }
#spa-invoice-print .ie .num { font-variant-numeric:tabular-nums; }
#spa-invoice-print .ie-page { padding:46px 56px 78px; }
#spa-invoice-print .ie-header { display:flex; justify-content:space-between; align-items:flex-end; padding-bottom:20px; margin-bottom:26px; border-bottom:1px solid var(--ink); position:relative; }
#spa-invoice-print .ie-header::after { content:""; position:absolute; left:0; bottom:-1px; width:96px; height:2px; background:var(--ac); }
#spa-invoice-print .ie-brand { display:flex; align-items:center; gap:16px; }
#spa-invoice-print .ie-logo img { height:52px; display:block; }
#spa-invoice-print .ie-co-name { font-size:14px; font-weight:600; color:var(--ink); letter-spacing:-0.2px; }
#spa-invoice-print .ie-doc-meta { text-align:right; }
#spa-invoice-print .ie-doc-kind { font-size:9px; font-weight:600; letter-spacing:3.5px; text-transform:uppercase; color:var(--faint); }
#spa-invoice-print .ie-doc-no { font-size:28px; font-weight:600; color:var(--ink); letter-spacing:-0.8px; margin-top:6px; line-height:1; }
#spa-invoice-print .ie-meta-grid { display:grid; grid-template-columns:repeat(3,1fr); margin-bottom:26px; border-top:1px solid var(--hair); border-bottom:1px solid var(--hair); }
#spa-invoice-print .ie-meta-cell { padding:11px 18px 11px 0; border-right:1px solid var(--hair); }
#spa-invoice-print .ie-meta-cell:last-child { border-right:none; padding-right:0; }
#spa-invoice-print .ie-meta-cell:not(:first-child) { padding-left:18px; }
#spa-invoice-print .ie-m-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); margin-bottom:5px; }
#spa-invoice-print .ie-m-val { font-size:11px; font-weight:600; color:var(--ink); font-variant-numeric:tabular-nums; }
#spa-invoice-print .ie-pill { display:inline-block; padding:2px 10px; border-radius:2px; font-size:8px; font-weight:700; letter-spacing:1px; text-transform:uppercase; background:var(--ink); color:#fff; }
#spa-invoice-print .ie-pill.ie-paid { background:#0f7a4d; }
#spa-invoice-print .ie-pill.ie-final { background:#c07d1a; }
#spa-invoice-print .ie-pill.ie-draft { background:var(--faint); }
#spa-invoice-print .ie-parties { display:grid; grid-template-columns:1fr 1fr; gap:56px; margin-bottom:30px; }
#spa-invoice-print .ie-p-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.6px; text-transform:uppercase; color:var(--faint); padding-bottom:8px; margin-bottom:14px; border-bottom:1px solid var(--hair); }
#spa-invoice-print .ie-p-name { font-size:15px; font-weight:600; color:var(--ink); margin-bottom:8px; letter-spacing:-0.2px; line-height:1.25; }
#spa-invoice-print .ie-p-line { font-size:9.5px; color:var(--soft); line-height:1.85; }
#spa-invoice-print .ie-tbl-wrap { margin-bottom:22px; }
#spa-invoice-print .ie table { width:100%; border-collapse:collapse; }
#spa-invoice-print .ie thead th { padding:9px 0; font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); text-align:left; border-bottom:1.5px solid var(--ink); border-top:1px solid var(--hair); }
#spa-invoice-print .ie thead th.r { text-align:right; }
#spa-invoice-print .ie thead th:not(:first-child) { padding-left:16px; }
#spa-invoice-print .ie tbody tr { page-break-inside:avoid; }
#spa-invoice-print .ie tbody td { padding:11px 0; vertical-align:top; border-bottom:1px solid var(--hair); font-size:10px; }
#spa-invoice-print .ie tbody td:not(:first-child) { padding-left:16px; }
#spa-invoice-print .ie td.r { text-align:right; font-variant-numeric:tabular-nums; }
#spa-invoice-print .ie-d-title { font-weight:600; color:var(--ink); font-size:10.5px; line-height:1.45; }
#spa-invoice-print .ie-amt { font-weight:600; color:var(--ink); }
#spa-invoice-print .ie-sum-area { display:flex; justify-content:flex-end; margin-bottom:26px; }
#spa-invoice-print .ie-sum { width:340px; }
#spa-invoice-print .ie-sr { display:flex; justify-content:space-between; padding:8px 0; font-size:10px; border-bottom:1px solid var(--hair); }
#spa-invoice-print .ie-sr .l { color:var(--soft); }
#spa-invoice-print .ie-sr .v { font-variant-numeric:tabular-nums; color:var(--ink); font-weight:500; }
#spa-invoice-print .ie-grand { display:flex; justify-content:space-between; align-items:baseline; padding:14px 0 8px; border-top:2px solid var(--ink); margin-top:6px; }
#spa-invoice-print .ie-gl { font-size:9.5px; font-weight:600; text-transform:uppercase; letter-spacing:2.4px; color:var(--ink); }
#spa-invoice-print .ie-gv { font-size:26px; font-weight:600; color:var(--ink); letter-spacing:-0.6px; font-variant-numeric:tabular-nums; line-height:1; }
#spa-invoice-print .ie-notes-area { margin-bottom:20px; }
#spa-invoice-print .ie-n-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); margin-bottom:10px; }
#spa-invoice-print .ie-note-text { font-size:10px; color:var(--soft); line-height:1.7; max-width:480px; white-space:pre-line; }
#spa-invoice-print .ie-notice { font-size:8.5px; color:var(--faint); margin-bottom:22px; line-height:1.65; max-width:520px; white-space:pre-line; }
#spa-invoice-print .ie-pay-area { margin-top:26px; }
#spa-invoice-print .ie-pay-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:32px; padding-top:18px; border-top:1px solid var(--hair); }
#spa-invoice-print .ie-pc-lbl { font-size:7.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:var(--faint); margin-bottom:8px; }
#spa-invoice-print .ie-pc-val { font-size:9.5px; color:var(--ink); line-height:1.75; font-variant-numeric:tabular-nums; white-space:pre-line; }
#spa-invoice-print .ie-foot { text-align:center; font-size:7.5px; color:var(--faint); padding:14px 64px; line-height:1.8; border-top:1px solid var(--hair); background:#fff; letter-spacing:0.2px; }
#spa-invoice-print .ie-foot strong { color:var(--ink); font-weight:600; }
</style>
