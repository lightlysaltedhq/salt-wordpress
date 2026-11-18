/**
 * Phase 3: the classic editor in a real browser (Playwright's Chromium).
 * HARNESS ONLY: run by tests/real-acf/run.sh against PHP's built-in server.
 *
 * 1. The fixtures page's edit screen loads every row and every layout, with
 *    no console errors or uncaught page errors.
 * 2. Conditional logic: on the probe page, a collection-query's mode shows
 *    categories or items, and a source without categories hides them.
 * 3. A required field blocks a save (an empty collection-showcase source);
 *    filling it lets the same page publish.
 * 4. A browser re-save of the fixtures page: every meta row before and after,
 *    straight from the database, each change explained or reported.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const url = process.env.SALT_HARNESS_URL;
const php = process.env.SALT_HARNESS_PHP_BIN;
const state = JSON.parse( fs.readFileSync( process.env.SALT_HARNESS_STATE, 'utf8' ) );
const emitted = JSON.parse( fs.readFileSync( process.env.SALT_HARNESS_ACF_JSON, 'utf8' ) );

let failures = 0;
const result = ( ok, check, detail = '' ) => {
	if ( ! ok ) {
		failures++;
	}
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};
const info = ( line ) => console.log( `     ${ line }` );

const meta = ( id ) =>
	JSON.parse( execFileSync( php, [ path.join( here, 'php/meta.php' ), String( id ) ], { encoding: 'utf8' } ) );
const publishedWithTitle = ( title ) =>
	Number( execFileSync( php, [ path.join( here, 'php/meta.php' ), '--published-title', title ], { encoding: 'utf8' } ) );

// Every emitted field by key, for explaining what a re-save changed.
const fieldsByKey = new Map();
const walk = ( list ) => {
	for ( const f of list ) {
		fieldsByKey.set( f.key, f );
		walk( f.sub_fields ?? [] );
		for ( const l of f.layouts ?? [] ) {
			walk( l.sub_fields ?? [] );
		}
	}
};
walk( emitted[ 0 ].fields );
const layoutNames = state.layouts;

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1400, height: 1000 } } );
context.setDefaultTimeout( 120000 );

// Chromium reports each `unload` listener as a console error now that its permissions policy
// denies unload by default. They come from WordPress core's admin scripts, not from ACF or the
// field shape, so they are counted and reported apart, by script, and do not fail a check.
const UNLOAD = 'Permissions policy violation: unload is not allowed in this document.';
const unloadBy = new Map();

// A page that records console errors and uncaught exceptions.
async function open( target ) {
	const page = await context.newPage();
	const errors = [];
	page.on( 'console', ( m ) => {
		if ( m.type() !== 'error' ) {
			return;
		}
		if ( m.text() === UNLOAD ) {
			const where = ( m.location().url || '(inline)' ).replace( url, '' ).replace( /\?.*$/, '' );
			unloadBy.set( where, ( unloadBy.get( where ) ?? 0 ) + 1 );
			return;
		}
		errors.push( `console: ${ m.text() } (${ ( m.location().url || '' ).replace( url, '' ) })` );
	} );
	page.on( 'pageerror', ( e ) => errors.push( `pageerror: ${ e.message }` ) );
	page.on( 'dialog', ( d ) => d.accept() );
	await page.goto( url + target, { waitUntil: 'load' } );
	return { page, errors };
}

async function close( page ) {
	await page.close( { runBeforeUnload: false } );
}

try {
	// ── Log in ────────────────────────────────────────────────────────────────────────────────
	{
		const { page } = await open( '/wp-login.php' );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', process.env.SALT_HARNESS_ADMIN_PASS );
		await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );
		result( true, 'logged in to wp-admin', page.url().replace( url, '' ) );
		await close( page );
	}

	const editUrl = ( id ) => `/wp-admin/post.php?post=${ id }&action=edit`;
	const rowsSel = '.acf-field[data-key="field_salt_sections_container"] .acf-flexible-content > .values > .layout';

	// ── 1. The fixtures page's edit screen ─────────────────────────────────────────────────────
	const before = meta( state.fixturesPage );
	const { page: edit, errors: editErrors } = await open( editUrl( state.fixturesPage ) );
	await edit.waitForSelector( rowsSel );
	await edit.waitForFunction( () => window.acf && document.readyState === 'complete' );
	await edit.waitForTimeout( 2000 );
	const screen = await edit.evaluate( ( sel ) => {
		const rows = [ ...document.querySelectorAll( sel ) ];
		const clones = [
			...document.querySelectorAll(
				'.acf-field[data-key="field_salt_sections_container"] .acf-flexible-content > .clones > .layout'
			),
		];
		return {
			rows: rows.map( ( r ) => r.dataset.layout ),
			fieldsPerRow: rows.map( ( r ) => r.querySelectorAll( '.acf-field[data-key]' ).length ),
			offered: clones.map( ( c ) => c.dataset.layout ),
			blockEditor: !! document.querySelector( '.block-editor' ),
			classic: !! document.querySelector( '#post #publish' ),
			wysiwyg: document.querySelectorAll( `${ sel } .acf-field-wysiwyg` ).length,
		};
	}, rowsSel );
	const missing = layoutNames.filter( ( l ) => ! screen.rows.includes( l ) );
	const notOffered = layoutNames.filter( ( l ) => ! screen.offered.includes( l ) );
	result(
		screen.classic && ! screen.blockEditor,
		'classic edit screen',
		screen.classic ? 'classic editor with the Publish box (block editor off, as the theme sets)' : 'no classic edit form'
	);
	result(
		screen.rows.length === state.rows.length && JSON.stringify( screen.rows ) === JSON.stringify( state.rows.map( ( r ) => r.split( '/' )[ 0 ] ) ),
		'edit screen renders every saved row',
		`${ screen.rows.length } rows rendered of ${ state.rows.length } saved, ${ screen.fieldsPerRow.reduce( ( a, b ) => a + b, 0 ) } field wrappers, ${ screen.wysiwyg } wysiwyg editors`
	);
	result(
		missing.length === 0 && notOffered.length === 0,
		'every layout loads and is offered by Add section',
		`${ layoutNames.length - missing.length } of ${ layoutNames.length } layouts rendered, ${ screen.offered.length } offered${ missing.length ? `; missing ${ missing.join( ', ' ) }` : '' }${ notOffered.length ? `; not offered ${ notOffered.join( ', ' ) }` : '' }`
	);
	const emptyRows = screen.fieldsPerRow.map( ( n, i ) => [ n, state.rows[ i ] ] ).filter( ( [ n ] ) => n === 0 );
	result( emptyRows.length === 0, 'every row renders its fields', emptyRows.length ? emptyRows.map( ( r ) => r[ 1 ] ).join( ', ' ) : 'no row without fields' );
	result( editErrors.length === 0, 'no console errors on the edit screen', `${ editErrors.length } errors` );
	editErrors.slice( 0, 20 ).forEach( info );

	// ── 4. Re-save the fixtures page through the browser ──────────────────────────────────────
	// PHP drops every POST variable past max_input_vars (1000 by default), and ACF then saves a
	// truncated sections list; the harness server raises it, and this records what the page needs.
	const posted = await edit.evaluate( () =>
		[ ...document.querySelector( 'form#post' ).elements ].filter(
			( e ) => e.name && ! e.disabled && ! ( [ 'checkbox', 'radio' ].includes( e.type ) && ! e.checked )
		).length
	);
	const limit = Number( process.env.SALT_HARNESS_MAX_INPUT_VARS );
	result(
		posted <= limit,
		'the re-save fits in max_input_vars',
		`the form posts ${ posted } variables; the harness server allows ${ limit } (PHP's default of 1000 would truncate this page)`
	);
	const started = Date.now();
	await Promise.all( [
		edit.waitForURL( /message=1/, { timeout: 600000 } ),
		edit.click( '#publish' ),
	] );
	const seconds = ( ( Date.now() - started ) / 1000 ).toFixed( 1 );
	const validationShown = await edit.locator( '.acf-notice.-error' ).count();
	result( validationShown === 0, 'browser re-save accepted', `Update returned ${ edit.url().replace( url, '' ) } after ${ seconds } s (validation and save)` );
	await close( edit );
	const after = meta( state.fixturesPage );
	reportResave( before, after );

	// ── 2. Conditional logic on the probe page ────────────────────────────────────────────────
	{
		const { page, errors } = await open( editUrl( state.probePage ) );
		await page.waitForSelector( rowsSel );
		await page.waitForFunction( () => window.acf && document.readyState === 'complete' );
		const row = page.locator( `${ rowsSel }[data-layout="collection-showcase"]` ).first();
		const field = ( name ) => row.locator( `.acf-field[data-key="field_salt_collection_showcase_${ name }"]` );
		const visible = async () => {
			const out = {};
			for ( const n of [ 'categories', 'items', 'order', 'count' ] ) {
				out[ n ] = await field( `query_${ n }` ).isVisible();
			}
			return out;
		};
		const steps = [
			[ 'posts', 'by-category', { categories: true, items: false, order: true, count: true } ],
			[ 'posts', 'manual', { categories: false, items: true, order: false, count: false } ],
			[ 'posts', 'automatic', { categories: false, items: false, order: true, count: true } ],
			[ 'testimonials', 'by-category', { categories: false, items: false, order: true, count: true } ],
			[ 'services', 'by-category', { categories: true, items: false, order: true, count: true } ],
		];
		for ( const [ source, mode, want ] of steps ) {
			await field( 'source' ).locator( 'select' ).selectOption( source );
			await field( 'query_mode' ).locator( 'select' ).selectOption( mode );
			await page.waitForTimeout( 300 );
			const got = await visible();
			const shown = Object.keys( got ).filter( ( k ) => got[ k ] );
			result(
				JSON.stringify( got ) === JSON.stringify( want ),
				`conditional logic: source ${ source }, mode ${ mode }`,
				`shows ${ shown.length ? shown.join( ', ' ) : 'none of categories, items, order, count' }`
			);
		}
		result( errors.length === 0, 'no console errors while switching modes', `${ errors.length } errors` );
		errors.slice( 0, 10 ).forEach( info );
		await close( page );
	}

	// ── 3. A required field blocks a save ─────────────────────────────────────────────────────
	{
		const title = `Harness: required field ${ Date.now() }`;
		const { page, errors } = await open( '/wp-admin/post-new.php?post_type=page' );
		await page.waitForFunction( () => window.acf && document.readyState === 'complete' );
		await page.fill( '#title', title );
		// ACF renders an Add section link above and below the rows; only one is visible.
		await page
			.locator( '.acf-field[data-key="field_salt_sections_container"] a[data-name="add-layout"]' )
			.filter( { visible: true } )
			.first()
			.click();
		await page.click( '.acf-fc-popup a[data-layout="collection-showcase"]' );
		const row = page.locator( `${ rowsSel }[data-layout="collection-showcase"]` ).first();
		await row.waitFor();
		// A new title starts WordPress's first autosave, which marks Publish disabled (clicks are
		// ignored) until it has saved the draft and shown the permalink.
		await page.locator( '#title' ).blur();
		await page.waitForSelector( '#edit-slug-box #sample-permalink', { timeout: 60000 } );
		await page.waitForSelector( '#publish:not(.disabled)' );
		const isValidation = ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'acf%2Fvalidate_save_post' );
		await Promise.all( [ page.waitForResponse( isValidation, { timeout: 60000 } ), page.click( '#publish' ) ] );
		await page.waitForSelector( '.acf-notice.-error', { timeout: 60000 } );
		const fieldError = await row
			.locator( '.acf-field[data-key="field_salt_collection_showcase_source"] .acf-notice.-error' )
			.textContent()
			.catch( () => '' );
		await page.waitForTimeout( 1000 );
		const blocked = publishedWithTitle( title ) === 0 && /post-new\.php/.test( page.url() );
		result(
			blocked && fieldError.trim() !== '',
			'required field blocks the save',
			`collection-showcase source left empty: ACF says "${ fieldError.trim() }", page not published (still on ${ page.url().replace( url, '' ) })`
		);
		await row.locator( '.acf-field[data-key="field_salt_collection_showcase_source"] select' ).selectOption( 'posts' );
		await Promise.all( [ page.waitForURL( /post\.php\?post=\d+&action=edit/, { timeout: 120000 } ), page.click( '#publish' ) ] );
		result(
			publishedWithTitle( title ) === 1,
			'filling the required field lets it publish',
			`source set to posts, page published (${ page.url().replace( url, '' ) })`
		);
		result( errors.length === 0, 'no console errors while validating', `${ errors.length } errors` );
		errors.slice( 0, 10 ).forEach( info );
		await close( page );
	}
	const unloadTotal = [ ...unloadBy.values() ].reduce( ( a, b ) => a + b, 0 );
	info(
		`${ unloadTotal } Chromium "unload is not allowed" policy reports across all screens, not counted as errors; by script: ${
			[ ...unloadBy ].map( ( [ k, n ] ) => `${ k } ${ n }` ).join( ', ' ) || 'none'
		}`
	);
} catch ( e ) {
	result( false, 'browser phase ran to the end', e.message.split( '\n' )[ 0 ] );
} finally {
	await browser.close();
}

process.exit( failures > 0 ? 1 : 0 );

// ── The re-save diff ──────────────────────────────────────────────────────────────────────────

/**
 * Compare every meta row before and after the browser re-save; explain each
 * change or report it as unexplained (a failure).
 *
 * @param {{status: string, meta: Object<string,string>}} was Before.
 * @param {{status: string, meta: Object<string,string>}} now After.
 */
