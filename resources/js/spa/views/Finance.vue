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

    <!-- Receipts -->
    <Card v-show="tab === 'receipts'" :title="t('invoices.receipts_title')" :body-class="'p-0'">
      <template #actions><Btn variant="solid" size="sm" icon="add" @click="newReceipt">{{ t('common.add') }}</Btn></template>
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
    </div>

    <!-- Invoice editor -->
    <Modal v-model="invDialog" :title="draft?.id ? (draft?.number || t('invoices.new')) : t('invoices.new')" width="820px">
      <div v-if="draft">
        <!-- header actions + status -->
        <div class="mb-3 flex items-center gap-1">
          <Badge v-if="draft.status" :tone="statusTone(draft.status)">{{ t('invoices.status_' + draft.status) }}</Badge>
          <div v-if="draft.id && draft.number" class="ml-auto flex items-center gap-0.5">
            <Btn variant="ghost" size="sm" icon="mail" :title="t('invoices.email_send')" @click="doEmail(draft as Invoice)" />
            <Btn variant="ghost" size="sm" icon="gavel" :title="t('invoices.dun_send')" @click="doDun(draft as Invoice)" />
            <Btn v-if="draft.type !== 'credit_note'" variant="ghost" size="sm" icon="cancel" :title="t('invoices.storno')" @click="doStorno(draft as Invoice)" />
          </div>
        </div>

        <div v-if="draft.imported" class="mb-3 rounded-lg bg-blue-500/10 px-3 py-2 text-sm text-blue-600 dark:text-blue-400">{{ t('invoices.status_final') }}</div>

        <fieldset :disabled="isLocked" class="m-0 border-0 p-0">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <TextField v-model="custName_" :label="t('invoices.customer')" class="col-span-2" />
            <TextField v-model="draft.issue_date" :label="t('invoices.issue_date')" type="date" />
            <TextField v-model="draft.due_date" :label="t('invoices.due_date')" type="date" />
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
        <Btn v-if="draft && draft.id && !draft.number" variant="soft" class="mr-auto" @click="finalize">{{ t('invoices.finalize') }}</Btn>
        <Btn variant="ghost" @click="invDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="saveInvoice">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Payment-method editor -->
    <Modal v-model="pDialog" :title="pForm.id ? t('common.edit') : t('common.add')" width="480px">
      <div class="space-y-3">
        <TextField v-model="pForm.name" :label="t('common.name')" />
        <Select v-model="pForm.type" label="Type" :options="['bank', 'card', 'paypal', 'cash', 'other'].map((x) => ({ title: x, value: x }))" />
        <TextField v-model="pForm.iban" label="IBAN" />
      </div>
      <template #footer>
        <Btn variant="ghost" @click="pDialog = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :loading="saving" @click="savePP">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

    <!-- Business-partner editor (all fields + multiple contact persons + logo pull) -->
    <Modal v-model="pDlg" :title="partnerForm.id ? t('common.edit') : t('invoices.partner_add')" width="640px">
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

        <TextField v-model="partnerForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" />

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
        <TextField v-model="rForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" />
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
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { useFinanceStore, type Invoice, type InvoiceLine, type Partner, type PaymentMethod, type Project, type Receipt } from '@spa/stores/finance';
import { useToast } from '@spa/composables/useToast';
import { api, VersionConflict } from '@spa/api/client';

const f = useFinanceStore();
const { success, error } = useToast();
const route = useRoute();
const router = useRouter();
const VALID = ['dashboard', 'invoices', 'payments', 'receipts', 'projects', 'partners', 'stats'];
const tab = computed(() => {
  const s = String(route.params.section || 'dashboard');
  return VALID.includes(s) ? s : 'dashboard';
});
function go(v: unknown) { router.push(`/finance/${String(v)}`); }

