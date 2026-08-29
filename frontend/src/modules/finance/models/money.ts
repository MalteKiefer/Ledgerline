export type DecimalString = string;
export type CurrencyCode = string;

export interface MoneyTotals {
  net_minor: number;
  vat_minor: number;
  gross_minor: number;
  currency: CurrencyCode;
}

export interface TaxBreakdown {
  tax_rate_basis_points: number;
  net_minor: number;
  vat_minor: number;
  gross_minor: number;
}

export interface CalculatedTotals extends MoneyTotals {
  discount_minor: number;
  tax_breakdowns: TaxBreakdown[];
}