function reportResave( was, now ) {
	const ignored = new Set( [ '_edit_lock', '_edit_last' ] );
	const counts = {};
	const unexplained = [];
	const examples = {};
	const note = ( why, line ) => {
		counts[ why ] = ( counts[ why ] ?? 0 ) + 1;
		( examples[ why ] ??= [] ).length < 2 && examples[ why ].push( line );
	};
	const keys = new Set( [ ...Object.keys( was.meta ), ...Object.keys( now.meta ) ] );
	let unchanged = 0;
	const wysiwyg = [];
	for ( const k of keys ) {
		if ( ignored.has( k ) ) {
			continue;
		}
		const a = was.meta[ k ];
		const b = now.meta[ k ];
		if ( a === b ) {
			unchanged++;
			continue;
		}
		const field = fieldsByKey.get( now.meta[ `_${ k }` ] ?? was.meta[ `_${ k }` ] ?? ( k.startsWith( '_' ) ? b : '' ) );
		const type = field?.type ?? '?';
		const line = `${ k } (${ type }): ${ JSON.stringify( a ) } -> ${ JSON.stringify( b ) }`;
		if ( k.startsWith( '_' ) && a === undefined && fieldsByKey.has( b ) ) {
			note( 'key reference added for a newly stored field', line );
		} else if ( a === undefined ) {
			if ( isDefault( field, b ) ) {
				note( 'field the fixture left unset, now stored at its default or empty', line );
			} else {
				unexplained.push( `added ${ line }` );
			}
		} else if ( b === undefined ) {
			unexplained.push( `removed ${ line }` );
		} else if ( type === 'textarea' && a.replace( /\n/g, '\r\n' ) === b ) {
			note( 'textarea line endings \\n -> \\r\\n (browser form submission)', line );
		} else if ( type === 'wysiwyg' ) {
			wysiwyg.push( [ a, b, line ] );
		} else if ( type === 'true_false' && a === '' && b === '0' ) {
			note( 'unchecked true_false "" (update_field false) -> "0" (form)', line );
		} else if ( sameList( a, b ) ) {
			note( 'same id list, serialised as strings by the form', line );
		} else {
			unexplained.push( `changed ${ line }` );
		}
	}
	// TinyMCE (with wpautop) saves paragraphs as line breaks; ACF's acf_the_content puts them
	// back. A change is explained when both versions render the same HTML.
	if ( wysiwyg.length ) {
		const rendered = JSON.parse(
			execFileSync( php, [ path.join( here, 'php/meta.php' ), '--the-content' ], {
				input: JSON.stringify( wysiwyg.flatMap( ( [ a, b ] ) => [ a, b ] ) ),
				encoding: 'utf8',
			} )
		);
		const tidy = ( h ) => h.replace( />\s+</g, '><' ).trim();
		wysiwyg.forEach( ( [ , , line ], i ) => {
			if ( tidy( rendered[ 2 * i ] ) === tidy( rendered[ 2 * i + 1 ] ) ) {
				note( 'wysiwyg paragraphs saved as line breaks by the editor (acf_the_content renders both the same)', line );
			} else {
				unexplained.push( `changed ${ line }; renders ${ JSON.stringify( rendered[ 2 * i ] ) } vs ${ JSON.stringify( rendered[ 2 * i + 1 ] ) }` );
			}
		} );
	}
	result( was.status === now.status, 'post status kept', `${ was.status } -> ${ now.status }` );
	result(
		unexplained.length === 0,
		'browser re-save changes nothing unexplained',
		`${ keys.size } meta keys: ${ unchanged } unchanged, ${ Object.values( counts ).reduce( ( x, y ) => x + y, 0 ) } explained changes, ${ unexplained.length } unexplained`
	);
	for ( const [ why, n ] of Object.entries( counts ) ) {
		info( `${ n } x ${ why }; e.g. ${ examples[ why ].join( ' | ' ) }` );
	}
	unexplained.slice( 0, 40 ).forEach( ( l ) => info( `UNEXPLAINED ${ l }` ) );
}

