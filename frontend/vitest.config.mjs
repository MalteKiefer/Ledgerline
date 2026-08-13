import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@spa': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    test: {
        include: [
            'src/**/*.test.js',
            'src/**/*.test.ts',
        ],
        environment: 'node',
    },
});
