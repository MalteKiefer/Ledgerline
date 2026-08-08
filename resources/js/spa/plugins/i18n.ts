import { i18nVue } from 'laravel-vue-i18n';

// Build-time PHP->JSON via laravel-vue-i18n/vite (langPath 'lang'); keeps
// lang/{en,de,ru}/*.php as the single source (EN/DE/RU parity) with Laravel
// trans() semantics. Lazy per-locale via import.meta.glob.
export const i18n = i18nVue;

export function resolveLang(lang: string) {
  const langs = import.meta.glob('../../../../lang/*.json');
  const key = `../../../../lang/php_${lang}.json`;
  const loader = langs[key];
  return loader ? loader() : Promise.resolve({ default: {} });
}

export function initialLocale(): string {
  const html = document.documentElement.getAttribute('lang');
  return html && ['en', 'de', 'ru'].includes(html) ? html : 'en';
}
