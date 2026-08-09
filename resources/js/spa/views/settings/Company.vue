<template>
  <div class="space-y-4 pb-20">
    <!-- Section sub-nav (pill row) -->
    <div class="flex flex-wrap gap-1 rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] p-1">
      <button
        v-for="s in sections" :key="s.id"
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
        :class="section === s.id ? 'bg-primary-500 text-white shadow-sm' : 'text-[var(--ll-muted)] hover:bg-black/[0.04] dark:hover:bg-white/5'"
        @click="section = s.id"
      >{{ t(s.label) }}</button>
    </div>

    <!-- Identity -->
    <Card v-show="section === 'identity'">
      <template #header>
        <Icon name="business" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.company_identity_heading') }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField v-model="form.company_name" :label="t('settings.company_name')" class="sm:col-span-2" />
        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.company_address') }}</span>
          <textarea v-model="form.company_address" rows="3" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"></textarea>
        </label>
        <TextField v-model="form.company_email" :label="t('settings.company_email')" type="email" />
        <TextField v-model="form.company_phone" :label="t('settings.company_phone')" />
        <TextField v-model="form.company_tax_id" :label="t('settings.company_tax_id')" />
        <TextField v-model="form.company_vat_id" :label="t('settings.company_vat_id')" />
      </div>
    </Card>

    <!-- Bank details -->
    <Card v-show="section === 'bank'">
      <template #header>
        <Icon name="key" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.company_bank_heading') }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <TextField v-model="form.company_bank_name" :label="t('settings.company_bank_name')" />
        <TextField v-model="form.company_iban" :label="t('settings.company_iban')" />
        <TextField v-model="form.company_bic" :label="t('settings.company_bic')" />
      </div>
    </Card>

    <!-- Website + contact persons -->
    <Card v-show="section === 'contact'">
      <template #header>
        <Icon name="badge" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.company_contacts_heading') }}</h2>
      </template>
      <TextField v-model="form.company_website" :label="t('settings.company_website')" type="url" placeholder="https://…" />

      <p class="mb-2 mt-4 text-xs text-[var(--ll-muted)]">{{ t('settings.company_contacts_hint') }}</p>

      <div v-for="(row, i) in form.company_contacts" :key="i" class="mb-3 grid grid-cols-12 items-center gap-2">
        <TextField v-model="row.name" :label="t('settings.company_contact_name')" class="col-span-12 sm:col-span-3" />
        <TextField v-model="row.role" :label="t('settings.company_contact_role')" class="col-span-12 sm:col-span-3" />
        <TextField v-model="row.email" :label="t('settings.company_contact_email')" type="email" class="col-span-12 sm:col-span-3" />
        <TextField v-model="row.phone" :label="t('settings.company_contact_phone')" class="col-span-10 sm:col-span-2" />
        <div class="col-span-2 flex justify-center sm:col-span-1">
          <button class="grid h-9 w-9 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :aria-label="t('common.delete')" @click="form.company_contacts.splice(i, 1)">
            <Icon name="delete" :size="18" />
          </button>
        </div>
      </div>

      <Btn variant="soft" size="sm" icon="add" @click="addContact">{{ t('settings.company_contact_add') }}</Btn>
    </Card>

    <!-- Invoice defaults -->
    <Card v-show="section === 'invoice'">
      <template #header>
        <Icon name="edit" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.company_invoice_heading') }}</h2>
      </template>
      <p class="mb-3 text-xs text-[var(--ll-muted)]">{{ t('settings.company_invoice_hint') }}</p>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField v-model="form.invoice_number_format" :label="t('settings.invoice_number_format')" placeholder="YYYY-NNNN" :hint="t('settings.invoice_number_format_hint')" class="sm:col-span-2" />
        <TextField v-model="invoiceNextNumberStr" :label="t('settings.invoice_next_number')" type="number" inputmode="numeric" />
        <TextField v-model="invoiceDefaultVatRateStr" :label="t('settings.invoice_default_vat_rate')" type="number" inputmode="decimal" />
        <TextField v-model="invoicePaymentTermsDaysStr" :label="t('settings.invoice_payment_terms_days')" type="number" inputmode="numeric" />
        <label class="flex items-center justify-between gap-3">
          <span class="text-sm font-medium">{{ t('settings.invoice_small_business_label') }}</span>
          <button
            type="button" role="switch" :aria-checked="form.small_business"
            class="relative h-6 w-10 flex-shrink-0 rounded-full transition-colors" :class="form.small_business ? 'bg-primary-500' : 'bg-black/10 dark:bg-white/15'"
            @click="form.small_business = !form.small_business"
          >
            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="form.small_business ? 'translate-x-4' : ''" />
          </button>
        </label>
        <p class="-mt-2 text-xs text-[var(--ll-muted)] sm:col-span-2">{{ t('settings.invoice_small_business_hint') }}</p>

        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.invoice_payment_terms_text') }}</span>
          <textarea v-model="form.invoice_payment_terms_text" rows="2" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"></textarea>
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.invoice_payment_methods') }}</span>
          <textarea v-model="form.invoice_payment_methods" rows="2" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"></textarea>
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.invoice_footer_text') }}</span>
          <textarea v-model="form.invoice_footer_text" rows="2" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"></textarea>
        </label>
      </div>
    </Card>

    <!-- Design -->
    <Card v-show="section === 'design'">
      <template #header>
        <Icon name="image" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.invoice_design_heading') }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select v-model="form.invoice_template" :options="templateItems" :label="t('settings.invoice_template')" />
        <TextField v-model="form.invoice_font" :label="t('settings.invoice_font')" :hint="t('settings.invoice_font_hint')" />

        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.invoice_accent_color') }}</span>
          <span class="flex items-center gap-3">
            <input v-model="form.invoice_accent_color" type="color" class="h-9 w-11 cursor-pointer rounded-lg border border-[var(--ll-border)] bg-transparent" :aria-label="t('settings.invoice_accent_color')">
            <input v-model="form.invoice_accent_color" type="text" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500">
          </span>
        </label>
        <label class="block">
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('settings.invoice_heading_color') }}</span>
          <span class="flex items-center gap-3">
            <input v-model="form.invoice_heading_color" type="color" class="h-9 w-11 cursor-pointer rounded-lg border border-[var(--ll-border)] bg-transparent" :aria-label="t('settings.invoice_heading_color')">
            <input v-model="form.invoice_heading_color" type="text" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500">
          </span>
        </label>

        <label class="flex items-center justify-between gap-3 sm:col-span-2">
          <span class="text-sm font-medium">{{ t('settings.invoice_vat_ist') }}</span>
          <button
            type="button" role="switch" :aria-checked="form.invoice_vat_ist"
            class="relative h-6 w-10 flex-shrink-0 rounded-full transition-colors" :class="form.invoice_vat_ist ? 'bg-primary-500' : 'bg-black/10 dark:bg-white/15'"
            @click="form.invoice_vat_ist = !form.invoice_vat_ist"
          >
            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="form.invoice_vat_ist ? 'translate-x-4' : ''" />
          </button>
        </label>
        <p class="-mt-2 text-xs text-[var(--ll-muted)] sm:col-span-2">{{ t('settings.invoice_vat_ist_hint') }}</p>
      </div>
    </Card>

    <!-- Logo -->
    <Card v-show="section === 'design'">
      <template #header>
        <Icon name="image" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.company_logo_heading') }}</h2>
      </template>
      <div class="flex flex-wrap items-center gap-4">
        <span v-if="logoSrc" class="grid h-[72px] w-[200px] place-items-center rounded-lg border border-[var(--ll-border)] bg-white p-1">
          <img :src="logoSrc" class="max-h-16 max-w-48 object-contain">
        </span>
        <input ref="fileInputEl" type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="hidden" @change="onFileChange">
        <Btn variant="outline" icon="upload" @click="fileInputEl?.click()">{{ t('settings.company_logo_heading') }}</Btn>
        <span v-if="selectedLogo()" class="text-xs text-[var(--ll-muted)]">{{ selectedLogo()?.name }}</span>
      </div>
      <label v-if="form.has_logo" class="mt-3 flex items-center gap-3">
        <button
          type="button" role="switch" :aria-checked="removeLogo"
          class="relative h-6 w-10 flex-shrink-0 rounded-full transition-colors" :class="removeLogo ? 'bg-red-500' : 'bg-black/10 dark:bg-white/15'"
          @click="removeLogo = !removeLogo"
        >
          <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="removeLogo ? 'translate-x-4' : ''" />
        </button>
        <span class="text-sm font-medium text-red-600">{{ t('settings.company_logo_remove') }}</span>
      </label>
    </Card>

    <!-- Sticky save bar -->
    <div class="sticky bottom-3 z-10 flex justify-end rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 shadow-lg">
      <Btn variant="solid" icon="check" :loading="saving" :disabled="loading" @click="save">
        {{ t('settings.save') }}
      </Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField, Select } from '@spa/ui';

