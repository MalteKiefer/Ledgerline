<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('messages.nav.finance') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <!-- In-page left submenu (like Profile / Settings) -->
      <Card body-class="p-0" class="w-full flex-shrink-0 self-start md:w-64">
        <button
          v-for="tt in sections" :key="tt"
          class="flex w-full items-center gap-3 border-b border-[var(--ll-border)] px-4 py-3 last:border-0 hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="tab === tt ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="go(tt)"
        >
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" :class="tab === tt ? 'bg-primary-500/15' : 'bg-black/[0.05] dark:bg-white/10'">
            <Icon :name="secIcon[tt]" :size="20" />
          </span>
          <span class="flex-1 text-left text-sm font-medium">{{ t('invoices.tab_' + tt) }}</span>
          <Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" />
        </button>
      </Card>

      <div class="min-w-0 flex-1">
    <!-- Dashboard -->
    <div v-show="tab === 'dashboard'">
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
    </div>

    <!-- Invoices -->
    <Card v-show="tab === 'invoices'" :body-class="'p-0'">
      <template #header>
        <TextField v-model="q" :placeholder="t('common.search')" icon="search" class="w-full sm:w-72" />
      </template>
      <template #actions>
        <Btn variant="solid" size="sm" icon="add" @click="newInvoice">{{ t('invoices.new') }}</Btn>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="px-4 py-2.5 font-medium">{{ t('invoices.col_number') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('invoices.customer') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('invoices.issue_date') }}</th>
              <th class="px-4 py-2.5 text-right font-medium">{{ t('invoices.gross') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('common.status') }}</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredInvoices" :key="item.id" class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5">
              <td class="px-4 py-2.5 font-mono">{{ item.number || '—' }}</td>
              <td class="px-4 py-2.5">{{ custName(item) }}</td>
              <td class="px-4 py-2.5">{{ fmtDate(item.issue_date) }}</td>
              <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ money(Number(item.gross ?? 0)) }}</td>
              <td class="px-4 py-2.5"><Badge :tone="statusTone(item.status)">{{ t('invoices.status_' + item.status) }}</Badge></td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-0.5">
                  <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editInvoice(item)" />
                  <Btn variant="ghost" size="sm" icon="print" :title="t('invoices.print')" :loading="pdfBusy && printingId === item.id" @click="doPrint(item)" />
                  <Btn v-if="item.number" tag="a" variant="ghost" size="sm" icon="picture_as_pdf" :href="f.invoicePdfUrl(item.id)" target="_blank" />
                  <Btn v-if="item.number" variant="ghost" size="sm" icon="mail" :title="t('invoices.email_send')" @click="doEmail(item)" />
                  <Btn v-if="item.number" variant="ghost" size="sm" icon="gavel" :title="t('invoices.dun_send')" @click="doDun(item)" />
                  <Btn v-if="item.number && item.type !== 'credit_note'" variant="ghost" size="sm" icon="cancel" :title="t('invoices.storno')" @click="doStorno(item)" />
                  <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delInvoice(item)" />
                </div>
              </td>
            </tr>
            <tr v-if="!filteredInvoices.length"><td colspan="6" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
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
    <Card v-show="tab === 'bank'" :title="t('invoices.tab_bank')" :body-class="'p-0'">
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
              <th class="px-4 py-2.5 font-medium">{{ t('common.date') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('invoices.tx_counterparty') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('invoices.tx_purpose') }}</th>
              <th class="px-4 py-2.5 text-right font-medium">{{ t('invoices.gross') }}</th>
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
                <button class="relative mr-1 inline-flex items-center rounded p-1.5 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('invoices.tx_receipts')" @click="openTxReceipts(tx)">
                  <Icon name="attach_file" :size="18" />
                  <span v-if="(tx.receipts?.length ?? 0)" class="ml-0.5 text-xs tabular-nums">{{ tx.receipts?.length }}</span>
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

    <!-- Receipts -->
    <Card v-show="tab === 'receipts'" :title="t('invoices.receipts_title')" :body-class="'p-0'">
      <template #actions>
        <div class="flex items-center gap-2">
          <Btn variant="ghost" size="sm" icon="sell" @click="catDialog = true">{{ t('invoices.categories') }}</Btn>
          <Btn variant="solid" size="sm" icon="add" @click="newReceipt">{{ t('common.add') }}</Btn>
        </div>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="px-4 py-2.5 font-medium">{{ t('common.name') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('common.date') }}</th>
              <th class="px-4 py-2.5 text-right font-medium">{{ t('invoices.gross') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('invoices.receipt_category') }}</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in f.standaloneReceipts" :key="item.id" class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5">
              <td class="px-4 py-2.5">
                <div class="flex items-center gap-2">
                  <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="receipt_long" :size="18" /></span>
                  <span>{{ item.name }}</span>
                </div>
              </td>
              <td class="px-4 py-2.5">{{ fmtDate(item.date ?? item.created_at) }}</td>
              <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ item.amount != null ? money(Number(item.amount)) : '—' }}</td>
              <td class="px-4 py-2.5">
                <Badge v-if="item.category" tone="gray">{{ item.category }}</Badge>
                <span v-else class="text-[var(--ll-muted)]">—</span>
              </td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-0.5">
                  <Btn tag="a" variant="ghost" size="sm" icon="open_in_new" :href="f.receiptFileUrl(item.id)" target="_blank" />
                  <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editReceipt(item)" />
                  <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delReceipt(item)" />
                </div>
              </td>
            </tr>
            <tr v-if="!f.standaloneReceipts.length"><td colspan="5" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('invoices.receipts_none') }}</td></tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Projects -->
    <Card v-show="tab === 'projects'" :title="t('invoices.tab_projects')">
      <template #actions><Btn variant="solid" size="sm" icon="add" @click="newProject">{{ t('invoices.project_add') }}</Btn></template>
      <div class="divide-y divide-[var(--ll-border)]">
        <div v-for="row in projectRows" :key="row.p.id" class="flex items-center gap-3 py-2.5" :style="{ paddingLeft: (row.depth * 28) + 'px' }">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary-500/12 text-primary-600 dark:text-primary-300"><Icon name="account_tree" :size="18" /></span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium">{{ row.p.name }}</div>
            <div v-if="row.p.note" class="truncate text-xs text-[var(--ll-muted)]">{{ row.p.note }}</div>
          </div>
          <Btn variant="ghost" size="sm" icon="edit" :title="t('common.edit')" @click="editProject(row.p)" />
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delProject(row.p)" />
        </div>
        <div v-if="!f.projects.length" class="py-8 text-center text-[var(--ll-muted)]">{{ t('invoices.project_empty') }}</div>
      </div>
    </Card>

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
                <th class="px-4 py-2.5 font-medium">{{ t('invoices.partner_name') }}</th>
                <th class="hidden px-4 py-2.5 font-medium md:table-cell">{{ t('invoices.partner_contact_person') }}</th>
                <th class="hidden px-4 py-2.5 font-medium lg:table-cell">{{ t('invoices.partner_email') }}</th>
                <th class="hidden px-4 py-2.5 font-medium lg:table-cell">{{ t('invoices.partner_phone') }}</th>
                <th class="hidden px-4 py-2.5 font-medium md:table-cell">{{ t('invoices.partner_vat') }}</th>
                <th class="px-4 py-2.5 text-right font-medium">{{ t('invoices.partner_links') }}</th>
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
              <div v-if="openPartnerRec.url"><dt class="text-xs text-[var(--ll-muted)]">{{ t('invoices.partner_url') }}</dt><dd><a :href="openPartnerRec.url" target="_blank" rel="noopener" class="break-all text-primary-600 hover:underline dark:text-primary-300">{{ openPartnerRec.url }}</a></dd></div>
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

    <!-- Stats / Reports -->
    <div v-show="tab === 'stats'" class="space-y-4">
      <div class="max-w-xs">
        <Select
          :label="t('invoices.euer_year')"
          :model-value="String(statsYear)"
          :options="years.map((y) => ({ title: String(y), value: y }))"
          @update:model-value="onStatsYear"
        />
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

      <!-- Monthly revenue -->
      <Card :title="t('invoices.stat_monthly')">
        <div v-for="m in months" :key="m.month" class="flex items-center gap-3 py-1 text-sm">
          <span class="w-11 shrink-0 text-[var(--ll-muted)]">{{ monthLabel(m.month) }}</span>
          <div class="h-2 flex-1 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
            <div class="h-full rounded-full bg-primary-500" :style="{ width: monthPct(m.net) + '%' }" />
          </div>
          <span class="w-28 shrink-0 text-right font-mono tabular-nums">{{ money(Number(m.net ?? 0)) }}</span>
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

    <!-- Invoice editor -->
    <Modal v-model="invDialog" :title="draft?.id ? (draft?.number || t('invoices.new')) : t('invoices.new')" width="820px">
      <div v-if="draft">
        <!-- header actions + status -->
        <div class="mb-3 flex items-center gap-1">
          <Select v-if="draft.id && !draft.imported" v-model="draft.status" :options="statusOptions" class="w-40" />
          <Badge v-else-if="draft.status" :tone="statusTone(draft.status)">{{ t('invoices.status_' + draft.status) }}</Badge>
          <div v-if="draft.id && draft.number" class="ml-auto flex items-center gap-0.5">
            <Btn variant="ghost" size="sm" icon="mail" :title="t('invoices.email_send')" @click="doEmail(draft as Invoice)" />
            <Btn variant="ghost" size="sm" icon="gavel" :title="t('invoices.dun_send')" @click="doDun(draft as Invoice)" />
            <Btn v-if="draft.type !== 'credit_note'" variant="ghost" size="sm" icon="cancel" :title="t('invoices.storno')" @click="doStorno(draft as Invoice)" />
          </div>
        </div>

        <div v-if="draft.imported" class="mb-3 rounded-lg bg-blue-500/10 px-3 py-2 text-sm text-blue-600 dark:text-blue-400">{{ t('invoices.status_final') }}</div>

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
            <TextField v-if="discountType_" type="number" :label="t('invoices.discount_value')" :model-value="draft.discount_value ?? ''" @update:model-value="draft.discount_value = $event === '' ? null : Number($event)" />
            <TextField type="number" :label="t('invoices.skonto_percent')" :model-value="draft.skonto_percent ?? ''" @update:model-value="draft.skonto_percent = $event === '' ? null : Number($event)" />
            <TextField type="number" :label="t('invoices.skonto_days')" :model-value="draft.skonto_days ?? ''" @update:model-value="draft.skonto_days = $event === '' ? null : Number($event)" />
          </div>

          <div class="mb-1 mt-4 text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.line_desc') }}</div>
          <div v-for="(l, i) in lines" :key="i" class="mb-1.5 flex items-center gap-2">
            <TextField v-model="l.desc" :placeholder="t('invoices.line_desc')" class="flex-1" />
            <TextField type="number" class="w-20" :model-value="l.qty" @update:model-value="l.qty = Number($event)" />
            <TextField type="number" class="w-28" :model-value="l.unitPrice" @update:model-value="l.unitPrice = Number($event)" />
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

    <!-- Bank transaction editor -->
    <Modal v-model="txDialog" :title="txForm.id ? t('common.edit') : t('common.add')" width="480px">
      <div class="space-y-3">
        <Select v-model.number="txForm.payment_method_id" :label="t('invoices.tab_payments')" :options="f.paymentMethods.map((p) => ({ title: p.name, value: p.id }))" />
        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="txForm.date" :label="t('common.date')" type="date" />
          <TextField v-model="txForm.amount" :label="t('invoices.gross')" type="number" />
        </div>
        <TextField v-model="txForm.counterparty" :label="t('invoices.tx_counterparty')" />
        <div class="grid grid-cols-2 gap-3">
          <TextField v-model="txForm.counterparty_iban" label="IBAN" />
          <TextField v-model="txForm.bic" label="BIC" />
        </div>
        <TextField v-model="txForm.purpose" :label="t('invoices.tx_purpose')" />
        <TextField v-model="txForm.booking_text" :label="t('invoices.tx_booking_text')" />
        <Select v-model="txForm.vat_cat" :label="t('invoices.tx_vat')" :options="vatCatItems" />
      </div>
      <template #footer>
        <Btn variant="ghost" @click="txDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveTx">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Bank transaction receipts (reconcile) -->
    <Modal v-model="txRecDialog" :title="t('invoices.tx_receipts')" width="480px">
      <div v-if="txRecTx" class="space-y-2">
        <div v-for="r in (txRecTx.receipts ?? [])" :key="r.id" class="flex items-center gap-2 rounded-lg border border-[var(--ll-border)] px-3 py-2">
          <Icon name="receipt" :size="18" class="shrink-0 text-[var(--ll-muted)]" />
          <span class="flex-1 truncate text-sm">{{ r.name }}</span>
          <Btn variant="ghost" size="sm" icon="open_in_new" :title="t('common.open')" @click="openTxReceipt(r)" />
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600 dark:text-red-400" :title="t('common.delete')" @click="delTxReceipt(r)" />
        </div>
        <div v-if="!(txRecTx.receipts?.length ?? 0)" class="py-4 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</div>
        <div class="mt-3 border-t border-[var(--ll-border)] pt-3">
          <input ref="txRecInput" type="file" accept=".pdf,image/*" class="hidden" @change="onTxReceiptFile">
          <Btn variant="soft" icon="upload" :loading="txRecBusy" @click="txRecInput?.click()">{{ t('invoices.tx_receipt_attach') }}</Btn>
        </div>
      </div>
      <template #footer>
        <Btn variant="ghost" @click="txRecDialog = false">{{ t('common.close') }}</Btn>
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
          <span class="flex-1 truncate text-sm">{{ c.name }}</span>
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
          <TextField v-model="partnerForm.hourly_rate" :label="t('invoices.partner_hourly_rate')" type="number" inputmode="decimal" placeholder="0.00" />
          <Select v-model="partnerForm.currency" :label="t('invoices.partner_currency')" :options="currencyOptions" />
        </div>

        <TextField v-model="partnerForm.vat_id" :label="t('invoices.partner_vat')" placeholder="DE…" />

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
          <input
            type="file" accept="application/pdf,image/*"
            class="block w-full text-sm text-[var(--ll-fg)] file:mr-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 hover:file:bg-primary-500/15 dark:file:text-primary-300"
            @change="rFile = ($event.target as HTMLInputElement).files?.[0] ?? null"
          >
        </label>
        <TextField v-model="rForm.name" :label="t('invoices.receipt_rename')" />
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
        <TextField v-model="rForm.vat" :label="t('invoices.vat')" />
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
                <td style="padding:9px 8px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ pmoney(l.unitPrice, printInv.currency, printInv.lang) }}</td>
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
              <td style="padding:9px 6px; text-align:right; white-space:nowrap; vertical-align:top;" class="tabular-nums">{{ pmoney(l.unitPrice, printInv.currency, printInv.lang) }}</td>
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
                  <td class="r num">{{ pmoney(l.unitPrice, printInv.currency, printInv.lang) }}</td>
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
                <td style="border:1px solid #cfcfcf; padding:6px 7px; text-align:right; vertical-align:top; white-space:nowrap;" class="tabular-nums">{{ pmoney(l.unitPrice, printInv.currency, printInv.lang) }}</td>
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
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { fmtDate as libDate } from '@spa/lib/datetime';
import { useRoute, useRouter } from 'vue-router';
import { trans as t, getActiveLanguage } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useFinanceStore, type Invoice, type InvoiceLine, type Partner, type PaymentMethod, type Project, type Receipt, type BankTransaction, type FinanceCategory, type DuplicateGroup, type CategorySuggestion } from '@spa/stores/finance';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { api, VersionConflict } from '@spa/api/client';
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
const VALID = ['dashboard', 'invoices', 'payments', 'bank', 'receipts', 'projects', 'partners', 'stats'];
const tab = computed(() => {
  const s = String(route.params.section || 'dashboard');
  return VALID.includes(s) ? s : 'dashboard';
});
function go(v: unknown) { router.push(`/finance/${String(v)}`); }

