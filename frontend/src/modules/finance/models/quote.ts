import type {
  CalculatedTotals,
  CurrencyCode,
  DecimalIntegerString,
  DecimalString,
  MoneyTotals,
  TaxBreakdown,
} from './money';

export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'declined' | 'converted';
export type QuoteEffectiveStatus = QuoteStatus | 'expired';
export type QuoteLineKind = 'service' | 'hardware';
export type QuoteDiscountType = 'none' | 'percent' | 'fixed';
export type QuoteDeliveryState = 'queued' | 'sending' | 'sent' | 'failed';

export interface QuoteCustomer extends Record<string, unknown> {
  name: string;
  email?: string | null;
}

export interface QuoteLineInput {
  description: string;
  quantity: DecimalString;
  unit: string;
  unit_price: DecimalString;
  tax_rate: DecimalString;
  kind: QuoteLineKind;
  product_id: number | null;
}

export interface QuoteDraftInput {
  title: string;
  partner_id: number | null;
  customer: QuoteCustomer;
  issue_date: string | null;
  valid_until: string | null;
  currency: CurrencyCode;
  lines: QuoteLineInput[];
  discount_type: QuoteDiscountType;
  discount_value: DecimalString | null;
  intro_text: string | null;
  outro_text: string | null;
  internal_note: string | null;
  control_net_minor?: DecimalIntegerString | null;
  control_vat_minor?: DecimalIntegerString | null;
  control_gross_minor?: DecimalIntegerString | null;
}

export interface QuoteLine extends QuoteLineInput {
  quantity_scaled: DecimalIntegerString;
  unit_price_minor: DecimalIntegerString;
  currency: CurrencyCode;
  tax_rate_basis_points: DecimalIntegerString;
}

export interface QuoteDiscount {
  type: QuoteDiscountType;
  value: DecimalString | null;
  basis_points?: DecimalIntegerString;
  minor?: DecimalIntegerString;
  currency: CurrencyCode;
}

export interface QuoteDraft {
  title: string;
  customer: QuoteCustomer;
  partner_id: number | null;
  issue_date: string;
  valid_until: string;
  currency: CurrencyCode;
  lines: QuoteLine[];
  discount: QuoteDiscount;
  totals: CalculatedTotals;
  intro_text: string | null;
  outro_text: string | null;
  internal_note: string | null;
}

export interface QuoteSnapshot extends Omit<QuoteDraft, 'internal_note'> {
  schema_version: 1;
  document_type: 'quote';
  series_uuid: string;
  document_number: string;
  revision_number: number;
  revision_label: string;
  customer_note: string | null;
}

export interface QuotePreview extends MoneyTotals {
  discount_minor: DecimalIntegerString;
  tax_breakdowns: TaxBreakdown[];
  issue_date: string;
  valid_until: string;
}

export interface QuoteRevision {
  id: number;
  revision_number: number;
  previous_revision_id: number | null;
  status: 'draft' | 'published';
  snapshot: QuoteSnapshot;
  totals: MoneyTotals;
  pdf_sha256: string | null;
  pdf_url: string | null;
  pdf_download_url: string | null;
  published_at: string | null;
  created_at: string;
}

export interface QuoteDelivery {
  uuid: string;
  revision_id: number;
  state: QuoteDeliveryState;
  attempts: number;
  last_error_code: string | null;
  queued_at: string;
  sent_at: string | null;
  failed_at: string | null;
}

export interface QuoteConversion {
  source_revision_id: number;
  target_type: 'invoice';
  target_reference: string;
  target_id: number | null;
  created_at: string;
}

export interface Quote {
  id: string;
  status: QuoteStatus;
  effective_status: QuoteEffectiveStatus;
  partner_id: number | null;
  number: string | null;
  version: number;
  has_pending_draft: boolean;
  current_revision: QuoteRevision | null;
  draft: QuoteDraft | null;
  totals: MoneyTotals;
  conversions: QuoteConversion[];
  delivery: QuoteDelivery | null;
  published_at: string | null;
  accepted_at: string | null;
  declined_at: string | null;
  converted_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface QuotePage {
  data: Quote[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

export interface QuoteListFilters {
  q?: string;
  status?: QuoteStatus | null;
  effective_status?: QuoteEffectiveStatus | null;
  sort?: 'published_at';
  direction?: 'desc';
  page?: number;
  per_page?: number;
}

export interface QuotePublishInput { version: number; change_reason: string | null }
export interface QuoteSendInput extends QuotePublishInput { recipient: string | null }
export interface QuoteDecisionInput { version: number; expected_revision_id: number }
export interface QuoteDuplicateInput { version: number; source_revision_id: number | null }
export interface InvoiceDraftTarget { target_reference: string; target_id: number | null }

export interface QuoteSendResult {
  quote: Quote;
  replayed: boolean;
  status: 200 | 202;
  etag: string | null;
}
