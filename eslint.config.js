import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		ignores: [ 'assets/dist/**', 'vendor/**', 'node_modules/**' ],
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
		files: [ 'bin/**/*.mjs', 'eslint.config.js' ],
		languageOptions: {
			ecmaVersion: 2023,
			sourceType: 'module',
			globals: { ...globals.node },
		},
	},
];
