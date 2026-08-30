<template>
  <section class="space-y-4" aria-labelledby="quote-edit-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="quote-edit-heading" class="text-xl font-bold">{{ isNew ? t('invoices.quote_add') : t('common.edit') }}</h1>
      <div class="flex flex-wrap gap-2">
        <Btn :loading="store.actionLoading" data-action="save" @click="save">{{ t('common.save') }}</Btn>
        <Btn :loading="store.actionLoading" data-action="publish" variant="outline" @click="saveThen('publish')">{{ t('invoices.quote_publish') }}</Btn>
        <Btn :loading="store.actionLoading" data-action="send" @click="saveThen('send')">{{ t('invoices.quote_send') }}</Btn>
        <Btn v-if="!isNew && store.current?.current_revision" data-action="discard" variant="danger" :disabled="store.actionLoading" @click="discard">
          {{ t('invoices.quote_discard_draft') }}
        </Btn>
      </div>
    </header>

    <div v-if="conflict" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">
      <p>{{ t('invoices.quote_conflict') }}</p>
      <Btn data-action="load-conflict" variant="outline" size="sm" class="mt-2" @click="loadConflict">
        {{ t('invoices.quote_conflict_load') }}
      </Btn>
    </div>
    <p v-else-if="store.actionError" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700">
      {{ errorLabel(store.actionError) }}
    </p>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div class="space-y-4">
        <Card :title="t('invoices.quote_title')">
          <div class="grid gap-3 sm:grid-cols-2">
            <TextField data-field="title" :model-value="form.title" :label="t('invoices.quote_title')" @update:model-value="set('title', $event)" />
            <TextField :model-value="form.customer.name" :label="t('invoices.quote_customer')" @update:model-value="setCustomer('name', $event)" />
            <TextField :model-value="form.customer.email ?? ''" type="email" :label="t('common.email')" @update:model-value="setCustomer('email', $event || null)" />
            <TextField :model-value="form.issue_date" type="date" :label="t('common.date')" @update:model-value="set('issue_date', $event || null)" />
            <TextField :model-value="form.valid_until" type="date" :label="t('invoices.quote_valid_until')" @update:model-value="set('valid_until', $event || null)" />
            <TextField :model-value="form.currency" :label="t('invoices.currency')" @update:model-value="set('currency', $event.toUpperCase())" />
          </div>
        </Card>
        <Card :title="t('invoices.positions')">
          <QuoteLineEditor :model-value="form.lines" @update:model-value="set('lines', $event)" />
        </Card>
        <Card :title="t('invoices.discount')">
          <div class="grid gap-3 sm:grid-cols-2">
            <label>
              <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.discount') }}</span>
              <select :value="form.discount_type" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="set('discount_type', ($event.target as HTMLSelectElement).value as QuoteDiscountType)">
                <option value="none">{{ t('common.none') }}</option>
                <option value="percent">%</option>
                <option value="fixed">{{ t('invoices.discount_amount') }}</option>
              </select>
            </label>
            <TextField v-if="form.discount_type !== 'none'" :model-value="form.discount_value" inputmode="decimal" :label="t('invoices.discount_amount')" @update:model-value="set('discount_value', $event || null)" />
          </div>
        </Card>
        <Card :title="t('invoices.quote_intro')">
          <div class="grid gap-3">
            <label>
              <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.quote_intro') }}</span>
              <textarea
                data-field="intro-text"
                :value="form.intro_text ?? ''"
                class="min-h-24 w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
                @input="set('intro_text', ($event.target as HTMLTextAreaElement).value || null)"
              />
            </label>
            <label>
              <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.quote_outro') }}</span>
              <textarea
                data-field="outro-text"
                :value="form.outro_text ?? ''"
                class="min-h-24 w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
                @input="set('outro_text', ($event.target as HTMLTextAreaElement).value || null)"
              />
            </label>
            <label>
              <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.quote_internal_note') }}</span>
              <textarea
                data-field="internal-note"
                :value="form.internal_note ?? ''"
                class="min-h-20 w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
                @input="set('internal_note', ($event.target as HTMLTextAreaElement).value || null)"
              />
            </label>
          </div>
        </Card>
      </div>

      <Card :title="t('invoices.quote_preview')" class="self-start xl:sticky xl:top-4">
        <p v-if="previewLoading && !preview" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
        <QuoteTotals v-else-if="preview" :totals="preview" :stale="previewStale" />
        <p v-else class="text-sm text-[var(--ll-muted)]">{{ t('invoices.quote_preview_unavailable') }}</p>
      </Card>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { Btn, Card, TextField } from '@spa/ui';
import QuoteLineEditor from '@spa/modules/finance/components/quotes/QuoteLineEditor.vue';
import QuoteTotals from '@spa/modules/finance/components/quotes/QuoteTotals.vue';
import type { Quote, QuoteDiscountType, QuoteDraftInput, QuotePreview } from '@spa/modules/finance/models/quote';
import { useQuotesStore } from '@spa/modules/finance/stores/quotes';

