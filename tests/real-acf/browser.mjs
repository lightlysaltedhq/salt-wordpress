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
 *    no Add Media button and Text tab buttons for its list alone; a summary
 *    shows "N / 300" as it is typed and the note past 300, and a service
 *    summary over 300 saves as a draft; a post with a title over 120 and a
 *    summary over 300 saves as a draft, publishes and updates, the counts
 *    showing each time; a post's editor is the section builder (layout) with
 *    the credited author, no classic editor and no Tags box, and its Topics
 *    box has no parent and holds the primary topic.
 * 7. The url format on save: a service's external cta link and a case
 *    study's externalUrl refuse /contact, #pricing, //x.example, javascript:
 *    and ftp:, and take https and mailto.
 * 8. The title's count: on each collection's edit screen "N / 120", the
 *    search note past 60 on services, case studies and posts only, the
 *    strong note past 120, and a title over 120 saves as a draft.
 * 9. The topic name's count: on the term add and edit screens of a topics
 *    taxonomy and of posts' categories, "N / 120" and the note past 120; a
 *    name over 120 is added and updated. No count in quick edit or in the
 *    Topics box's add-new form, which adds a name over 120 too.
 * 10. Site Settings → Case Studies: with the work module on, the form saves
 *    the band header and its tones (and offers no image aside), and the
 *    case-study view draws them, its featured image the band's last part
 *    and the page's one priority image; with it off, the page and its field group are gone
 *    and the stored settings are kept.
 *
 * With SALT_HARNESS_SCREENSHOTS set to a directory, it saves pictures of the
 * counts there.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { normalise } from '@lightlysaltedhq/salt-contract/normalise';

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

// The character count beside a field (admin/character-count.js): its text, whether it is past
// the target (is-over, red and bold), and the mark it is past.
const countOf = ( p, field ) =>
	p.evaluate( ( f ) => {
		const c = document.getElementById( `salt-character-count-${ f }` );
		return c ? { text: c.textContent.replace( /\s+/g, ' ' ).trim(), over: c.classList.contains( 'is-over' ), mark: c.dataset.mark } : null;
	}, field );
