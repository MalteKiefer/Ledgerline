// Exact, non-floating display formatting for integer minor-unit money and
// scale-4 hour quantities. Never routes these through JavaScript `Number`;
// large values must render exactly as the server sent them.
export function formatMinor(value: string, currency: string): string {
  if (!/^-?(?:0|[1-9][0-9]*)$/.test(value)) return `${value} ${currency}`;
  const negative = value.startsWith('-');
  const digits = (negative ? value.slice(1) : value).padStart(3, '0');
  const integer = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const fraction = digits.slice(-2);
  return `${negative ? '-' : ''}${integer},${fraction} ${currency}`;
}

export function formatScaled(value: string, suffix = 'h'): string {
  if (!/^-?(?:0|[1-9][0-9]*)$/.test(value)) return value;
  const negative = value.startsWith('-');
  const digits = (negative ? value.slice(1) : value).padStart(5, '0');
  const integer = digits.slice(0, -4);
  const fraction = digits.slice(-4);
  return `${negative ? '-' : ''}${integer}.${fraction} ${suffix}`;
}