const route = useRoute();
const router = useRouter();
const store = useQuotesStore();
const id = computed(() => typeof route.params.quote === 'string' ? route.params.quote : null);
const isNew = computed(() => id.value === null);
const form = ref<QuoteDraftInput>(emptyDraft());
const preview = ref<QuotePreview | null>(null);
const previewStale = ref(true);
const previewLoading = ref(false);
const conflict = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | null = null;
let previewController: AbortController | null = null;

onMounted(async () => {
  if (id.value !== null) {
    const loaded = await store.loadQuote(id.value).catch(() => null);
    if (loaded?.draft) form.value = inputFrom(loaded);
  }
  schedulePreview();
});

onBeforeUnmount(() => {
  if (previewTimer !== null) clearTimeout(previewTimer);
  previewController?.abort();
});

function changed(next: QuoteDraftInput): void {
  form.value = next;
  previewStale.value = true;
  schedulePreview();
}

function set<K extends keyof QuoteDraftInput>(key: K, value: QuoteDraftInput[K]): void {
  changed({ ...form.value, [key]: value });
}

function setCustomer(key: 'name' | 'email', value: string | null): void {
  changed({ ...form.value, customer: { ...form.value.customer, [key]: value } });
}

function schedulePreview(): void {
  if (previewTimer !== null) clearTimeout(previewTimer);
  previewController?.abort();
  previewTimer = setTimeout(() => void loadPreview(), 300);
}

async function loadPreview(): Promise<void> {
  const controller = new AbortController();
  previewController = controller;
  previewLoading.value = true;
  try {
    const result = await store.preview(payload(), controller.signal);
    if (previewController === controller) {
      preview.value = result;
      previewStale.value = false;
    }
  } catch {
    // Invalid intermediate form input keeps the last truthful server preview.
  } finally {
    if (previewController === controller) previewLoading.value = false;
  }
}

function payload(): QuoteDraftInput {
  const { control_net_minor: _net, control_vat_minor: _vat, control_gross_minor: _gross, ...exact } = form.value;
  return exact;
}

async function save(): Promise<Quote> {
  conflict.value = false;
  try {
    const saved = id.value === null
      ? await store.create(payload())
      : await store.updateDraft(id.value, store.current?.version ?? 0, payload());
    form.value = saved.draft ? inputFrom(saved) : form.value;
    if (id.value === null) await router.push({ name: 'finance.quotes.edit', params: { quote: saved.id } });
    return saved;
  } catch (error) {
    conflict.value = store.actionError === 'version_conflict';
    throw error;
  }
}

async function saveThen(action: 'publish' | 'send'): Promise<void> {
  try {
    const saved = await save();
    if (action === 'publish') await store.publish(saved.id, saved.version, null);
    else await store.send(saved.id, saved.version, saved.draft?.customer.email ?? null, null);
    await router.push({ name: 'finance.quotes.show', params: { quote: saved.id } });
  } catch {
    // The visible typed error/conflict state is the workflow result.
  }
}

function loadConflict(): void {
  if (!store.current?.draft) return;
  form.value = inputFrom(store.current);
  conflict.value = false;
  schedulePreview();
}

async function discard(): Promise<void> {
  if (id.value === null || !store.current) return;
  await store.discardDraft(id.value, store.current.version);
  await router.push({ name: 'finance.quotes.show', params: { quote: id.value } });
}

function inputFrom(quote: Quote): QuoteDraftInput {
  if (!quote.draft) return emptyDraft();
  return {
    title: quote.draft.title,
    partner_id: quote.draft.partner_id,
    customer: { ...quote.draft.customer },
    issue_date: quote.draft.issue_date,
    valid_until: quote.draft.valid_until,
    currency: quote.draft.currency,
    lines: quote.draft.lines.map(({ description, quantity, unit, unit_price, tax_rate, kind, product_id }) => ({
      description, quantity, unit, unit_price, tax_rate, kind, product_id,
    })),
    discount_type: quote.draft.discount.type,
    discount_value: quote.draft.discount.value,
    intro_text: quote.draft.intro_text,
    outro_text: quote.draft.outro_text,
    internal_note: quote.draft.internal_note,
  };
}

function emptyDraft(): QuoteDraftInput {
  return {
    title: '',
    partner_id: null,
    customer: { name: '', email: null },
    issue_date: null,
    valid_until: null,
    currency: 'EUR',
    lines: [{ description: '', quantity: '1.0000', unit: 'pc', unit_price: '0.00', tax_rate: '19.00', kind: 'service', product_id: null }],
    discount_type: 'none',
    discount_value: null,
    intro_text: null,
    outro_text: null,
    internal_note: null,
  };
}

function errorLabel(code: string): string {
  return `${t('invoices.quote_error')} (${code})`;
}
</script>