// In-page left submenu sections (mirrors the Profile/Settings hub layout).
const sections = ['dashboard', 'invoices', 'payments', 'bank', 'receipts', 'projects', 'partners', 'stats'] as const;
const secIcon: Record<string, string> = {
  dashboard: 'space_dashboard', invoices: 'receipt_long', payments: 'account_balance_wallet',
  bank: 'account_balance', receipts: 'receipt', projects: 'account_tree', partners: 'groups', stats: 'insights',
};
const q = ref('');

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
interface RForm { id?: number; version?: number; name: string; category: string; tags: string[]; vat: string; note: string; partner_id: number | null }
const rForm = reactive<RForm>({ name: '', category: '', tags: [], vat: '', note: '', partner_id: null });

// Project form
const prjDialog = ref(false);
interface PrjForm { id?: number; version?: number; name: string; parent_id: number | null; note: string }
const prjForm = reactive<PrjForm>({ name: '', parent_id: null, note: '' });

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
  } catch { /* ignore */ }
}
// Duplicate + category-suggestion read-outs (stats tab).
const dupInvoices = ref<DuplicateGroup[]>([]);
const dupTransactions = ref<DuplicateGroup[]>([]);
const catSuggestions = ref<CategorySuggestion[]>([]);
function invoiceLabel(id: number) { const i = f.invoices.find((x) => x.id === id); return i?.number || `#${id}`; }
function txLabel(id: number) { const x = f.transactions.find((tx) => tx.id === id); return x ? `${x.date ?? ''} ${money(x.amount)}`.trim() : `#${id}`; }
function openInvoiceById(id: number) { const i = f.invoices.find((x) => x.id === id); if (i) { go('invoices'); editInvoice(i); } }
function onStatsYear(v: unknown) { void loadReports(Number(v)); }

