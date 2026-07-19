import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: [
            'resources/js/**/*.test.js',
            'extension/src/**/*.test.js',
        ],
        environment: 'node',
    },
});
