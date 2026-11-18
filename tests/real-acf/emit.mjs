/**
 * Writes the pinned salt-contract emitter's ACF output (acfSnapshot) to the
 * path given, for the harness mu-plugin to register verbatim. The contract is
 * the theme's own exact pin (node_modules/@lightlysaltedhq/salt-contract at
 * the repository root), never a contracts checkout.
 *
 * The emitter needs the site's icons (features.items.icon offers them): they
 * are the theme's bundled icons, assets/src/icons/*.svg, named by file.
 * Nothing else is passed, so every section and source is emitted with the
 * contract's defaults.
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

const icons = fs
	.readdirSync( path.join( repo, 'assets/src/icons' ) )
	.filter( ( f ) => f.endsWith( '.svg' ) )
	.sort()
	.map( ( f ) => f.slice( 0, -4 ) )
	.map( ( value ) => ( {
		value,
		label: value
			.split( '-' )
			.map( ( w ) => w.charAt( 0 ).toUpperCase() + w.slice( 1 ) )
			.join( ' ' ),
	} ) );

const json = acfSnapshot( { icons } );
fs.writeFileSync( out, json );
console.log( `salt-contract ${ version }: emitted ${ json.length } bytes with ${ icons.length } icons` );
