import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';

// No external font provider is configured: the application uses the operating
// system's native font stack only, so nothing is fetched from a CDN at build
// time or at runtime.
export default defineConfig({
    plugins: [
        laravel({
            // Legacy Blade+Alpine bundle (app.js) stays live until the SPA cutover;
            // the Vue 3 SPA (Tailwind + Reka UI) boots from spa/main.ts.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/spa/main.ts'],
            refresh: true,
        }),
        vue(),
        i18n('lang'),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            // Runtime-only Vue build: no in-browser template compiler, so the SPA
            // never needs CSP 'unsafe-eval' (dropped at cutover once Alpine is gone).
            vue: 'vue/dist/vue.runtime.esm-bundler.js',
            '@spa': fileURLToPath(new URL('./resources/js/spa', import.meta.url)),
        },
    },
    build: {
        // Do not inject Vite's inline modulepreload-polyfill <script>: it would
        // violate our Content-Security-Policy (script-src has no 'unsafe-inline'
        // and the app ships no other inline scripts). Modern browsers support
        // <link rel="modulepreload"> natively, so the preload links still work.
        modulePreload: { polyfill: false },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
