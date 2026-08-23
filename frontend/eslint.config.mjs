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
      // Translations come from laravel-vue-i18n (lang/*.php is the source), so
      // vue-i18n's own composable is never installed and useI18n() throws at
      // runtime -- which typecheck and build cannot see, because the package is
      // present as a transitive dependency. Cost a release in v1.722.4.
      'no-restricted-imports': ['error', {
        paths: [{ name: 'vue-i18n', message: "Use: import { trans as t } from 'laravel-vue-i18n'" }],
      }],
      '@typescript-eslint/no-unused-vars': ['error', { args: 'none', varsIgnorePattern: '^_', caughtErrors: 'none' }],
    },
  },
];
