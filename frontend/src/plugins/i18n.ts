import { i18nVue } from 'laravel-vue-i18n';

// Build-time PHP->JSON via laravel-vue-i18n/vite (langPath 'lang'); keeps
// lang/{en,de,ru}/*.php as the single source (EN/DE/RU parity) with Laravel
// trans() semantics. Lazy per-locale via import.meta.glob.
export const i18n = i18nVue;

const SUPPORTED = ['en', 'de', 'ru'];

/**
 * The locale to boot with. Client-side so the SPA is independent of a
 * server-rendered <html lang>: a persisted choice (localStorage) wins, then the
 * document lang (same-origin Blade), then the browser language, then English.
 * Also stamps <html lang> so every documentElement.lang reader is correct.
 */
export function initialLocale(): string {
  const stored = localStorage.getItem('ll_locale');
  const html = document.documentElement.getAttribute('lang');
  const browser = (navigator.language || '').slice(0, 2).toLowerCase();
  const pick = [stored, html, browser].find((l) => l && SUPPORTED.includes(l)) || 'en';
  document.documentElement.setAttribute('lang', pick);
  return pick;
}

/** Persist + apply a locale change (called by the appearance switcher). */
export function persistLocale(locale: string): void {
  if (!SUPPORTED.includes(locale)) return;
  localStorage.setItem('ll_locale', locale);
  document.documentElement.setAttribute('lang', locale);
}