interface CompanyContact {
  name: string;
  role: string;
  email: string;
  phone: string;
}

/** Shape returned by GET /api/v1/company (controller present()). */
interface CompanyResponse {
  company_name: string | null;
  company_address: string | null;
  company_email: string | null;
  company_phone: string | null;
  company_tax_id: string | null;
  company_vat_id: string | null;
  company_iban: string | null;
  company_bic: string | null;
  company_bank_name: string | null;
  invoice_number_format: string | null;
  invoice_next_number: number | null;
  invoice_default_vat_rate: number | string | null;
  small_business: boolean;
  invoice_payment_terms_days: number | null;
  invoice_footer_text: string | null;
  invoice_accent_color: string | null;
  invoice_heading_color: string | null;
  invoice_template: string | null;
  invoice_payment_methods: string | null;
  invoice_payment_terms_text: string | null;
  has_logo: boolean;
  // Fields mirrored from the Blade that the API may also return.
  company_website?: string | null;
  company_contacts?: CompanyContact[] | null;
  invoice_font?: string | null;
  invoice_vat_ist?: boolean | null;
}

interface FormState {
  company_name: string;
  company_address: string;
  company_email: string;
  company_phone: string;
  company_tax_id: string;
  company_vat_id: string;
  company_website: string;
  company_bank_name: string;
  company_iban: string;
  company_bic: string;
  company_contacts: CompanyContact[];
  invoice_number_format: string;
  invoice_next_number: number | null;
  invoice_default_vat_rate: number | null;
  invoice_payment_terms_days: number | null;
  invoice_footer_text: string;
  invoice_payment_terms_text: string;
  invoice_payment_methods: string;
  small_business: boolean;
  invoice_accent_color: string;
  invoice_heading_color: string;
  invoice_template: string;
  invoice_font: string;
  invoice_vat_ist: boolean;
  has_logo: boolean;
}

