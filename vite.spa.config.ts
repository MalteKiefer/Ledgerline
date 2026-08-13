import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';

// Standalone SPA build — produces a self-contained static bundle in dist/spa
// that talks to the Laravel (or a future Go) backend purely over the API. No
// laravel-vite-plugin, no Blade: the entry is resources/js/spa/index.html.
//
//   VITE_API_URL   absolute API origin (e.g. https://api.example.com); empty = same origin
//   VITE_BASE      public base path the SPA is served from (default '/')
//   VITE_APP_VERSION  build number shown in the sidebar footer
//
// Build: `npm run build:spa`. Host dist/spa on any static server with an
// SPA history fallback (all non-file routes -> index.html).
export default defineConfig({
  root: fileURLToPath(new URL('./resources/js/spa', import.meta.url)),
  base: process.env.VITE_BASE || '/',
  plugins: [
    vue(),
    i18n('lang'),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      vue: 'vue/dist/vue.runtime.esm-bundler.js',
      '@spa': fileURLToPath(new URL('./resources/js/spa', import.meta.url)),
    },
  },
  build: {
    outDir: fileURLToPath(new URL('./dist/spa', import.meta.url)),
    emptyOutDir: true,
    // No inline modulepreload polyfill (CSP-clean); modern browsers support
    // <link rel="modulepreload"> natively.
    modulePreload: { polyfill: false },
  },
});
