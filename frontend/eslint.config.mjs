import tsParser from '@typescript-eslint/parser';
import tsPlugin from '@typescript-eslint/eslint-plugin';

// Correctness-only lint for the Vue SPA's TypeScript (stores/lib/api/router).
// Style is not enforced here; the point is to catch unused symbols and obvious
// mistakes that vue-tsc's typecheck (the primary gate) does not surface as
// errors. .vue single-file components are covered by `npm run typecheck`.
export default [
  {
    files: ['src/**/*.ts'],
    languageOptions: {
      parser: tsParser,
      ecmaVersion: 2023,
      sourceType: 'module',
    },
    plugins: { '@typescript-eslint': tsPlugin },
    rules: {
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': ['error', { args: 'none', varsIgnorePattern: '^_', caughtErrors: 'none' }],
    },
  },
];