const DEFAULT_ACCENT = '#111827';
const DEFAULT_HEADING = '#6b7280';

const { success, error } = useToast();

type CompanySection = 'identity' | 'bank' | 'contact' | 'invoice' | 'design';
const section = ref<CompanySection>('identity');
const sections: { id: CompanySection; label: string }[] = [
  { id: 'identity', label: 'settings.company_identity_heading' },
  { id: 'bank', label: 'settings.company_bank_heading' },
  { id: 'contact', label: 'settings.company_contacts_heading' },
  { id: 'invoice', label: 'settings.company_invoice_heading' },
  { id: 'design', label: 'settings.invoice_design_heading' },
];

const form = reactive<FormState>({
  company_name: '',
  company_address: '',
  company_email: '',
  company_phone: '',
  company_tax_id: '',
  company_vat_id: '',
  company_website: '',
  company_bank_name: '',
  company_iban: '',
  company_bic: '',
  company_contacts: [],
  invoice_number_format: '',
  invoice_next_number: null,
  invoice_default_vat_rate: null,
  invoice_payment_terms_days: null,
  invoice_footer_text: '',
  invoice_payment_terms_text: '',
  invoice_payment_methods: '',
  small_business: false,
  invoice_accent_color: DEFAULT_ACCENT,
  invoice_heading_color: DEFAULT_HEADING,
  invoice_template: 'editorial',
  invoice_font: '',
  invoice_vat_ist: false,
  has_logo: false,
});

