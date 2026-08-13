import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';

// Standalone SPA build — independent of the Laravel backend, which it reaches
// purely over the API. Translations are compiled from the backend's PHP lang
// files (../backend/lang, the single source of truth). Output: dist/.
//
//   VITE_API_URL      absolute API origin (empty = same origin)
//   VITE_BASE         public base path (default '/')
//   VITE_APP_VERSION  version shown in the sidebar footer
export default defineConfig({
  base: process.env.VITE_BASE || '/',
  plugins: [
    vue(),
    i18n('../backend/lang'),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      vue: 'vue/dist/vue.runtime.esm-bundler.js',
      '@spa': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    modulePreload: { polyfill: false },
  },
  server: {
    watch: { ignored: ['**/dist/**'] },
  },
});