// In-page left submenu sections (mirrors the Profile/Settings hub layout).
const sections = ['dashboard', 'invoices', 'payments', 'receipts', 'projects', 'partners', 'stats'] as const;
const secIcon: Record<string, string> = {
  dashboard: 'space_dashboard', invoices: 'receipt_long', payments: 'account_balance_wallet',
  receipts: 'receipt', projects: 'account_tree', partners: 'groups', stats: 'insights',
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
const saving = ref(false);

const pDialog = ref(false);
interface PPForm { id?: number; version?: number; name: string; type?: string; iban?: string | null }
const pForm = reactive<PPForm>({ name: '', type: 'bank' });

// Receipt form
const rDialog = ref(false);
const rFile = ref<File | File[] | null>(null);
interface RForm { id?: number; version?: number; name: string; category: string; tags: string[]; vat: string; note: string; partner_id: number | null }
const rForm = reactive<RForm>({ name: '', category: '', tags: [], vat: '', note: '', partner_id: null });

// Project form
const prjDialog = ref(false);
interface PrjForm { id?: number; version?: number; name: string; parent_id: number | null; note: string }
const prjForm = reactive<PrjForm>({ name: '', parent_id: null, note: '' });

onMounted(async () => { await f.load(); await loadReports(); });

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
  } catch { /* ignore */ }
}
function onStatsYear(v: unknown) { void loadReports(Number(v)); }

const fmt = computed(() => new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: 'EUR' }));
function money(n: number) { return fmt.value.format(n || 0); }
function fmtDate(s?: string | null) { return s ? String(s).slice(0, 10) : '—'; }
function statusTone(s: string): 'success' | 'info' | 'warning' | 'gray' { return s === 'paid' ? 'success' : s === 'sent' ? 'info' : s === 'final' ? 'warning' : 'gray'; }
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
  custName_.value = '';
  invDialog.value = true;
}
function editInvoice(i: Invoice) {
  draft.value = { ...i };
  lines.value = Array.isArray(i.lines) ? i.lines.map((l) => ({ ...l })) : [];
  custName_.value = custName(i) === '—' ? '' : custName(i);
  invDialog.value = true;
}
async function saveInvoice() {
  if (!draft.value) return;
  saving.value = true;
  const body: Record<string, unknown> = {
    status: draft.value.status, currency: draft.value.currency || 'EUR',
    issue_date: draft.value.issue_date, due_date: draft.value.due_date,
    customer: { ...(draft.value.customer ?? {}), name: custName_.value },
    lines: lines.value, note: draft.value.note,
    net: totals.value.net, vat: totals.value.vat, gross: totals.value.gross,
    vat_rate: lines.value[0]?.vatRate ?? 19,
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
async function delInvoice(i: Invoice) { if (!confirm(t('common.confirm_delete'))) return; await f.deleteInvoice(i.id); await f.load(); await loadReports(); }

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

function resetForm(o: PPForm) { Object.assign(pForm, { id: undefined, version: undefined, name: '', type: 'bank', iban: '' }, o); }
function newPayment() { resetForm({ name: '', type: 'bank' }); pDialog.value = true; }
function editPayment(p: PaymentMethod) { resetForm({ id: p.id, name: p.name, type: p.type, iban: p.iban, version: p.version }); pDialog.value = true; }
async function savePP() {
  saving.value = true;
  try {
    await f.savePayment(pForm as unknown as Partial<PaymentMethod>);
    pDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
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
    if (icon.length > 2000) { error(t('invoices.partner_logo_too_large')); return; } // backend `logo` column caps at 2000 chars
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
  if (!confirm(t('invoices.partner_delete_confirm'))) return;
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
async function delReceipt(r: Receipt) { if (!confirm(t('invoices.receipt_delete_confirm'))) return; await f.deleteReceipt(r.id); await f.load(); }

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
async function delProject(p: Project) { if (!confirm(t('invoices.project_delete_confirm'))) return; await f.deleteProject(p.id); await f.load(); }

</script>
