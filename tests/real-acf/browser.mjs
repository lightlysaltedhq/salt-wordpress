/**
 * Phase 3: the classic editor in a real browser (Playwright's Chromium).
 * HARNESS ONLY: run by tests/real-acf/run.sh against PHP's built-in server.
 *
 * 1. The fixtures page's edit screen loads every row and every layout, with
 *    no console errors or uncaught page errors.
 * 2. Conditional logic: on the probe page, the faq section's collection
 *    query mode shows categories or items, and order and count unless manual.
 * 3. A required field blocks a save (a features item with no title);
 *    filling it lets the same page publish.
 * 4. A browser re-save of the fixtures page: every meta row before and after,
 *    straight from the database, each change explained or reported.
 * 5. Each rich-text editor offers only its field's contract list: the
 *    rich-text body h2 to h6, the call-to-action body and a case study's
 *    challenge h3 and h4, a features item, an FAQ answer and the service's
 *    intro (its own editor) no heading. Read from the live TinyMCE, and
 *    proved by what it keeps of headings 2 to 6 set into it.
 * 6. The Topics box and term screens offer no parent; the service editor has
 *    no Add Media button and Text tab buttons for its list alone; a service
 *    or post summary of 301 two-byte characters is refused and 300 published;
 *    the post fields unit 10 wires are not drawn.
 * 7. The url format on save: a service's external cta link and a case
 *    study's externalUrl refuse /contact, #pricing, //x.example, javascript:
 *    and ftp:, and take https and mailto.
 * 8. The title limit: on each collection's edit screen a title of 121
 *    two-byte characters is refused with the message and not saved, and
 *    one of 120 is not refused (a post then publishes).
 * 9. The topic name limit: a name of 121 two-byte characters is refused with
 *    the message and not saved, and one of 120 is saved, on the term add and
 *    edit screens and in the Topics box of a service, case study, FAQ and
 *    location (by the button, by Enter and as one part of a comma list).
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
const publishedWithTitle = ( title, type = 'page' ) =>
	Number( execFileSync( php, [ path.join( here, 'php/meta.php' ), '--published-title', title, type ], { encoding: 'utf8' } ) );

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
		const row = page.locator( `${ rowsSel }[data-layout="faq"]` ).first();
		const field = ( name ) => row.locator( `.acf-field[data-key="field_salt_faq_${ name }"]` );
		const visible = async () => {
			const out = {};
			for ( const n of [ 'categories', 'items', 'order', 'count' ] ) {
				out[ n ] = await field( `query_${ n }` ).isVisible();
			}
			return out;
		};
		const steps = [
			[ 'by-category', { categories: true, items: false, order: true, count: true } ],
			[ 'manual', { categories: false, items: true, order: false, count: false } ],
			[ 'automatic', { categories: false, items: false, order: true, count: true } ],
		];
		for ( const [ mode, want ] of steps ) {
			await field( 'query_mode' ).locator( 'select' ).selectOption( mode );
			await page.waitForTimeout( 300 );
			const got = await visible();
			const shown = Object.keys( got ).filter( ( k ) => got[ k ] );
			result(
				JSON.stringify( got ) === JSON.stringify( want ),
				`conditional logic: faq query, mode ${ mode }`,
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
		await page.click( '.acf-fc-popup a[data-layout="features"]' );
		const row = page.locator( `${ rowsSel }[data-layout="features"]` ).first();
		await row.waitFor();
		// One feature item, its required title left empty.
		const items = row.locator( '.acf-field[data-key="field_salt_features_items"]' );
		await items.locator( 'a[data-event="add-row"]' ).filter( { visible: true } ).first().click();
		const itemTitle = items.locator( '.acf-row:not(.acf-clone) .acf-field[data-key="field_salt_features_items_title"]' ).first();
		await itemTitle.waitFor();
		// A new title starts WordPress's first autosave, which marks Publish disabled (clicks are
		// ignored) until it has saved the draft and shown the permalink.
		await page.locator( '#title' ).blur();
		await page.waitForSelector( '#edit-slug-box #sample-permalink', { timeout: 60000 } );
		await page.waitForSelector( '#publish:not(.disabled)' );
		const isValidation = ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'acf%2Fvalidate_save_post' );
		await Promise.all( [ page.waitForResponse( isValidation, { timeout: 60000 } ), page.click( '#publish' ) ] );
		await page.waitForSelector( '.acf-notice.-error', { timeout: 60000 } );
		const fieldError = await itemTitle
			.locator( '.acf-notice.-error' )
			.textContent()
			.catch( () => '' );
		await page.waitForTimeout( 1000 );
		const blocked = publishedWithTitle( title ) === 0 && /post-new\.php/.test( page.url() );
		result(
			blocked && fieldError.trim() !== '',
			'required field blocks the save',
			`features item title left empty: ACF says "${ fieldError.trim() }", page not published (still on ${ page.url().replace( url, '' ) })`
		);
		await itemTitle.locator( 'input' ).fill( 'Harness feature' );
		await Promise.all( [ page.waitForURL( /post\.php\?post=\d+&action=edit/, { timeout: 120000 } ), page.click( '#publish' ) ] );
		result(
			publishedWithTitle( title ) === 1,
			'filling the required field lets it publish',
			`title filled, page published (${ page.url().replace( url, '' ) })`
		);
		result( errors.length === 0, 'no console errors while validating', `${ errors.length } errors` );
		errors.slice( 0, 10 ).forEach( info );
		await close( page );
	}
	// ── 4b. The collection sections' query: categories follow source and mode; by-category is
	// refused for a source without categories (salt.modeRequires, inc/fields/collection-query.php)
	{
		const title = `Harness: collection query ${ Date.now() }`;
		const { page, errors } = await open( '/wp-admin/post-new.php?post_type=page' );
		await page.waitForFunction( () => window.acf && document.readyState === 'complete' );
		await page.fill( '#title', title );
		const rows = {};
		for ( const layout of [ 'collection-showcase', 'carousel' ] ) {
			await page
				.locator( '.acf-field[data-key="field_salt_sections_container"] a[data-name="add-layout"]' )
				.filter( { visible: true } )
				.first()
				.click();
			await page.click( `.acf-fc-popup a[data-layout="${ layout }"]` );
			rows[ layout ] = page.locator( `${ rowsSel }[data-layout="${ layout }"]` ).first();
			await rows[ layout ].waitFor();
		}
		const field = ( layout, name ) => rows[ layout ].locator( `.acf-field[data-key="field_salt_${ layout.replace( '-', '_' ) }_${ name }"]` );
		const choose = async ( layout, name, value ) => {
			await field( layout, name ).locator( 'select' ).first().selectOption( value );
			await page.waitForTimeout( 300 );
		};
		const steps = [
			[ 'collection-showcase', 'services', 'by-category', { categories: true } ],
			[ 'collection-showcase', 'team', 'by-category', { categories: false } ],
			[ 'collection-showcase', 'posts', 'by-category', { categories: true } ],
			[ 'collection-showcase', 'posts', 'automatic', { categories: false } ],
			[ 'carousel', 'inline', null, { query: false, cards: true, card_style: true } ],
			[ 'carousel', 'case-studies', 'by-category', { query: true, categories: true, cards: false } ],
			[ 'carousel', 'testimonials', 'by-category', { query: true, categories: false, cards: false } ],
		];
		for ( const [ layout, source, mode, want ] of steps ) {
			await choose( layout, 'source', source );
			if ( mode ) {
				await choose( layout, 'query_mode', mode );
			}
			const got = {};
			for ( const name of Object.keys( want ) ) {
				got[ name ] = await field( layout, name === 'categories' ? 'query_categories' : name ).isVisible();
			}
			result(
				JSON.stringify( got ) === JSON.stringify( want ),
				`conditional logic: ${ layout }, source ${ source }${ mode ? `, mode ${ mode }` : '' }`,
				Object.entries( got )
					.map( ( [ k, v ] ) => `${ k } ${ v ? 'shown' : 'hidden' }` )
					.join( ', ' )
			);
		}

		// Both rows at by-category on a source without categories: the save is refused on each mode field.
		await choose( 'collection-showcase', 'source', 'team' );
		await choose( 'collection-showcase', 'query_mode', 'by-category' );
		await choose( 'carousel', 'source', 'testimonials' );
		await choose( 'carousel', 'query_mode', 'by-category' );
		await page.locator( '#title' ).blur();
		await page.waitForSelector( '#edit-slug-box #sample-permalink', { timeout: 60000 } );
		await page.waitForSelector( '#publish:not(.disabled)' );
		const isValidation = ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'acf%2Fvalidate_save_post' );
		await Promise.all( [ page.waitForResponse( isValidation, { timeout: 60000 } ), page.click( '#publish' ) ] );
		await page.waitForSelector( '.acf-notice.-error', { timeout: 60000 } );
		await page.waitForTimeout( 1000 );
		for ( const layout of [ 'collection-showcase', 'carousel' ] ) {
			const message = ( await field( layout, 'query_mode' ).locator( '.acf-notice.-error' ).textContent().catch( () => '' ) ).trim();
			result(
				message.startsWith( 'By category works only for' ),
				`by-category on a source without categories is refused: ${ layout }`,
				message !== '' ? `the mode field says "${ message }"` : 'no message on the mode field'
			);
		}
		result( publishedWithTitle( title ) === 0, 'the refused page is not published', page.url().replace( url, '' ) );

		await choose( 'collection-showcase', 'source', 'services' );
		await choose( 'carousel', 'source', 'case-studies' );
		await Promise.all( [ page.waitForURL( /post\.php\?post=\d+&action=edit/, { timeout: 120000 } ), page.click( '#publish' ) ] );
		result( publishedWithTitle( title ) === 1, 'by-category on a source with categories publishes', `services and case studies (${ page.url().replace( url, '' ) })` );
		result( errors.length === 0, 'no console errors on the collection query checks', `${ errors.length } errors` );
		errors.slice( 0, 10 ).forEach( info );
		await close( page );
	}

	// ── 4c. Analytics on the contract's markup ─────────────────────────────────────────────────
	// The bands collection-showcase and carousel draw carry data-track, not data-section. The
	// markup is the contract fixtures' (the theme draws it byte for byte: npm run
	// conformance:contract); the scripts and stylesheet are the built ones from assets/dist.
	{
		const theme = path.resolve( here, '../..' );
		const manifest = JSON.parse( fs.readFileSync( path.join( theme, 'assets/dist/manifest.json' ), 'utf8' ) );
		const dist = ( entry ) => fs.readFileSync( path.join( theme, 'assets/dist', manifest[ entry ] ), 'utf8' );
		const fixtures = path.join( theme, 'node_modules/@lightlysaltedhq/salt-contract/fixtures' );
		const bands = [ 'collection-showcase/grid-services-first', 'collection-showcase/accordion-testimonials', 'carousel/inline-generic-heading' ]
			.map( ( c ) => fs.readFileSync( path.join( fixtures, `${ c }.html` ), 'utf8' ) )
			.join( '<div style="height:150vh"></div>' );
		const page = await context.newPage();
		// Narrow, so three slides overflow the track and the next control is not at its edge.
		await page.setViewportSize( { width: 480, height: 900 } );
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		// The served stylesheet lays the track out as a scroll-snapping row.
		const css = dist( 'salt.css' );
		await page.setContent( `<!doctype html><html><head><style>${ css }</style></head><body><div style="height:150vh"></div>${ bands }</body></html>` );
		await page.evaluate( () => {
			window.saltEvents = [];
			window.saltCarousel = { labels: { previous: 'Previous', next: 'Next' } };
			window.salt = { posthog: { capture: ( name, props ) => window.saltEvents.push( [ name, props.section ] ) } };
			window.requestIdleCallback = ( cb ) => cb();
			document.addEventListener( 'click', ( e ) => e.target.closest( 'a' ) && e.preventDefault() );
		} );
		await page.addScriptTag( { content: dist( 'sections/carousel.js' ) } );
		await page.addScriptTag( { content: dist( 'analytics/events.js' ) } );
		await page.locator( '[data-block="collection-showcase"][data-source="services"]' ).scrollIntoViewIfNeeded();
		await page.locator( '.salt-showcase__view-all a' ).click();
		await page.locator( 'details.salt-accordion__item summary' ).first().click();
		await page.locator( '.salt-carousel__control[data-direction="next"]' ).click();
		await page.waitForTimeout( 500 );
		const events = await page.evaluate( () => window.saltEvents );
		const has = ( name, section ) => events.some( ( [ n, s ] ) => n === name && s === section );
		for ( const [ name, section ] of [
			[ 'section_view', 'collection-showcase' ],
			[ 'cta_click', 'collection-showcase' ],
			[ 'accordion_open', 'collection-showcase' ],
			[ 'carousel_interact', 'carousel' ],
		] ) {
			result( has( name, section ), `analytics: ${ name } fires on a contract band`, `section "${ section }"; seen: ${ events.map( ( e ) => e.join( ' ' ) ).join( ', ' ) }` );
		}
		result( errors.length === 0, 'no page errors in the analytics check', `${ errors.length } errors` );
		await page.close();
	}

	// ── 5. Each rich-text editor offers only its field's list ────────────────────────────────
	// salt_wysiwyg_field_editor() and admin/rich-text-editor.js narrow each ACF editor to its
	// contract list (salt.allowed); the service's own editor holds its intro. For each, read the
	// live TinyMCE's format menu and toolbar, then set headings 2 to 6 and read back what the
	// editor keeps. Nothing is saved.
	{
		const probe = ( key ) => {
			const wrap = [ ...document.querySelectorAll( `.acf-field[data-key="${ key }"]` ) ].find(
				( el ) => ! el.closest( '.clones, .acf-clone' ) && el.querySelector( 'textarea.wp-editor-area' )
			);
			const id = key === 'content' ? 'content' : wrap?.querySelector( 'textarea.wp-editor-area' ).id;
			const ed = id && window.tinymce ? window.tinymce.get( id ) : null;
			if ( ! ed ) {
				return null;
			}
			const formats = ( ed.settings.block_formats || '' ).split( ';' ).map( ( f ) => f.split( '=' )[ 1 ] );
			ed.setContent( '<h2>a</h2><h3>b</h3><h4>c</h4><h5>d</h5><h6>e</h6><blockquote><p>f</p></blockquote>' );
			const kept = [ ...ed.getBody().querySelectorAll( 'h2,h3,h4,h5,h6,blockquote' ) ].map( ( n ) => n.nodeName.toLowerCase() );
			return { formats, toolbar: ed.settings.toolbar1 || '', kept };
		};
		const want = [
			[ 'fixtures page', editUrl( state.fixturesPage ), 'field_salt_rich_text_body', [ 'p', 'h2', 'h3', 'h4', 'h5', 'h6' ], [ 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' ] ],
			[ 'fixtures page', null, 'field_salt_call_to_action_body', [ 'p', 'h3', 'h4' ], [ 'h3', 'h4', 'blockquote' ] ],
			[ 'fixtures page', null, 'field_salt_features_items_text', [ 'p' ], [ 'blockquote' ] ],
			[ 'a new FAQ', '/wp-admin/post-new.php?post_type=faq', 'field_salt_collection_faqs_answer', [ 'p' ], [] ],
			[ 'a new case study', '/wp-admin/post-new.php?post_type=work', 'field_salt_collection_case_studies_challenge', [ 'p', 'h3', 'h4' ], [ 'h3', 'h4' ] ],
			[ 'a new service', '/wp-admin/post-new.php?post_type=service', 'content', [ 'p' ], [] ],
		];
		let screen = null;
		for ( const [ where, target, key, formats, kept ] of want ) {
			if ( target ) {
				if ( screen ) {
					result( screen.errors.length === 0, `no console errors in the editors on ${ screen.where }`, `${ screen.errors.length } errors` );
					screen.errors.slice( 0, 10 ).forEach( info );
					await close( screen.page );
				}
				screen = { ...( await open( target ) ), where };
				await screen.page.waitForFunction( () => window.acf && window.tinymce && document.readyState === 'complete' );
				await screen.page.waitForTimeout( 1500 );
			}
			const got = await screen.page.evaluate( probe, key );
			const headingButton = formats.length > 1;
			const ok =
				got !== null &&
				JSON.stringify( got.formats ) === JSON.stringify( formats ) &&
				JSON.stringify( got.kept ) === JSON.stringify( kept ) &&
				got.toolbar.includes( 'formatselect' ) === headingButton &&
				got.toolbar.includes( 'blockquote' ) === kept.includes( 'blockquote' );
			result(
				ok,
				`rich-text editor narrowed: ${ key } (${ where })`,
				got === null
					? 'no TinyMCE found'
					: `offers ${ got.formats.join( ', ' ) }; of h2–h6 and blockquote it keeps ${ got.kept.join( ', ' ) || 'none' }; toolbar ${ got.toolbar }`
			);
		}
		result( screen.errors.length === 0, `no console errors in the editors on ${ screen.where }`, `${ screen.errors.length } errors` );
		screen.errors.slice( 0, 10 ).forEach( info );
		await close( screen.page );
	}
	// ── 6. Topics, the service editor, the hidden post fields and the summary check ─────────
	{
		// The Topics box on a new service: labelled Topics, its "add new" form has no parent.
		const { page, errors } = await open( '/wp-admin/post-new.php?post_type=service' );
		await page.waitForFunction( () => window.acf && window.tinymce && document.readyState === 'complete' );
		const box = await page.evaluate( () => ( {
			title: document.querySelector( '#service_categorydiv .hndle, #service_categorydiv h2' )?.textContent.trim(),
			adder: !! document.querySelector( '#service_category-adder' ),
			parent: !! document.querySelector( '[name="newservice_category_parent"], label[for="newservice_category_parent"]' ),
			// ACF's hidden template editor (acf_content) has its own Add Media button; the service's is #wp-content-media-buttons.
			media: !! document.querySelector( '#wp-content-media-buttons' ),
			// dfw is the distraction-free toggle WordPress adds to the post editor, not a format.
			quicktags: ( window.tinyMCEPreInit?.qtInit?.content?.buttons ?? '' ).split( ',' ).filter( ( b ) => b && b !== 'dfw' ),
		} ) );
		result(
			box.title === 'Topics' && box.adder && ! box.parent,
			'the Topics box offers no parent',
			`box "${ box.title }"; add-new form ${ box.adder ? 'present' : 'missing' }; parent control ${ box.parent ? 'PRESENT' : 'none' }`
		);
		result(
			! box.media && JSON.stringify( box.quicktags ) === JSON.stringify( [ 'strong', 'em', 'link', 'ul', 'ol', 'li' ] ),
			'the service editor offers only what its intro shows',
			`Add Media ${ box.media ? 'PRESENT' : 'none' }; Text tab buttons ${ box.quicktags.join( ', ' ) }`
		);

		// The summary check (admin/summary-limit.js): 301 characters refused with the message, 300 not.
		const trySummary = async ( p, summary ) => {
			await p.evaluate( ( text ) => {
				document.querySelector( '#excerpt' ).value = text;
			}, summary );
			await p.waitForSelector( '#publish:not(.disabled)' );
			await p.click( '#publish' );
			await p.waitForTimeout( 1500 );
			return p.evaluate( () => document.querySelector( '#salt-summary-limit' )?.textContent.trim() ?? '' );
		};
		for ( const [ type, screen ] of [ [ 'service', page ], [ 'post', null ] ] ) {
			const p = screen ?? ( await open( `/wp-admin/post-new.php?post_type=${ type }` ) ).page;
			await p.waitForFunction( () => window.saltSummaryLimit && document.readyState === 'complete' );
			const title = `Harness: summary ${ type } ${ Date.now() }`;
			await p.fill( '#title', title );
			await p.locator( '#title' ).blur();
			await p.waitForSelector( '#edit-slug-box #sample-permalink', { timeout: 60000 } );
			const refused = await trySummary( p, 'é'.repeat( 301 ) );
			const notPublished = publishedWithTitle( title, type ) === 0;
			result(
				refused === 'The summary (the Excerpt box) is 301 characters long. Shorten it to 300 characters or fewer.' && notPublished,
				`a ${ type } summary of 301 characters is refused`,
				`301 two-byte characters: the editor says "${ refused }"; ${ notPublished ? 'not saved' : 'SAVED' }`
			);
			if ( type === 'service' ) {
				// A new service still needs its required benefit, so ACF then refuses it for that alone.
				const other = await trySummary( p, 'é'.repeat( 300 ) );
				result( other === '', 'a service summary of 300 characters is accepted', '300 two-byte characters: no summary message (ACF then asks for the required benefit)' );
			} else {
				await p.evaluate( () => {
					document.querySelector( '#excerpt' ).value = 'é'.repeat( 300 );
				} );
				await p.waitForSelector( '#publish:not(.disabled)' );
				// The first autosave already put post.php?post=N in the address; a save adds message=.
				await Promise.all( [ p.waitForURL( /message=\d+/, { timeout: 120000 } ), p.click( '#publish' ) ] );
				result( publishedWithTitle( title, type ) === 1, `a ${ type } summary of 300 characters is published`, '300 two-byte characters' );
				const hidden = await p.evaluate( () =>
					[ 'author', 'primary_topic', 'layout' ].filter( ( n ) => document.querySelector( `.acf-field[data-key="field_salt_collection_posts_${ n }"]` ) )
				);
				result( hidden.length === 0, 'the post fields unit 10 wires are not in the editor', hidden.length ? `drawn: ${ hidden.join( ', ' ) }` : 'author, primaryTopic and layout not drawn' );
			}
			if ( screen === null ) {
				await close( p );
			}
		}
		result( errors.length === 0, 'no console errors on the service screen', `${ errors.length } errors` );
		errors.slice( 0, 10 ).forEach( info );
		await close( page );

		// The term screens: no Parent field when adding or editing a topic.
		const terms = await open( '/wp-admin/edit-tags.php?taxonomy=service_category&post_type=service' );
		const addHasParent = await terms.page.locator( '.term-parent-wrap, #parent' ).count();
		const heading = ( await terms.page.locator( 'h1.wp-heading-inline, .wrap h1' ).first().textContent() ).trim();
		await terms.page.fill( '#tag-name', 'Harness topic' );
		await Promise.all( [
			terms.page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'action=add-tag' ) ),
			terms.page.click( '#submit' ),
		] );
		const editHref = await terms.page.locator( '#the-list a.row-title', { hasText: 'Harness topic' } ).first().getAttribute( 'href' );
		await close( terms.page );
		const edit = await open( editHref.replace( url, '' ) );
		const editHasParent = await edit.page.locator( '.term-parent-wrap, #parent' ).count();
		await close( edit.page );
		result(
			heading === 'Topics' && addHasParent === 0 && editHasParent === 0,
			'the topic screens offer no parent',
			`"${ heading }"; Parent on the add form ${ addHasParent }, on the edit form ${ editHasParent }`
		);
	}
	// ── 7. The url format on save (inc/fields/checks.php) ──────────────────────────────────────
	{
		const URL_MESSAGE =
			'Enter a full address that starts with http://, https://, mailto: or tel:. A path such as /contact or an anchor such as #pricing is not accepted; link to a page on this site with an internal link instead.';
		const isValidation = ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'acf%2Fvalidate_save_post' );
		// Set the value, press Publish, wait for ACF's validation and read the field's message.
		const tryUrl = async ( p, input, key, value ) => {
			await p.evaluate(
				( [ sel, v ] ) => {
					document.querySelectorAll( '.acf-notice.-error' ).forEach( ( n ) => n.remove() );
					document.querySelector( sel ).value = v;
				},
				[ input, value ]
			);
			await p.waitForSelector( '#publish:not(.disabled)' );
			await Promise.all( [ p.waitForResponse( isValidation, { timeout: 60000 } ), p.click( '#publish' ) ] );
			await p.waitForTimeout( 800 );
			return p
				.evaluate( ( k ) => document.querySelector( `.acf-field[data-key="${ k }"] .acf-notice.-error` )?.textContent.trim() ?? '', key )
				.catch( () => '' );
		};
		const ready = async ( p, title ) => {
			await p.waitForFunction( () => window.acf && document.readyState === 'complete' );
			await p.fill( '#title', title );
			await p.locator( '#title' ).blur();
			await p.waitForSelector( '#edit-slug-box #sample-permalink', { timeout: 60000 } );
		};
		const refused = [ '/contact', '#pricing', '//x.example', 'javascript:alert(1)', 'ftp://files.example' ];

		// A service's cta, made an external link. A new service never publishes here (it needs a benefit).
		const svc = await open( '/wp-admin/post-new.php?post_type=service' );
		await ready( svc.page, `Harness: url ${ Date.now() }` );
		const cta = 'field_salt_collection_services_cta';
		await svc.page.evaluate( ( k ) => {
			document.querySelector( `.acf-field[data-key="${ k }_type"] input[value="external"]` ).click();
			document.querySelector( `.acf-field[data-key="${ k }_label"] input` ).value = 'Ask us';
		}, cta );
		const ctaInput = `.acf-field[data-key="${ cta }_url"] input`;
		const ctaSaw = [];
		for ( const v of [ ...refused, 'https://hollinfarm.example/visit', 'mailto:hello@hollinfarm.example' ] ) {
			ctaSaw.push( [ v, await tryUrl( svc.page, ctaInput, `${ cta }_url`, v ) ] );
		}
		const ctaOk = ctaSaw.every( ( [ v, m ] ) => ( refused.includes( v ) ? m === URL_MESSAGE : m === '' ) );
		result(
			ctaOk,
			'an external link refuses a url that is not absolute http, https, mailto or tel',
			`service cta: ${ ctaSaw.map( ( [ v, m ] ) => `${ v } ${ m === URL_MESSAGE ? 'refused' : m === '' ? 'taken' : `"${ m }"` }` ).join( '; ' ) }`
		);
		result( svc.errors.length === 0, 'no console errors while checking urls on a service', `${ svc.errors.length } errors` );
		svc.errors.slice( 0, 10 ).forEach( info );
		await close( svc.page );

		// A case study's externalUrl: a path refused, https taken. A new case study never publishes
		// here (it needs a gallery image and a result), so ACF's other messages stay.
		const work = await open( '/wp-admin/post-new.php?post_type=work' );
		const workTitle = `Harness: url case study ${ Date.now() }`;
		await ready( work.page, workTitle );
		const ext = 'field_salt_collection_case_studies_external_url';
		const extInput = `.acf-field[data-key="${ ext }"] input`;
		const pathSaid = await tryUrl( work.page, extInput, ext, '/contact' );
		const httpsSaid = await tryUrl( work.page, extInput, ext, 'https://combefarm.example/orchard' );
		const others = await work.page.evaluate( () => document.querySelectorAll( '.acf-field .acf-notice.-error' ).length );
		result(
			pathSaid === URL_MESSAGE && httpsSaid === '' && others > 0 && publishedWithTitle( workTitle, 'work' ) === 0,
			'a case study\'s externalUrl refuses a path and takes https',
			`/contact: ${ pathSaid === URL_MESSAGE ? 'refused' : `"${ pathSaid }"` }; https://combefarm.example/orchard: ${ httpsSaid === '' ? 'taken' : `"${ httpsSaid }"` } (ACF still asks for ${ others } other required fields)`
		);
		result( work.errors.length === 0, 'no console errors while checking urls on a case study', `${ work.errors.length } errors` );
		work.errors.slice( 0, 10 ).forEach( info );
		await close( work.page );
	}
	// ── 8. The title limit (admin/title-limit.js) ──────────────────────────────────────────────
	{
		const message = ( n ) => `The title is ${ n } characters long. Shorten it to 120 characters or fewer.`;
		// A unique title of exactly n code points, the rest two-byte.
		const titleOf = ( type, n ) => Array.from( `${ type } ${ Date.now() } ${ 'é'.repeat( n ) }` ).slice( 0, n ).join( '' );
		const saidAfterPublish = async ( p ) => {
			await p.waitForSelector( '#publish:not(.disabled)' );
			await Promise.all( [ p.waitForURL( /message=\d+/, { timeout: 3000 } ).catch( () => null ), p.click( '#publish' ) ] );
			await p.waitForTimeout( 800 );
			return p.evaluate( () => document.querySelector( '#salt-title-limit' )?.textContent.trim() ?? '' ).catch( () => '' );
		};
		for ( const type of [ 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location' ] ) {
			const { page, errors } = await open( `/wp-admin/post-new.php?post_type=${ type }` );
			await page.waitForFunction( () => window.saltTitleLimit && document.readyState === 'complete' );
			const long = titleOf( type, 121 );
			// Leaving a new title starts WordPress's first autosave (Publish is disabled until it is
			// done). Only viewable types then draw a permalink, so wait for autosave.js's own event.
			await page.evaluate( () => {
				window.saltHarnessAutosaved = false;
				window.jQuery( document ).on( 'after-autosave', () => {
					window.saltHarnessAutosaved = true;
				} );
			} );
			await page.fill( '#title', long );
			await page.locator( '#title' ).blur();
			await page.waitForFunction( () => window.saltHarnessAutosaved, null, { timeout: 60000 } );
			const refused = await saidAfterPublish( page );
			const notPublished = publishedWithTitle( long, type ) === 0; // WordPress's autosave keeps a draft, as it does for the summary.
			const fits = titleOf( type, 120 );
			await page.fill( '#title', fits );
			const accepted = await saidAfterPublish( page );
			const published = publishedWithTitle( fits, type ) === 1;
			result(
				refused === message( 121 ) && notPublished && accepted === '' && ( type !== 'post' || published ),
				`a ${ type } title of 121 characters is refused and 120 is not`,
				`121 two-byte: "${ refused }", ${ notPublished ? 'not published' : 'PUBLISHED' }; 120 two-byte: ${ accepted === '' ? 'no title message' : `"${ accepted }"` }, ${ published ? 'published' : 'not published (other fields still required)' }`
			);
			result( errors.length === 0, `no console errors on the ${ type } title check`, `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
			await close( page );
		}
	}
	// ── 9. The topic name limit (admin/topic-name-limit.js) ────────────────────────────────────
	{
		const message = ( n ) => `The topic name is ${ n } characters long. Shorten it to 120 characters or fewer.`;
		const termCount = ( name, taxonomy ) =>
			Number( execFileSync( php, [ path.join( here, 'php/meta.php' ), '--term-name', name, taxonomy ], { encoding: 'utf8' } ) );
		// A unique name of exactly n code points, the rest two-byte.
		const nameOf = ( taxonomy, n ) => Array.from( `${ taxonomy } ${ Date.now() } ${ 'é'.repeat( n ) }` ).slice( 0, n ).join( '' );
		const said = ( p ) => p.evaluate( () => document.querySelector( '#salt-topic-name-limit' )?.textContent.trim() ?? '' );

		// The term add and edit screens.
		{
			const taxonomy = 'service_category';
			const { page, errors } = await open( `/wp-admin/edit-tags.php?taxonomy=${ taxonomy }&post_type=service` );
			await page.waitForFunction( () => window.saltTopicNameLimit && document.readyState === 'complete' );
			const long = nameOf( taxonomy, 121 );
			await page.fill( '#tag-name', long );
			await page.click( '#submit' );
			await page.waitForTimeout( 1500 );
			const addRefused = await said( page );
			const addNotSaved = termCount( long, taxonomy ) === 0;
			const fits = nameOf( taxonomy, 120 );
			await page.fill( '#tag-name', fits );
			await Promise.all( [
				page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'action=add-tag' ) ),
				page.click( '#submit' ),
			] );
			const addAccepted = await said( page );
			const addSaved = termCount( fits, taxonomy ) === 1;
			result(
				addRefused === message( 121 ) && addNotSaved && addAccepted === '' && addSaved,
				'a topic name of 121 characters is refused on the term add screen and 120 is not',
				`121 two-byte: "${ addRefused }", ${ addNotSaved ? 'not saved' : 'SAVED' }; 120 two-byte: ${ addAccepted === '' ? 'no message' : `"${ addAccepted }"` }, ${ addSaved ? 'saved' : 'NOT SAVED' }`
			);
			const editHref = await page.locator( '#the-list a.row-title', { hasText: fits.slice( 0, 30 ) } ).first().getAttribute( 'href' );
			result( errors.length === 0, 'no console errors on the term add screen', `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
			await close( page );

			const edit = await open( editHref.replace( url, '' ) );
			await edit.page.waitForFunction( () => window.saltTopicNameLimit && document.readyState === 'complete' );
			const longer = nameOf( taxonomy, 121 );
			await edit.page.fill( '#name', longer );
			await Promise.all( [ edit.page.waitForURL( /message=\d+/, { timeout: 3000 } ).catch( () => null ), edit.page.click( '#edittag [type="submit"]' ) ] );
			await edit.page.waitForTimeout( 800 );
			const editRefused = await said( edit.page );
			const editNotSaved = termCount( longer, taxonomy ) === 0 && termCount( fits, taxonomy ) === 1;
			const renamed = nameOf( taxonomy, 120 );
			await edit.page.fill( '#name', renamed );
			await Promise.all( [ edit.page.waitForURL( /message=\d+/, { timeout: 60000 } ), edit.page.click( '#edittag [type="submit"]' ) ] );
			const editAccepted = await said( edit.page );
			const editSaved = termCount( renamed, taxonomy ) === 1;
			result(
				editRefused === message( 121 ) && editNotSaved && editAccepted === '' && editSaved,
				'a topic name of 121 characters is refused on the term edit screen and 120 is not',
				`121 two-byte: "${ editRefused }", ${ editNotSaved ? 'not saved' : 'SAVED' }; 120 two-byte: ${ editAccepted === '' ? 'no message' : `"${ editAccepted }"` }, ${ editSaved ? 'saved' : 'NOT SAVED' }`
			);
			result( edit.errors.length === 0, 'no console errors on the term edit screen', `${ edit.errors.length } errors` );
			edit.errors.slice( 0, 10 ).forEach( info );
			await close( edit.page );
		}

		// The Topics box's "add new" form on each post type that has one.
		for ( const [ type, taxonomy ] of [ [ 'service', 'service_category' ], [ 'work', 'work_type' ], [ 'faq', 'faq_category' ], [ 'location', 'location_area' ] ] ) {
			const { page, errors } = await open( `/wp-admin/post-new.php?post_type=${ type }` );
			await page.waitForFunction( () => window.saltTopicNameLimit && document.readyState === 'complete' );
			await page.click( `#${ taxonomy }-add-toggle` );
			const field = `#new${ taxonomy }`;
			const tryAdd = async ( value, how ) => {
				await page.fill( field, value );
				if ( how === 'enter' ) {
					await page.press( field, 'Enter' );
				} else {
					await page.click( `#${ taxonomy }-add-submit` );
				}
				await page.waitForTimeout( 1500 );
				return said( page );
			};
			const long = nameOf( taxonomy, 121 );
			const clickRefused = await tryAdd( long, 'click' );
			const enterRefused = await tryAdd( long, 'enter' );
			// WordPress makes a topic of each comma-separated part; one over-long part refuses them all.
			const short = `${ taxonomy } short ${ Date.now() }`;
			const listRefused = await tryAdd( `${ short }, ${ long }`, 'click' );
			const notSaved = termCount( long, taxonomy ) === 0 && termCount( short, taxonomy ) === 0;
			const fits = nameOf( taxonomy, 120 );
			await page.fill( field, fits );
			await Promise.all( [
				page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( `action=add-${ taxonomy }` ) ),
				page.click( `#${ taxonomy }-add-submit` ),
			] );
			const accepted = await said( page );
			const saved = termCount( fits, taxonomy ) === 1;
			result(
				clickRefused === message( 121 ) && enterRefused === message( 121 ) && listRefused === message( 121 ) && notSaved && accepted === '' && saved,
				`a topic name of 121 characters is refused in the ${ type } Topics box and 120 is not`,
				`121 two-byte by the button: "${ clickRefused }", by Enter: "${ enterRefused }", in a list: "${ listRefused }", ${ notSaved ? 'none saved' : 'SAVED' }; 120 two-byte: ${ accepted === '' ? 'no message' : `"${ accepted }"` }, ${ saved ? 'saved' : 'NOT SAVED' }`
			);
			result( errors.length === 0, `no console errors on the ${ type } Topics box check`, `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
			await close( page );
		}
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