const templateItems = computed(() =>
  (['editorial', 'modern', 'elegant', 'klassisch', 'schlicht'] as const).map((v) => ({
    value: v,
    title: t(`settings.invoice_template_${v}`),
  })),
);

// TextField only emits strings — bridge the strongly-typed numeric form fields for the kit's input contract.
const invoiceNextNumberStr = computed<string>({
  get: () => (form.invoice_next_number == null ? '' : String(form.invoice_next_number)),
  set: (v: string) => { form.invoice_next_number = v === '' ? null : Number(v); },
});
const invoiceDefaultVatRateStr = computed<string>({
  get: () => (form.invoice_default_vat_rate == null ? '' : String(form.invoice_default_vat_rate)),
  set: (v: string) => { form.invoice_default_vat_rate = v === '' ? null : Number(v); },
});
const invoicePaymentTermsDaysStr = computed<string>({
  get: () => (form.invoice_payment_terms_days == null ? '' : String(form.invoice_payment_terms_days)),
  set: (v: string) => { form.invoice_payment_terms_days = v === '' ? null : Number(v); },
});

const loading = ref(true);
const saving = ref(false);

// Logo state
const fileInputEl = ref<HTMLInputElement | null>(null);
const logoInput = ref<File | File[] | null>(null);
const logoPreview = ref<string | null>(null);
const removeLogo = ref(false);
const logoVersion = ref(Date.now());

function selectedLogo(): File | null {
  return Array.isArray(logoInput.value) ? (logoInput.value[0] ?? null) : logoInput.value;
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null;
  logoInput.value = file;
}

const logoSrc = computed<string | null>(() => {
  if (logoPreview.value) return logoPreview.value;
  if (form.has_logo && !removeLogo.value) return api.streamUrl(`/api/v1/company/logo?v=${logoVersion.value}`);
  return null;
});

watch(logoInput, () => {
  if (logoPreview.value) {
    URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = null;
  }
  const file = selectedLogo();
  if (file) {
    logoPreview.value = URL.createObjectURL(file);
    removeLogo.value = false;
  }
});

function addContact() {
  form.company_contacts.push({ name: '', role: '', email: '', phone: '' });
}

function apply(c: CompanyResponse) {
  form.company_name = c.company_name ?? '';
  form.company_address = c.company_address ?? '';
  form.company_email = c.company_email ?? '';
  form.company_phone = c.company_phone ?? '';
  form.company_tax_id = c.company_tax_id ?? '';
  form.company_vat_id = c.company_vat_id ?? '';
  form.company_website = c.company_website ?? '';
  form.company_bank_name = c.company_bank_name ?? '';
  form.company_iban = c.company_iban ?? '';
  form.company_bic = c.company_bic ?? '';
  form.company_contacts = Array.isArray(c.company_contacts)
    ? c.company_contacts.map((r) => ({ name: r.name ?? '', role: r.role ?? '', email: r.email ?? '', phone: r.phone ?? '' }))
    : [];
  form.invoice_number_format = c.invoice_number_format ?? '';
  form.invoice_next_number = c.invoice_next_number ?? null;
  form.invoice_default_vat_rate = c.invoice_default_vat_rate != null ? Number(c.invoice_default_vat_rate) : null;
  form.invoice_payment_terms_days = c.invoice_payment_terms_days ?? null;
  form.invoice_footer_text = c.invoice_footer_text ?? '';
  form.invoice_payment_terms_text = c.invoice_payment_terms_text ?? '';
  form.invoice_payment_methods = c.invoice_payment_methods ?? '';
  form.small_business = !!c.small_business;
  form.invoice_accent_color = c.invoice_accent_color ?? DEFAULT_ACCENT;
  form.invoice_heading_color = c.invoice_heading_color ?? DEFAULT_HEADING;
  form.invoice_template = c.invoice_template ?? 'editorial';
  form.invoice_font = c.invoice_font ?? '';
  form.invoice_vat_ist = !!c.invoice_vat_ist;
  form.has_logo = !!c.has_logo;
}