const counts = ( n, target, note = '' ) => `${ n } / ${ target }${ note ? ` ${ note }` : '' }`;
const OVER = ( target ) => `Over the suggested ${ target } characters`;
const SEARCH = 'Search results may shorten a title over about 60 characters';
// A unique text of exactly n code points, the rest two-byte.
const textOf = ( prefix, n ) => Array.from( `${ prefix } ${ Date.now() } ${ 'é'.repeat( n ) }` ).slice( 0, n ).join( '' );
// The post's status, title and excerpt, straight from the database.
const postRow = ( id ) => JSON.parse( execFileSync( php, [ path.join( here, 'php/meta.php' ), '--post', String( id ) ], { encoding: 'utf8' } ) );
// Screenshots of the counts, when SALT_HARNESS_SCREENSHOTS names a directory.
const shots = process.env.SALT_HARNESS_SCREENSHOTS ?? '';
const shot = async ( p, selector, name ) => {
	if ( shots ) {
		fs.mkdirSync( shots, { recursive: true } );
		await p.locator( selector ).first().screenshot( { path: path.join( shots, `${ name }.png` ) } );
	}
};
// Type a new post's title and leave it, then wait for WordPress's first autosave (Save Draft and
// Publish are disabled until it is done; only viewable types draw a permalink, so wait for
// autosave.js's own event).
const titleNewPost = async ( p, title ) => {
	await p.evaluate( () => {
		window.saltHarnessAutosaved = false;
		window.jQuery( document ).on( 'after-autosave', () => {
			window.saltHarnessAutosaved = true;
		} );
	} );
	await p.fill( '#title', title );
	await p.locator( '#title' ).blur();
	await p.waitForFunction( () => window.saltHarnessAutosaved, null, { timeout: 60000 } );
};
// Save the edit form by a button (#save-post or #publish) and wait for the reloaded screen, whose
// address carries a message= (WordPress then takes it out of the address, so the navigation is
// what is checked). Which message it is says little: when ACF has validated the form it submits it
// again without the button's name, and WordPress then says 4 whatever was saved. The caller reads
// the stored status from the database.
const saveBy = async ( p, button ) => {
	await p.waitForSelector( `${ button }:not(.disabled)` );
	const [ saved ] = await Promise.all( [
		p.waitForURL( /[?&]message=\d+(&|$)/, { timeout: 120000 } ).then( () => true, () => false ),
		p.click( button ),
	] );
	await p.waitForFunction( () => document.readyState === 'complete' );
	return saved;
};

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
	// ── 6. Topics, the service editor, the post editor and the summary check ─────────
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

		// The summary's count (admin/character-count.js): "N / 300" beside the Excerpt box as the
		// editor types, the note past 300, and never a refusal.
		{
			const title = textOf( 'Harness: summary service', 40 );
			await titleNewPost( page, title );
			const empty = await countOf( page, 'excerpt' );
			await page.fill( '#excerpt', 'é'.repeat( 250 ) );
			const under = await countOf( page, 'excerpt' );
			await shot( page, '#postexcerpt', 'summary-under-300' );
			await page.fill( '#excerpt', 'é'.repeat( 301 ) );
			const over = await countOf( page, 'excerpt' );
			await shot( page, '#postexcerpt', 'summary-over-300' );
			const id = await page.inputValue( '#post_ID' );
			const drafted = await saveBy( page, '#save-post' );
			const row = postRow( id );
			const after = await countOf( page, 'excerpt' );
			result(
				empty?.text === counts( 0, 300 ) && under?.text === counts( 250, 300 ) && ! under.over && over?.text === counts( 301, 300, OVER( 300 ) ) && over.over &&
					drafted && row.status === 'draft' && Array.from( row.excerpt ).length === 301 && after?.text === counts( 301, 300, OVER( 300 ) ),
				'a service summary over 300 characters is counted, not refused, and saves as a draft',
				`empty "${ empty?.text }"; 250 two-byte "${ under?.text }"; 301 two-byte "${ over?.text }"; Save Draft ${ drafted ? 'saved' : 'NOT SAVED' }, ${ row.status } with ${ Array.from( row.excerpt ?? '' ).length } characters; reloaded "${ after?.text }"`
			);
		}

		// On a post: an over-target title and summary save as a draft, publish and update.
		{
			const p = ( await open( '/wp-admin/post-new.php?post_type=post' ) ).page;
			await p.waitForFunction( () => window.saltCharacterCount && document.readyState === 'complete' );
			// WordPress hides a post's Excerpt box until it is ticked in Screen Options.
			if ( ! ( await p.isVisible( '#excerpt' ) ) ) {
				await p.evaluate( () => document.querySelector( '#postexcerpt-hide' ).click() );
				await p.waitForSelector( '#excerpt', { state: 'visible' } );
			}
			const id = await p.inputValue( '#post_ID' );
			const steps = [];
			const title1 = textOf( 'Harness: post', 125 );
			await titleNewPost( p, title1 );
			await p.fill( '#excerpt', 'é'.repeat( 310 ) );
			const shown = [ await countOf( p, 'title' ), await countOf( p, 'excerpt' ) ];
			steps.push( [ 'Save Draft', await saveBy( p, '#save-post' ), 'draft', title1, 310 ] );
			steps[ 0 ].push( postRow( id ), [ await countOf( p, 'title' ), await countOf( p, 'excerpt' ) ] );
			steps.push( [ 'Publish', await saveBy( p, '#publish' ), 'publish', title1, 310 ] );
			steps[ 1 ].push( postRow( id ), [ await countOf( p, 'title' ), await countOf( p, 'excerpt' ) ] );
			const title2 = textOf( 'Harness: post updated', 130 );
			await p.fill( '#title', title2 );
			await p.fill( '#excerpt', 'é'.repeat( 320 ) );
			steps.push( [ 'Update', await saveBy( p, '#publish' ), 'publish', title2, 320 ] );
			steps[ 2 ].push( postRow( id ), [ await countOf( p, 'title' ), await countOf( p, 'excerpt' ) ] );
			const okStep = ( [ , saved, status, title, excerpt, row, [ t, e ] ] ) =>
				saved && row.status === status && row.title === title && Array.from( row.excerpt ).length === excerpt &&
				t?.text === counts( Array.from( title ).length, 120, OVER( 120 ) ) && t.over && e?.text === counts( excerpt, 300, OVER( 300 ) ) && e.over;
			result(
				shown[ 0 ]?.text === counts( 125, 120, OVER( 120 ) ) && shown[ 1 ]?.text === counts( 310, 300, OVER( 300 ) ) && steps.every( okStep ),
				'a post with a title over 120 and a summary over 300 saves as a draft, publishes and updates, with the counts showing',
				`typed: "${ shown[ 0 ]?.text }", "${ shown[ 1 ]?.text }"; ${ steps
					.map( ( [ by, saved, , , , row, [ t, e ] ] ) => `${ by } ${ saved ? 'saved' : 'NOT SAVED' } (${ row.status }, title ${ Array.from( row.title ?? '' ).length }, summary ${ Array.from( row.excerpt ?? '' ).length }; counts "${ t?.text }", "${ e?.text }")` )
					.join( '; ' ) }`
			);
			const post = await p.evaluate( () => {
				const field = ( n ) => document.querySelector( `.acf-field[data-key="field_salt_collection_posts_${ n }"]` );
				const box = document.querySelector( '#categorydiv' );
				return {
					author: !! document.querySelector( '#post_author_override' ) && ! field( 'author' ),
					layout: !! field( 'layout' ),
					primary: document.querySelectorAll( '.acf-field[data-key="field_salt_collection_posts_primary_topic"]' ).length === 1 && !! box?.contains( field( 'primary_topic' ) ),
					title: box?.querySelector( '.hndle, h2' )?.textContent.trim(),
					parent: !! document.querySelector( '[name="newcategory_parent"], label[for="newcategory_parent"]' ),
					editor: !! document.querySelector( '#postdivrich' ),
					tags: !! document.querySelector( '#tagsdiv-post_tag' ),
				};
			} );
			result(
				post.author && post.layout && post.primary && post.title === 'Topics' && ! post.parent && ! post.editor && ! post.tags,
				'the post editor is the section builder, with the credited author and the primary topic in the Topics box',
				`author ${ post.author ? 'drawn' : 'MISSING' }; layout ${ post.layout ? 'drawn' : 'MISSING' }; primary topic ${ post.primary ? 'in the box' : 'NOT IN THE BOX' }; box "${ post.title }"; parent ${ post.parent ? 'PRESENT' : 'none' }; classic editor ${ post.editor ? 'PRESENT' : 'none' }; Tags box ${ post.tags ? 'PRESENT' : 'none' }`
			);
			await close( p );
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
	// ── 8. The title's count (admin/character-count.js) ───────────────────────────────────────
	// "N / 120" beside the title as the editor types; services, case studies and posts also say
	// search results may shorten it past 60; past 120 the strong note. A title over 120 saves as a
	// draft (a post publishes and updates with one in phase 6).
	{
		for ( const type of [ 'service', 'work', 'post', 'testimonial', 'faq', 'location' ] ) {
			const search = [ 'service', 'work', 'post' ].includes( type );
			const { page, errors } = await open( `/wp-admin/post-new.php?post_type=${ type }` );
			await page.waitForFunction( () => window.saltCharacterCount && document.readyState === 'complete' );
			const id = await page.inputValue( '#post_ID' );
			const seen = {};
			for ( const [ name, n ] of [ [ 'under-60', 40 ], [ 'between-60-and-120', 90 ], [ 'over-120', 125 ] ] ) {
				await page.fill( '#title', textOf( type, n ) );
				seen[ name ] = await countOf( page, 'title' );
				if ( type === 'service' || ( type === 'faq' && name !== 'between-60-and-120' ) ) {
					await shot( page, '#titlediv', `title-${ type }-${ name }` );
				}
			}
			const want = {
				'under-60': counts( 40, 120 ),
				'between-60-and-120': counts( 90, 120, search ? SEARCH : '' ),
				'over-120': counts( 125, 120, OVER( 120 ) ),
			};
			const shown = Object.keys( want ).every( ( k ) => seen[ k ]?.text === want[ k ] ) && ! seen[ 'under-60' ].over && ! seen[ 'between-60-and-120' ].over && seen[ 'over-120' ].over;
			const long = textOf( type, 125 );
			await titleNewPost( page, long );
			const drafted = await saveBy( page, '#save-post' );
			const row = postRow( id );
			const after = await countOf( page, 'title' );
			result(
				shown && drafted && row.status === 'draft' && row.title === long && after?.text === want[ 'over-120' ],
				`a ${ type } title is counted against 120${ search ? ' with the 60 note' : ', with no 60 note' }, and one over 120 saves as a draft`,
				`40: "${ seen[ 'under-60' ]?.text }"; 90: "${ seen[ 'between-60-and-120' ]?.text }"; 125: "${ seen[ 'over-120' ]?.text }"; Save Draft ${ drafted ? 'saved' : 'NOT SAVED' } (${ row.status }, ${ Array.from( row.title ?? '' ).length } characters); reloaded "${ after?.text }"`
			);
			result( errors.length === 0, `no console errors on the ${ type } title count`, `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
			await close( page );
		}
	}
	// ── 9. The topic name's count (admin/character-count.js) ──────────────────────────────────
	// "N / 120" on the term add and edit screens of a topics taxonomy and of posts' categories
	// (labelled Topics); a name over 120 is added and updated. No count in the Topics box's own
	// "add new" form, which adds an over-long name too, or in quick edit.
	{
		const termCount = ( name, taxonomy ) =>
			Number( execFileSync( php, [ path.join( here, 'php/meta.php' ), '--term-name', name, taxonomy ], { encoding: 'utf8' } ) );

		for ( const [ taxonomy, query ] of [ [ 'service_category', 'taxonomy=service_category&post_type=service' ], [ 'category', 'taxonomy=category' ] ] ) {
			const { page, errors } = await open( `/wp-admin/edit-tags.php?${ query }` );
			await page.waitForFunction( () => window.saltCharacterCount && document.readyState === 'complete' );
			const empty = await countOf( page, 'tag-name' );
			await page.fill( '#tag-name', textOf( taxonomy, 80 ) );
			const under = await countOf( page, 'tag-name' );
			await shot( page, '.form-field.term-name-wrap', `topic-${ taxonomy }-add-under-120` );
			const long = textOf( taxonomy, 125 );
			await page.fill( '#tag-name', long );
			const over = await countOf( page, 'tag-name' );
			await shot( page, '.form-field.term-name-wrap', `topic-${ taxonomy }-add-over-120` );
			await Promise.all( [
				page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( 'action=add-tag' ) ),
				page.click( '#submit' ),
			] );
			await page.waitForTimeout( 500 );
			const added = termCount( long, taxonomy ) === 1;
			const cleared = await countOf( page, 'tag-name' );
			// Quick edit on the new row: no count there.
			const row = page.locator( '#the-list tr', { hasText: long.slice( 0, 30 ) } ).first();
			await row.hover();
			await row.locator( 'button.editinline' ).click( { force: true } );
			await page.waitForSelector( '#the-list .inline-edit-row' );
			const quick = await page.locator( '#the-list .inline-edit-row .salt-character-count' ).count();
			const editHref = await page.locator( '#the-list a.row-title', { hasText: long.slice( 0, 30 ) } ).first().getAttribute( 'href' );
			result(
				empty?.text === counts( 0, 120 ) && under?.text === counts( 80, 120 ) && ! under.over && over?.text === counts( 125, 120, OVER( 120 ) ) && over.over && added && cleared?.text === counts( 0, 120 ) && quick === 0,
				`a ${ taxonomy } name is counted on the add screen and one over 120 is added`,
				`empty "${ empty?.text }"; 80 two-byte "${ under?.text }"; 125 two-byte "${ over?.text }"; ${ added ? 'added' : 'NOT ADDED' }; after adding "${ cleared?.text }"; counts in quick edit ${ quick }`
			);
			result( errors.length === 0, `no console errors on the ${ taxonomy } add screen`, `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
			await close( page );

			const edit = await open( editHref.replace( url, '' ) );
			await edit.page.waitForFunction( () => window.saltCharacterCount && document.readyState === 'complete' );
			const loaded = await countOf( edit.page, 'name' );
			const longer = textOf( `${ taxonomy } renamed`, 130 );
			await edit.page.fill( '#name', longer );
			const typed = await countOf( edit.page, 'name' );
			const updated = await Promise.all( [ edit.page.waitForURL( /message=\d+/, { timeout: 60000 } ).then( () => true ).catch( () => false ), edit.page.click( '#edittag [type="submit"]' ) ] );
			const saved = termCount( longer, taxonomy ) === 1;
			result(
				loaded?.text === counts( 125, 120, OVER( 120 ) ) && loaded.over && typed?.text === counts( 130, 120, OVER( 120 ) ) && updated[ 0 ] && saved,
				`a ${ taxonomy } name is counted on the edit screen and one over 120 is updated`,
				`loaded "${ loaded?.text }"; 130 two-byte "${ typed?.text }"; Update ${ updated[ 0 ] ? 'returned' : 'DID NOT RETURN' }, ${ saved ? 'saved' : 'NOT SAVED' }`
			);
			result( edit.errors.length === 0, `no console errors on the ${ taxonomy } edit screen`, `${ edit.errors.length } errors` );
			edit.errors.slice( 0, 10 ).forEach( info );
			await close( edit.page );
		}

		// The Topics box's "add new" form: no count, and an over-long name is added.
		for ( const [ type, taxonomy ] of [ [ 'service', 'service_category' ], [ 'post', 'category' ] ] ) {
			const { page, errors } = await open( `/wp-admin/post-new.php?post_type=${ type }` );
			await page.waitForFunction( () => window.saltCharacterCount && document.readyState === 'complete' );
			await page.click( `#${ taxonomy }-add-toggle` );
			const long = textOf( `${ taxonomy } box`, 125 );
			await page.fill( `#new${ taxonomy }`, long );
			const inBox = await page.locator( `#${ taxonomy }div .salt-character-count` ).count();
			await Promise.all( [
				page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() ?? '' ).includes( `action=add-${ taxonomy }` ) ),
				page.click( `#${ taxonomy }-add-submit` ),
			] );
			const added = termCount( long, taxonomy ) === 1;
			result(
				inBox === 0 && added,
				`the ${ type } Topics box's add-new form has no count and adds a name over 120`,
				`counts in the box ${ inBox }; ${ added ? 'added' : 'NOT ADDED' }`
			);
			result( errors.length === 0, `no console errors in the ${ type } Topics box`, `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
			await close( page );
		}
	}
	// ── 10. Site Settings → Case Studies saves, and the case-study view reads it ──────────────
	{
		const script = path.join( here, 'php/case-study-settings.php' );
		const caseStudy = ( work ) => {
			if ( work !== undefined ) {
				execFileSync( php, [ script, '--work', work ] );
			}
			return JSON.parse( execFileSync( php, [ script ], { encoding: 'utf8' } ) );
		};
		const pageUrl = '/wp-admin/admin.php?page=salt-settings-case-studies';
		const field = ( name ) => `.acf-field[data-name="${ name }"]`;

		// With the work module on: set every setting through the form, then read it back.
		const on = caseStudy( '1' );
		const { page, errors } = await open( pageUrl );
		await page.waitForFunction( () => window.acf && document.readyState === 'complete' );
		const offered = await page.locator( field( 'case_study_header' ) ).count();
		await page.click( `${ field( 'case_study_header' ) } label:has(input[value="band"])` );
		await page.selectOption( `${ field( 'case_study_header_tone' ) } select`, 'brand-tint' );
		await page.selectOption( `${ field( 'case_study_header_tone_dark' ) } select`, 'surface-inverse' );
		const mediaControl = await page.locator( field( 'case_study_media' ) ).count();
		await Promise.all( [ page.waitForURL( /[?&]message=1/, { timeout: 60000 } ), page.click( '#publish' ) ] );
		const saved = caseStudy();
		const want = { header: 'band', headerTone: 'brand-tint', headerToneDark: 'surface-inverse' };
		result(
			on.work && on.group && offered === 1 && mediaControl === 0 && JSON.stringify( saved.settings ) === JSON.stringify( want ),
			'Site Settings → Case Studies saves the header layout, with no image aside control (work on)',
			`page ${ offered ? 'offered' : 'MISSING' }; image aside control ${ mediaControl ? 'STILL OFFERED' : 'gone' }; salt_case_study_settings() reads ${ JSON.stringify( saved.settings ) }`
		);
		result( errors.length === 0, 'no console errors on the Case Studies settings page', `${ errors.length } errors` );
		errors.slice( 0, 10 ).forEach( info );
		await close( page );

		// The case-study view draws what was saved: the band in its tones, the featured image the story's first part (SC-034, SC-036).
		const { page: view } = await open( saved.url.replace( url, '' ) );
		const drawn = await view.evaluate( () => {
			const article = document.querySelector( 'article.salt-case-study-view' );
			const header = article?.querySelector( '.salt-case-study-view__header' );
			return {
				header: article?.dataset.header ?? null,
				tone: header?.dataset.tone ?? null,
				toneDark: header?.dataset.toneDark ?? null,
				heroFirst: article?.querySelector( ':scope > .salt-case-study-view__story > :first-child' )?.matches( '.salt-case-study-view__media' ) ?? false,
				priority: [ ...document.querySelectorAll( 'img[fetchpriority="high"]' ) ].map( ( img ) => img.closest( '.salt-case-study-view__media' ) !== null ),
				inlineStyle: article?.hasAttribute( 'style' ) ?? false,
			};
		} );
		result(
			drawn.header === 'band' && drawn.tone === 'brand-tint' && drawn.toneDark === 'surface-inverse' && drawn.heroFirst && JSON.stringify( drawn.priority ) === '[true]' && ! drawn.inlineStyle,
			'the case-study view reads the saved settings',
			`${ saved.url.replace( url, '' ) }: ${ JSON.stringify( drawn ) }`
		);
		await close( view );

		// With the work module off: no page, no group, the stored settings kept for when it is on again.
		const off = caseStudy( '0' );
		const { page: gone } = await open( '/wp-admin/' );
		const linked = await gone.locator( 'a[href*="page=salt-settings-case-studies"]' ).count();
		const direct = await gone.goto( `${ url }${ pageUrl }` );
		const blocked = direct.status() >= 400 && ( await gone.locator( field( 'case_study_header' ) ).count() ) === 0;
		await close( gone );
		const back = caseStudy( '1' );
		result(
			! off.work && ! off.group && linked === 0 && blocked && JSON.stringify( back.settings ) === JSON.stringify( want ),
			'with the work module off there is no Case Studies page, and its settings are kept',
			`group ${ off.group ? 'REGISTERED' : 'not registered' }; menu links ${ linked }; the page answered ${ direct.status() }; on again it reads ${ JSON.stringify( back.settings ) }`
		);
	}

	// ── 11. The client and sector records (SC-035): the switches, the editor and the client slot ─
	{
		const script = path.join( here, 'php/records.php' );
		const state = ( ...ids ) => JSON.parse( execFileSync( php, [ script, ...ids.map( String ) ], { encoding: 'utf8' } ) );
		const field = ( name ) => `.acf-field[data-name="${ name }"]`;
		const advanced = '/wp-admin/admin.php?page=salt-settings-advanced';

		// The switches, through Site Settings → Advanced → Modules → Content modules.
		const switches = async ( client, sector ) => {
			const { page, errors } = await open( advanced );
			await page.waitForFunction( () => window.acf && document.readyState === 'complete' );
			await page.click( 'a.acf-tab-button:has-text("Content modules")' );
			const before = {};
			for ( const [ slug, want ] of [ [ 'client', client ], [ 'sector', sector ] ] ) {
				const box = page.locator( `${ field( `module_${ slug }` ) } input[type="checkbox"]` );
				before[ slug ] = await box.isChecked();
				if ( before[ slug ] !== want ) {
					await page.locator( `${ field( `module_${ slug }` ) } .acf-switch` ).click();
				}
			}
			before.work = await page.locator( `${ field( 'module_work' ) } input[type="checkbox"]` ).isChecked();
			await Promise.all( [ page.waitForURL( /[?&]message=1/, { timeout: 60000 } ), page.click( '#publish' ) ] );
			await close( page );
			return { before, errors };
		};
		// A case study's editor: which controls it offers and what they hold.
		const editor = async ( page ) =>
			page.evaluate( () => {
				const read = ( name ) => {
					const el = document.querySelector( `.acf-field[data-name="${ name }"]` );
					if ( ! el ) {
						return null;
					}
					const select = el.querySelector( 'select' );
					const status = el.querySelector( '.salt-record-unavailable' )?.textContent.trim();
					return {
						...( status ? { status } : {} ),
						shown: ! el.classList.contains( 'acf-hidden' ) && el.offsetParent !== null,
						// An empty picker reads '', not its placeholder.
						value: select ? ( select.value ? select.selectedOptions[ 0 ].textContent.trim() : '' ) : el.querySelector( 'input' )?.value ?? '',
					};
				};
				return { client: read( 'client' ), clientRecord: read( 'clientRecord' ), sector: read( 'sector' ), sectorRecord: read( 'sectorRecord' ) };
			} );
		const edit = async ( id ) => {
			const opened = await open( `/wp-admin/post.php?post=${ id }&action=edit` );
			await opened.page.waitForFunction( () => window.acf && document.readyState === 'complete' );
			await opened.page.waitForTimeout( 500 );
			return opened;
		};
		const drawnClient = async ( target ) => {
			const { page } = await open( target.replace( url, '' ) );
			const text = await page.locator( '.salt-case-study-view__client .salt-case-study-view__detail-value' ).first().textContent().catch( () => null );
			await close( page );
			return text?.trim() ?? null;
		};
		const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );
		const stored = ( w ) => ( { client: w.client, clientRecord: w.clientRecord, sector: w.sector, sectorRecord: w.sectorRecord } );

		// Defaults: both switches off on the screen and at run time; the other modules stay on.
		const first = await switches( false, false );
		const fresh = state();
		result(
			! first.before.client && ! first.before.sector && first.before.work && ! fresh.modules.client && ! fresh.modules.sector && ! fresh.types.client && ! fresh.types.sector,
			'the Clients and Sectors switches are off by default, on the screen and at run time, and the other modules stay on',
			`screen ${ JSON.stringify( first.before ) }; run time ${ JSON.stringify( fresh.modules ) }; post types ${ JSON.stringify( fresh.types ) }`
		);
		result( first.errors.length === 0, 'no console errors on Site Settings → Advanced', `${ first.errors.length } errors` );

		const ids = JSON.parse( execFileSync( php, [ script, '--setup' ], { encoding: 'utf8' } ) );
		const seeded = state( ids.conflict, ids.recordOnly, ids.legacy, ids.unresolved );
		const conflictWas = stored( seeded.works[ ids.conflict ] );

		// All four switch combinations on the conflicting case study: what the editor offers, that a save rewrites nothing, and the client slot.
		for ( const [ client, sector ] of [ [ false, false ], [ true, false ], [ false, true ], [ true, true ] ] ) {
			const mode = `clients ${ client ? 'on' : 'off' }, sectors ${ sector ? 'on' : 'off' }`;
			await switches( client, sector );
			const { page, errors } = await edit( ids.conflict );
			const seen = await editor( page );
			const saved = await saveBy( page, '#publish' );
			await close( page );
			const after = state( ids.conflict );
			const work = after.works[ ids.conflict ];
			const drawn = await drawnClient( work.url );
			const expect = {
				client: client ? { shown: false, value: 'Ashby PC' } : { shown: true, value: 'Ashby PC' },
				clientRecord: client ? { shown: true, value: 'Ashby Parish Council' } : null,
				sector: sector ? { shown: false, value: 'Councils' } : { shown: true, value: 'Councils' },
				sectorRecord: sector ? { shown: true, value: 'Local government' } : null,
			};
			result(
				same( seen, expect ) && saved && same( stored( work ), conflictWas ) && drawn === ( client ? 'Ashby Parish Council' : 'Ashby PC' ) && same( after.types, { client, sector } ),
				`${ mode }: the editor offers the picker only when on, keeps the text until a record is chosen, a save rewrites nothing, and the client slot draws the ${ client ? 'record' : 'text' }`,
				`editor ${ JSON.stringify( seen ) }; saved ${ saved }; stored ${ JSON.stringify( stored( work ) ) }; drawn "${ drawn }"; post types ${ JSON.stringify( after.types ) }`
			);
			result( errors.length === 0, `no console errors in the case-study editor (${ mode })`, `${ errors.length } errors` );
			errors.slice( 0, 10 ).forEach( info );
		}

		// Clients and sectors have no public address.
		{
			const { page } = await open( `/?post_type=client&p=${ ids.clientA }` );
			const status = ( await page.goto( `${ url }/?post_type=client&p=${ ids.clientA }` ) ).status();
			const named = await page.locator( 'text=Ashby Parish Council' ).count();
			await close( page );
			result( status === 404 && named === 0, 'a client record has no public page', `/?post_type=client&p=${ ids.clientA } answered ${ status }` );
		}

		// A round trip with differing names and IDs, starting from a case study with records and no text.
		await switches( false, false );
		let opened = await edit( ids.recordOnly );
		const initialised = await editor( opened.page );
		await opened.page.fill( `${ field( 'client' ) } input`, 'Ashby Council (edited)' );
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const step1 = stored( state( ids.recordOnly ).works[ ids.recordOnly ] );
		result(
			initialised.client?.value === 'Ashby Parish Council' && initialised.sector?.value === 'Local government' &&
				same( step1, { client: 'Ashby Council (edited)', clientRecord: String( ids.clientA ), sector: 'Local government', sectorRecord: String( ids.sectorA ) } ),
			'off, with no text ever saved, the text opens with the stored record\'s name; an edit saves and the IDs are kept',
			`opened with ${ JSON.stringify( { client: initialised.client?.value, sector: initialised.sector?.value } ) }; stored ${ JSON.stringify( step1 ) }`
		);

		await switches( true, true );
		opened = await edit( ids.recordOnly );
		const chosen = await editor( opened.page );
		// Clearing the record shows the kept text; then search the picker for the other client.
		await opened.page.locator( `${ field( 'clientRecord' ) } .select2-selection__clear` ).click();
		await opened.page.waitForTimeout( 300 );
		const cleared = await editor( opened.page );
		// Clearing opens the dropdown; close it, then open it as an editor would.
		await opened.page.keyboard.press( 'Escape' );
		await opened.page.locator( `${ field( 'clientRecord' ) } .select2-selection` ).click();
		// Typed, as select2 searches on keystrokes; then wait for the search's answer.
		await opened.page.locator( '.select2-search__field' ).pressSequentially( 'Estates', { delay: 50 } );
		const option = opened.page.locator( '.select2-results__option', { hasText: 'Ashby Estates Trust' } );
		await option.waitFor( { timeout: 30000 } );
		await opened.page
			.waitForFunction( () => [ ...document.querySelectorAll( '.select2-results__option' ) ].every( ( o ) => o.textContent.includes( 'Estates' ) ), null, { timeout: 30000 } )
			.catch( () => {} );
		const offered = await opened.page.locator( '.select2-results__option' ).allTextContents();
		await option.click();
		await opened.page.waitForTimeout( 300 );
		const picked = await editor( opened.page );
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const step2 = state( ids.recordOnly ).works[ ids.recordOnly ];
		const drawn2 = await drawnClient( step2.url );
		result(
			chosen.clientRecord?.value === 'Ashby Parish Council' && chosen.client?.shown === false &&
				cleared.client?.shown === true && cleared.client?.value === 'Ashby Council (edited)' &&
				same( offered.map( ( t ) => t.trim() ), [ 'Ashby Estates Trust' ] ) && picked.clientRecord?.value === 'Ashby Estates Trust' && picked.client?.shown === false &&
				same( stored( step2 ), { client: 'Ashby Council (edited)', clientRecord: String( ids.clientB ), sector: 'Local government', sectorRecord: String( ids.sectorA ) } ) &&
				drawn2 === 'Ashby Estates Trust',
			'on, the picker is searchable; clearing it shows the kept text, choosing another record keeps that text, and the slot draws the new record',
			`chosen ${ JSON.stringify( chosen.clientRecord ) }; cleared shows ${ JSON.stringify( cleared.client ) }; search "Estates" offered ${ JSON.stringify( offered ) }; stored ${ JSON.stringify( stored( step2 ) ) }; drawn "${ drawn2 }"`
		);

		await switches( false, false );
		opened = await edit( ids.recordOnly );
		const back = await editor( opened.page );
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const step3 = state( ids.recordOnly ).works[ ids.recordOnly ];
		result(
			back.client?.value === 'Ashby Council (edited)' && same( stored( step3 ), stored( step2 ) ) && ( await drawnClient( step3.url ) ) === 'Ashby Council (edited)',
			'off again, the edited text is not overwritten by the record\'s name, the record ID is kept, and the slot draws the text',
			`opened with "${ back.client?.value }"; stored ${ JSON.stringify( stored( step3 ) ) }`
		);

		// A legacy case study (text only) under both switches, and an ID that is no post.
		await switches( true, true );
		const before = state();
		opened = await edit( ids.legacy );
		const legacy = await editor( opened.page );
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		opened = await edit( ids.unresolved );
		const unresolved = await editor( opened.page );
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const end = state( ids.legacy, ids.unresolved );
		const legacyAfter = end.works[ ids.legacy ];
		const unresolvedAfter = end.works[ ids.unresolved ];
		result(
			legacy.client?.shown && legacy.client?.value === 'Legacy Gardens Ltd' && legacy.clientRecord?.value === '' &&
				legacyAfter.client === 'Legacy Gardens Ltd' && [ '', '0' ].includes( String( legacyAfter.clientRecord ) ) &&
				( await drawnClient( legacyAfter.url ) ) === 'Legacy Gardens Ltd' && same( end.records, before.records ),
			'on, a legacy case study keeps its text name, gets no record, and no record is created',
			`editor ${ JSON.stringify( legacy ) }; stored ${ JSON.stringify( stored( legacyAfter ) ) }; records ${ JSON.stringify( before.records ) } -> ${ JSON.stringify( end.records ) }`
		);
		result(
			unresolved.client?.shown && unresolved.client?.value === 'Old Co' && unresolved.clientRecord?.value === '' && /no longer exists/.test( unresolved.clientRecord?.status ?? '' ) &&
				same( stored( unresolvedAfter ), stored( seeded.works[ ids.unresolved ] ) ) && ( await drawnClient( unresolvedAfter.url ) ) === 'Old Co',
			'on, an ID that is no post is named as unavailable, offered to no one, kept through an ordinary save, and the slot draws the text',
			`editor ${ JSON.stringify( unresolved ) }; stored ${ JSON.stringify( stored( unresolvedAfter ) ) }`
		);

		// Removing it is explicit, and removes only the one ticked.
		opened = await edit( ids.unresolved );
		await opened.page.locator( `${ field( 'clientRecord' ) } input[name="salt_remove_record[client]"]` ).check();
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const removed = stored( state( ids.unresolved ).works[ ids.unresolved ] );
		result(
			[ '', '0' ].includes( String( removed.clientRecord ) ) && removed.sectorRecord === '999998' && removed.client === 'Old Co',
			'on, ticking "Remove the saved choice" removes that record only, and keeps the text',
			`stored ${ JSON.stringify( removed ) }`
		);

		// A record in the bin: kept through an ordinary save and the switches, drawn again once restored.
		const bin = ( id ) => execFileSync( php, [ script, '--trash', String( id ) ] );
		const restore = ( id ) => execFileSync( php, [ script, '--restore', String( id ) ] );
		bin( ids.clientA );
		opened = await edit( ids.conflict );
		const binned = await editor( opened.page );
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const binnedAfter = state( ids.conflict ).works[ ids.conflict ];
		const binnedDrawn = await drawnClient( binnedAfter.url );
		await switches( false, true );
		await switches( true, true );
		const afterSwitches = stored( state( ids.conflict ).works[ ids.conflict ] );
		restore( ids.clientA );
		opened = await edit( ids.conflict );
		const restored = await editor( opened.page );
		await close( opened.page );
		const restoredDrawn = await drawnClient( binnedAfter.url );
		result(
			binned.client?.shown && binned.clientRecord?.value === '' && /“Ashby Parish Council”, is in the bin/.test( binned.clientRecord?.status ?? '' ) &&
				same( stored( binnedAfter ), conflictWas ) && binnedDrawn === 'Ashby PC' && same( afterSwitches, conflictWas ) &&
				restored.clientRecord?.value === 'Ashby Parish Council' && ! restored.clientRecord?.status && restored.client?.shown === false && restoredDrawn === 'Ashby Parish Council',
			'on, a record in the bin is named as such, kept through an ordinary save and both switches, the slot draws the text, and once restored it is chosen and drawn again',
			`in the bin: editor ${ JSON.stringify( binned.clientRecord ) }, stored ${ JSON.stringify( stored( binnedAfter ) ) }, drawn "${ binnedDrawn }"; after the switches ${ JSON.stringify( afterSwitches ) }; restored: editor ${ JSON.stringify( restored.clientRecord ) }, drawn "${ restoredDrawn }"`
		);

		// Choosing another record replaces one in the bin.
		bin( ids.clientA );
		opened = await edit( ids.conflict );
		await opened.page.locator( `${ field( 'clientRecord' ) } .select2-selection` ).click();
		await opened.page.locator( '.select2-search__field' ).pressSequentially( 'Estates', { delay: 50 } );
		await opened.page.locator( '.select2-results__option', { hasText: 'Ashby Estates Trust' } ).click();
		await saveBy( opened.page, '#publish' );
		await close( opened.page );
		const replaced = stored( state( ids.conflict ).works[ ids.conflict ] );
		restore( ids.clientA );
		result(
			replaced.clientRecord === String( ids.clientB ) && replaced.client === 'Ashby PC',
			'on, choosing another record replaces one in the bin',
			`stored ${ JSON.stringify( replaced ) }`
		);
		await switches( false, false );
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
	// TinyMCE (with wpautop) saves paragraphs as blank lines and a br as a newline; ACF's
	// acf_the_content puts them back. A change is explained when both versions render the same
	// HTML, compared as the contract compares it (`<br />\nTwo` is `<br>Two`).
	if ( wysiwyg.length ) {
		const rendered = JSON.parse(
			execFileSync( php, [ path.join( here, 'php/meta.php' ), '--the-content' ], {
				input: JSON.stringify( wysiwyg.flatMap( ( [ a, b ] ) => [ a, b ] ) ),
				encoding: 'utf8',
			} )
		);
		const tidy = ( h ) => normalise( `<div>${ h }</div>` );
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
