import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@spa': fileURLToPath(new URL('./resources/js/spa', import.meta.url)),
        },
    },
    test: {
        include: [
            'resources/js/**/*.test.js',
            'resources/js/**/*.test.ts',
        ],
        environment: 'node',
    },
});
