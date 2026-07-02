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
		files: [ 'bin/**/*.mjs', 'eslint.config.js' ],
		languageOptions: {
			ecmaVersion: 2023,
			sourceType: 'module',
			globals: { ...globals.node },
		},
	},
];
