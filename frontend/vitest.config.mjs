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
        // Most suites are pure logic and run fastest in node; the ones that touch
        // the DOM (the Markdown sanitiser) opt in per file with
        // `@vitest-environment jsdom`.
        environment: 'node',
    },
});
