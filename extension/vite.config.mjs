import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = fileURLToPath(new URL('.', import.meta.url));

// Builds the extension's three entry points into extension/dist. The
// background service worker bundles libsodium (the only crypto surface);
// popup and content are plain modules that message the worker.
export default defineConfig({
    root: dir,
    base: './',
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        target: 'esnext',
        rollupOptions: {
            input: {
                background: resolve(dir, 'src/background.js'),
                content: resolve(dir, 'src/content.js'),
                popup: resolve(dir, 'src/popup.html'),
                'passkey-inject': resolve(dir, 'src/passkey-inject.js'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
});
