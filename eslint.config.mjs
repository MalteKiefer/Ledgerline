import globals from 'globals';

// Correctness-only lint (no style rules — Pint/Prettier are not run on this
// codebase). The point is to catch undefined references (e.g. a helper that was
// not imported after the app.js modularization) that the bundler wouldn't flag.
export default [
    {
        files: ['resources/js/**/*.js', 'extension/src/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.worker,
                Alpine: 'readonly',
                chrome: 'readonly',
            },
        },
        rules: {
            'no-undef': 'error',
        },
    },
];
