/**
 * Writes the pinned salt-contract emitter's ACF output (acfSnapshot) to the
 * path given, for the harness mu-plugin to register verbatim. The contract is
 * the theme's own exact pin (node_modules/@lightlysaltedhq/salt-contract at
 * the repository root), never a contracts checkout.
 *
 * The options are the theme's own (inc/fields/generated/sections.options.json:
 * the sections the theme renders and its bundled icons), so the output is
 * what the theme registers; every source is emitted with the contract's
 * defaults.
 *
 * Usage: node tests/real-acf/emit.mjs <out.json>
 */

import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const repo = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const out = process.argv[ 2 ];
if ( ! out ) {
	console.error( 'usage: emit.mjs <out.json>' );
	process.exit( 2 );
}

// Resolve from the repository root, so the theme's pinned copy is the one read.
const require = createRequire( path.join( repo, 'package.json' ) );
const pkgPath = require.resolve( '@lightlysaltedhq/salt-contract/package.json' );
const { version } = JSON.parse( fs.readFileSync( pkgPath, 'utf8' ) );
const { acfSnapshot } = await import( require.resolve( '@lightlysaltedhq/salt-contract/emit/acf' ) );

// The theme's own options (sections + icons), as bin/check-fields-from-contract.php uses them:
// the emitter's output must then be the theme's committed inc/fields/generated/sections.json.
const options = JSON.parse( fs.readFileSync( path.join( repo, 'inc/fields/generated/sections.options.json' ), 'utf8' ) );
const icons = options.icons;

const json = acfSnapshot( options );
fs.writeFileSync( out, json );
const committed = fs.readFileSync( path.join( repo, 'inc/fields/generated/sections.json' ), 'utf8' );
console.log(
	`salt-contract ${ version }: emitted ${ json.length } bytes, ${ options.sections.length } sections, ${ icons.length } icons; ${
		json === committed ? 'identical to' : 'DIFFERENT FROM'
	} the theme's inc/fields/generated/sections.json`
);