const fmt = computed(() => new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: 'EUR' }));
function money(n: number) { return fmt.value.format(n || 0); }
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

const filteredInvoices = computed(() => {
  const s = q.value.trim().toLowerCase();
  if (!s) return f.invoices;
  return f.invoices.filter((i) => (i.number ?? '').toLowerCase().includes(s) || custName(i).toLowerCase().includes(s));
});

const isLocked = computed(() => !!(draft.value?.imported || (draft.value?.number && draft.value?.status !== 'draft')));
const totals = computed(() => {
  let net = 0; let vat = 0;
  for (const l of lines.value) { const ln = (l.qty || 0) * (l.unitPrice || 0); net += ln; vat += ln * ((l.vatRate || 0) / 100); }
  net = Math.round(net * 100) / 100; vat = Math.round(vat * 100) / 100;
  return { net, vat, gross: Math.round((net + vat) * 100) / 100 };
});

// Indented project tree (roots + children by parent_id; unknown parents surface as roots).
const projectRows = computed<{ p: Project; depth: number }[]>(() => {
  const all = f.projects;
  const byParent = new Map<number, Project[]>();
  const ids = new Set(all.map((p) => p.id));
  for (const p of all) {
    const key = p.parent_id != null && ids.has(p.parent_id) ? p.parent_id : 0;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key)!.push(p);
  }
  const out: { p: Project; depth: number }[] = [];
  const walk = (parent: number, depth: number) => {
    for (const p of byParent.get(parent) ?? []) { out.push({ p, depth }); walk(p.id, depth + 1); }
  };
  walk(0, 0);
  return out;
});
const parentOptions = computed(() => f.projects.filter((p) => p.id !== prjForm.id).map((p) => ({ id: p.id, name: p.name })));
const partnerOptions = computed(() => f.partners.map((p) => ({ id: p.id, name: p.name })));

