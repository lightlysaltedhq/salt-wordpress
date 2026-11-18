import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		// tests/real-acf installs its own WordPress (and, in CI, ACF Pro) under vendor trees.
		ignores: [ 'assets/dist/**', 'vendor/**', 'node_modules/**', 'tests/real-acf/vendor/**', 'tests/real-acf/acf-composer/**' ],
	},
	js.configs.recommended,
	{
		files: [ 'assets/src/js/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2023,
			sourceType: 'module',
			globals: { ...globals.browser },
		},
	},
	{
		// Admin scripts are dual-context: classic script in wp-admin (acf,
		// saltContrast, jQuery are read via window.*) + a CommonJS-style
		// export seam for the colour-parity gate (bin/check-color-parity.mjs)
		// — `module` is the one extra global that needs declaring.
		files: [ 'assets/src/js/admin/**/*.js' ],
		languageOptions: {
			globals: { module: 'readonly' },
		},
	},
	{
		files: [ 'bin/**/*.mjs', 'tests/real-acf/*.mjs', 'eslint.config.js' ],
		languageOptions: {
			ecmaVersion: 2023,
			sourceType: 'module',
			globals: { ...globals.node },
		},
	},
	{
		// The harness's Playwright script also passes functions to page.evaluate(), which run
		// in the browser.
		files: [ 'tests/real-acf/browser.mjs' ],
		languageOptions: {
			globals: { ...globals.browser },
		},
	},
];
