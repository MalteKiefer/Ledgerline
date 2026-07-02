import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// No external font provider is configured: the application uses the operating
// system's native font stack only, so nothing is fetched from a CDN at build
// time or at runtime.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
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
