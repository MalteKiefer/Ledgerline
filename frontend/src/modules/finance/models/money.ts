export type DecimalString = string;
export type DecimalIntegerString = string;
export type CurrencyCode = string;

export interface MoneyTotals {
  net_minor: DecimalIntegerString;
  vat_minor: DecimalIntegerString;
  gross_minor: DecimalIntegerString;
  currency: CurrencyCode;
}

export interface TaxBreakdown {
  tax_rate_basis_points: DecimalIntegerString;
  net_minor: DecimalIntegerString;
  vat_minor: DecimalIntegerString;
  gross_minor: DecimalIntegerString;
}

export interface CalculatedTotals extends MoneyTotals {
  discount_minor: DecimalIntegerString;
  tax_breakdowns: TaxBreakdown[];
}