function unserialiseList( s ) {
	if ( s === '' ) {
		return [];
	}
	const m = s.match( /^a:\d+:\{(.*)\}$/ );
	if ( ! m ) {
		return null;
	}
	return [ ...m[ 1 ].matchAll( /i:\d+;(?:s:\d+:"([^"]*)"|i:(\d+));/g ) ].map( ( x ) => String( x[ 1 ] ?? x[ 2 ] ) );
}

function sameList( a, b ) {
	const x = unserialiseList( a );
	const y = unserialiseList( b );
	return x !== null && y !== null && JSON.stringify( x ) === JSON.stringify( y );
}

/**
 * Whether a value the form stored for a field the fixture did not set is that
 * field's default (or nothing).
 *
 * @param {Object|undefined} field ACF field.
 * @param {string}           value Stored value.
 * @return {boolean} Whether it is the default.
 */
function isDefault( field, value ) {
	if ( ! field ) {
		return false;
	}
	if ( [ 'group', 'repeater', 'gallery', 'relationship', 'post_object', 'image', 'taxonomy' ].includes( field.type ) ) {
		return value === '' || value === '0' || value === 'a:0:{}';
	}
	if ( field.type === 'true_false' ) {
		return value === String( field.default_value ?? 0 );
	}
	if ( field.default_value !== undefined ) {
		return value === String( field.default_value );
	}
	return value === '';
}