function conflict() { void f.load(); error(t('common.error')); }

function newInvoice() {
  draft.value = { status: 'draft', currency: 'EUR', issue_date: new Date().toISOString().slice(0, 10), customer: {}, lines: [] };
  lines.value = [{ desc: '', qty: 1, unitPrice: 0, vatRate: 19 }];
  loadCustomerFields(null);
  invDialog.value = true;
}
function editInvoice(i: Invoice) {
  draft.value = { ...i };
  lines.value = Array.isArray(i.lines) ? i.lines.map((l) => ({ ...l })) : [];
  loadCustomerFields(i.customer);
  invDialog.value = true;
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
const bankTransactions = computed(() =>
  bankAccount.value ? f.transactions.filter((x) => x.payment_method_id === bankAccount.value) : f.transactions,
);
const vatCatItems = computed(() => [
  { title: '—', value: '' },
  { title: '19%', value: '19' }, { title: '7%', value: '7' }, { title: '0%', value: '0' },
  { title: t('invoices.vatcat_private'), value: 'private' },
]);

interface TxForm {
  id?: number; version?: number; payment_method_id: number | null;
  date: string; amount: string; counterparty: string; counterparty_iban: string;
  bic: string; purpose: string; booking_text: string; vat_cat: string;
}
function blankTx(): TxForm {
  return {
    payment_method_id: bankAccount.value || (f.paymentMethods[0]?.id ?? null),
    date: new Date().toISOString().slice(0, 10), amount: '', counterparty: '', counterparty_iban: '',
    bic: '', purpose: '', booking_text: '', vat_cat: '',
  };
}
const txForm = reactive<TxForm>(blankTx());
const txDialog = ref(false);
function newTx() { Object.assign(txForm, blankTx()); txDialog.value = true; }
function editTx(tx: BankTransaction) {
  Object.assign(txForm, {
    id: tx.id, version: tx.version, payment_method_id: tx.payment_method_id,
    date: tx.date ?? '', amount: String(tx.amount ?? ''), counterparty: tx.counterparty ?? '',
    counterparty_iban: tx.counterparty_iban ?? '', bic: tx.bic ?? '', purpose: tx.purpose ?? '',
    booking_text: tx.booking_text ?? '', vat_cat: tx.vat_cat ?? '',
  });
  txDialog.value = true;
}
async function saveTx() {
  saving.value = true;
  try {
    const body = {
      payment_method_id: txForm.payment_method_id, date: txForm.date, amount: Number(txForm.amount),
      counterparty: txForm.counterparty, counterparty_iban: txForm.counterparty_iban, bic: txForm.bic,
      purpose: txForm.purpose, booking_text: txForm.booking_text, vat_cat: txForm.vat_cat || null,
      version: txForm.version,
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

// CSV bank-statement import: header row + comma/semicolon delimiter; maps date/amount/counterparty/purpose.
async function onBankCsv(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  const acct = bankAccount.value || f.paymentMethods[0]?.id;
  if (!acct) { error(t('invoices.tx_import_no_account')); return; }
  try {
    const rows = parseBankCsv(await file.text());
    if (!rows.length) { error(t('invoices.tx_import_empty')); return; }
    const res = await f.bulkTransactions(acct, rows);
    await f.load();
    success(t('invoices.tx_import_done', { created: String(res.created), skipped: String(res.skipped) }));
  } catch { error(t('common.error')); }
}
function csvAmount(raw: string): number {
  let s = raw.replace(/[^\d.,-]/g, '');
  if (s.includes(',') && s.includes('.')) {
    s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
  } else if (s.includes(',')) s = s.replace(',', '.');
  return Number(s);
}
function csvDate(s: string): string | null {
  if (!s) return null;
  const m = s.match(/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})/);
  if (m) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return `${y}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
  const iso = s.match(/(\d{4})-(\d{2})-(\d{2})/);
  return iso ? iso[0] : null;
}
// Returns rows the backend bulk endpoint accepts (date + amount required per row).
function parseBankCsv(text: string): Record<string, unknown>[] {
  const lines = text.split(/\r?\n/).filter((l) => l.trim());
  if (lines.length < 2) return [];
  const delim = (lines[0].match(/;/g)?.length ?? 0) >= (lines[0].match(/,/g)?.length ?? 0) ? ';' : ',';
  const split = (l: string) => l.split(delim).map((c) => c.trim().replace(/^"|"$/g, ''));
  const header = split(lines[0]).map((h) => h.toLowerCase());
  const idx = (...names: string[]) => header.findIndex((h) => names.some((n) => h.includes(n)));
  const di = idx('datum', 'date', 'buchung');
  const ai = idx('betrag', 'amount', 'value');
  const ci = idx('empfänger', 'auftraggeber', 'counterparty', 'name', 'beguenstigter', 'begünstigter', 'zahlungspflichtiger');
  const pi = idx('verwendung', 'purpose', 'reference', 'zweck');
  const out: Record<string, unknown>[] = [];
  for (let i = 1; i < lines.length; i++) {
    const c = split(lines[i]);
    if (ai < 0 || !c[ai]) continue;
    const amount = csvAmount(c[ai]);
    if (!Number.isFinite(amount)) continue;
    const date = di >= 0 ? csvDate(c[di]) : null;
    if (!date) continue; // date is required by the endpoint
    out.push({
      date,
      amount,
      counterparty: ci >= 0 ? (c[ci] ?? '') : '',
      purpose: pi >= 0 ? (c[pi] ?? '') : '',
    });
  }
  return out;
}

// ---- Bank-transaction receipts (reconcile: attach receipt documents to a booking) ----
const txRecDialog = ref(false);
const txRecTx = ref<BankTransaction | null>(null);
const txRecInput = ref<HTMLInputElement | null>(null);
const txRecBusy = ref(false);
function openTxReceipts(tx: BankTransaction) { txRecTx.value = tx; txRecDialog.value = true; }
function openTxReceipt(r: import('@spa/stores/finance').TxReceipt) {
  if (txRecTx.value) window.open(f.txReceiptUrl(txRecTx.value.id, r.id), '_blank');
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

// ---- Finance categories (managed list feeding the receipt/partner category datalist) ----
const catDialog = ref(false);
const catForm = reactive<{ id?: number; version?: number; name: string; color: string }>({ name: '', color: '#6750a4' });
function resetCat() { Object.assign(catForm, { id: undefined, version: undefined, name: '', color: '#6750a4' }); }
function editCat(c: FinanceCategory) { Object.assign(catForm, { id: c.id, version: c.version, name: c.name, color: c.color ?? '#6750a4' }); }
async function saveCat() {
  const name = catForm.name.trim();
  if (!name) return;
  saving.value = true;
  try {
    const body = { name, color: catForm.color, version: catForm.version };
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

const filteredPartners = computed<Partner[]>(() => {
  const list = [...f.partners].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  const s = partnerSearch.value.trim().toLowerCase();
  if (!s) return list;
  return list.filter((p) => [p.name, p.email, p.invoice_email, p.phone, p.vat_id, p.address, p.url, p.category, p.note, ...(p.contacts?.map((c) => c.name) ?? [])]
    .some((v) => String(v ?? '').toLowerCase().includes(s)));
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
  try { return new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: cur }).format(n); }
  catch { return `${n.toFixed(2)} ${cur}`; }
}

function openPartner(p: Partner) { openPartnerId.value = p.id; partnersView.value = 'detail'; }
function backToPartners() { partnersView.value = 'list'; openPartnerId.value = null; }

function newPartner() { Object.assign(partnerForm, blankPartnerForm()); pDlg.value = true; }
function editPartner(p: Partner) {
  Object.assign(partnerForm, blankPartnerForm(), {
    id: p.id, version: p.version, name: p.name ?? '', url: p.url ?? '', logo: p.logo ?? '',
    email: p.email ?? '', invoice_email: p.invoice_email ?? '', phone: p.phone ?? '',
    hourly_rate: p.hourly_rate != null ? String(p.hourly_rate) : '', currency: p.currency ?? '',
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
    hourly_rate: partnerForm.hourly_rate.trim() === '' ? null : Number(partnerForm.hourly_rate),
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
function newReceipt() { Object.assign(rForm, { id: undefined, version: undefined, name: '', category: '', tags: [], vat: '', note: '', partner_id: null }); rFile.value = null; rDialog.value = true; }
function editReceipt(r: Receipt) {
  Object.assign(rForm, {
    id: r.id, version: r.version, name: r.name, category: r.category ?? '',
    tags: Array.isArray(r.tags) ? [...r.tags] : [], vat: r.vat ?? '', note: r.note ?? '', partner_id: r.partner_id,
  });
  rFile.value = null; rDialog.value = true;
}
async function saveReceipt() {
  saving.value = true;
  try {
    if (rForm.id) {
      const body: Record<string, unknown> = {
        name: rForm.name, category: rForm.category || null, tags: rForm.tags,
        vat: rForm.vat || null, note: rForm.note || null, partner_id: rForm.partner_id, version: rForm.version,
      };
      await f.updateReceipt(rForm.id, body);
    } else {
      const file = Array.isArray(rFile.value) ? rFile.value[0] : rFile.value;
      if (!file) { error(t('common.error')); saving.value = false; return; }
      const fd = new FormData();
      fd.append('file', file);
      if (rForm.name) fd.append('name', rForm.name);
      if (rForm.category) fd.append('category', rForm.category);
      if (rForm.vat) fd.append('vat', rForm.vat);
      if (rForm.note) fd.append('note', rForm.note);
      if (rForm.partner_id != null) fd.append('partner_id', String(rForm.partner_id));
      for (const tag of rForm.tags) fd.append('tags[]', tag);
      await f.createReceipt(fd);
    }
    rDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delReceipt(r: Receipt) { if (!await confirmAsk(t('invoices.receipt_delete_confirm'), { danger: true })) return; await f.deleteReceipt(r.id); await f.load(); }

// ---- Projects ----
function newProject() { Object.assign(prjForm, { id: undefined, version: undefined, name: '', parent_id: null, note: '' }); prjDialog.value = true; }
function editProject(p: Project) { Object.assign(prjForm, { id: p.id, version: p.version, name: p.name, parent_id: p.parent_id, note: p.note ?? '' }); prjDialog.value = true; }
async function saveProject() {
  saving.value = true;
  try {
    const body: Partial<Project> & { version?: number } = { name: prjForm.name, parent_id: prjForm.parent_id, note: prjForm.note || null };
    if (prjForm.id) { body.id = prjForm.id; body.version = prjForm.version; }
    await f.saveProject(body);
    prjDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delProject(p: Project) { if (!await confirmAsk(t('invoices.project_delete_confirm'), { danger: true })) return; await f.deleteProject(p.id); await f.load(); }

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
    window.open(f.invoicePdfUrl(id), '_blank', 'noopener');
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
