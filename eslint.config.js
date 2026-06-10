/**
 * ESLint flat config for the Editoria11y WordPress plugin's hand-written
 * JS shims (assets/js/). The vendored library bundle in assets/lib/ comes
 * from upstream itmaybejj/editoria11y and is intentionally excluded.
 *
 * Rule baseline: @eslint/js recommended, with three deviations noted below.
 * Existing inline `// eslint-disable-...` comments in the source files
 * mirror the stricter rule set enforced by the upstream project; they are
 * preserved here (linterOptions.reportUnusedDisableDirectives = 'off') so
 * that toggling rules in this config does not require auditing comments.
 */

import js from '@eslint/js';
import globals from 'globals';

export default [
  {
    ignores: [
      'assets/lib/**',
      'build/**',
      'vendor/**',
      'node_modules/**',
    ],
  },
  js.configs.recommended,
  {
    files: ['assets/js/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        // WordPress injects these at runtime; ESLint can't see the
        // declarations. wpApiSettings is consumed by the front-end and
        // editor shims (their enqueues still depend on `wp-api`); the
        // dashboard cut over to a JSON config blob and no longer needs it.
        wp: 'readonly',
        wpApiSettings: 'readonly',
        Ed1: 'readonly',
        // Heartbeat fires events as jQuery custom events, and the
        // network-defaults backfill panel subscribes to them. The
        // bundled `jquery` script handle is a WP core enqueue dep.
        jQuery: 'readonly',
      },
    },
    linterOptions: {
      // Keep upstream-style eslint-disable comments as hints even when the
      // matching rule is locally permissive. Avoids drift between this
      // plugin and itmaybejj/editoria11y when a rule is toggled.
      reportUnusedDisableDirectives: 'off',
    },
    rules: {
      // The existing JS pattern is to console.warn / console.log on
      // graceful-failure paths; keeping no-console off avoids per-call
      // disables.
      'no-console': 'off',
      // Callbacks with stable WP signatures often don't use every
      // parameter (e.g., `function ($result, $server, $request)` mirrors
      // the REST filter contract). Match that. `caughtErrorsIgnorePattern`
      // honors the `_`-prefix convention the source already uses to mark a
      // caught error intentionally unused (`catch ( _err )`), so a
      // graceful-failure catch block that ignores the error doesn't warn.
      'no-unused-vars': ['warn', { args: 'none', caughtErrorsIgnorePattern: '^_' }],
    },
  },
  {
    // Vitest unit tests + their manual mocks (tests/js/unit/). These run
    // under jsdom, so they see the browser globals; `globals.vitest`
    // supplies describe/test/expect/beforeAll/vi etc. (the API the suite
    // uses with `globals: true` in vitest.config.js).
    //
    // Node globals are deliberately NOT spread in here: the suite is
    // native ESM, so `require`/`__dirname` should stay flagged as
    // no-undef. That guard is what catches a missed ESM conversion (the
    // node:fs/node:path/node:url helpers are reached via explicit
    // `import`, not globals).
    files: ['tests/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.vitest,
      },
    },
    rules: {
      'no-console': 'off',
    },
  },
  {
    // Root tooling configs (vitest.config.js, this file) are evaluated in
    // a Node context by Vite/ESLint, so they need the Node globals (URL,
    // process, etc.) that the browser/test blocks above don't supply.
    files: ['*.config.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.node,
      },
    },
  },
];