/** The field payload sent on save (extra Blade fields are harmless if the API ignores them). */
function payload(): Record<string, unknown> {
  return {
    company_name: form.company_name,
    company_address: form.company_address,
    company_email: form.company_email,
    company_phone: form.company_phone,
    company_tax_id: form.company_tax_id,
    company_vat_id: form.company_vat_id,
    company_website: form.company_website,
    company_bank_name: form.company_bank_name,
    company_iban: form.company_iban,
    company_bic: form.company_bic,
    company_contacts: form.company_contacts.filter((c) => c.name || c.role || c.email || c.phone),
    invoice_number_format: form.invoice_number_format,
    invoice_next_number: form.invoice_next_number,
    invoice_default_vat_rate: form.invoice_default_vat_rate,
    invoice_payment_terms_days: form.invoice_payment_terms_days,
    invoice_footer_text: form.invoice_footer_text,
    invoice_payment_terms_text: form.invoice_payment_terms_text,
    invoice_payment_methods: form.invoice_payment_methods,
    small_business: form.small_business,
    invoice_accent_color: form.invoice_accent_color,
    invoice_heading_color: form.invoice_heading_color,
    invoice_template: form.invoice_template,
    invoice_font: form.invoice_font,
    invoice_vat_ist: form.invoice_vat_ist,
  };
}

/** Build a multipart body (method-spoofed PUT) for a logo upload / removal. */
function multipart(): FormData {
  const fd = new FormData();
  fd.append('_method', 'PUT');
  for (const [key, value] of Object.entries(payload())) {
    if (value === null || value === undefined) continue;
    if (key === 'company_contacts' && Array.isArray(value)) {
      (value as CompanyContact[]).forEach((c, i) => {
        fd.append(`company_contacts[${i}][name]`, c.name ?? '');
        fd.append(`company_contacts[${i}][role]`, c.role ?? '');
        fd.append(`company_contacts[${i}][email]`, c.email ?? '');
        fd.append(`company_contacts[${i}][phone]`, c.phone ?? '');
      });
      continue;
    }
    if (typeof value === 'boolean') {
      fd.append(key, value ? '1' : '0');
      continue;
    }
    fd.append(key, String(value));
  }
  if (removeLogo.value) fd.append('remove_logo', '1');
  const file = selectedLogo();
  if (file) fd.append('logo', file);
  return fd;
}

async function save() {
  saving.value = true;
  try {
    const useMultipart = !!selectedLogo() || removeLogo.value;
    const res = useMultipart
      ? await api.upload<{ company: CompanyResponse }>('/api/v1/company', multipart())
      : await api.put<{ company: CompanyResponse }>('/api/v1/company', payload());
    apply(res.company);
    logoInput.value = null;
    removeLogo.value = false;
    if (logoPreview.value) {
      URL.revokeObjectURL(logoPreview.value);
      logoPreview.value = null;
    }
    logoVersion.value = Date.now();
    success(t('settings.company_saved'));
  } catch {
    error(t('common.error'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await api.get<{ company: CompanyResponse }>('/api/v1/company');
    apply(res.company);
  } catch {
    error(t('common.error'));
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
});
</script>
