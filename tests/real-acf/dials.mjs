// The design dials in Chromium (HARNESS ONLY, run by run.sh).
//
//  - An administrator sees the four dials on Site Settings → Brand & Design,
//    offered as choices, and saves one through the form.
//  - The page renders as before with every dial unset: no dial token is
//    written and the computed corners, shadow and section space are the
//    theme's own.
//  - Each non-default choice changes only its own tokens, in the page's
//    computed style, and the brand <style> keeps the request's CSP nonce, so
//    the browser refuses nothing.
//  - An Editor can neither open the settings page nor have a posted dial
//    saved through ACF's own save.
//
// With SALT_HARNESS_SCREENSHOTS set to a directory, it saves the page in each
// state there (dials-<state>.png) and the admin's settings screen.
/* global document, window, getComputedStyle */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const php = process.env.SALT_HARNESS_PHP_BIN;
const base = process.env.SALT_HARNESS_URL;
const site = ( ...args ) => execFileSync( php, [ new URL( './php/dials.php', import.meta.url ).pathname, ...args ], { encoding: 'utf8' } ).trim();
const shots = process.env.SALT_HARNESS_SCREENSHOTS ?? '';
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};

const TOKENS = [ '--radius-sm', '--radius-md', '--radius-lg', '--salt-card-shadow', '--salt-button-radius', '--space-section-sm', '--space-section-md', '--space-section-lg' ];
const OWN = {
	corners: [ '--radius-sm', '--radius-md', '--radius-lg' ],
	shadows: [ '--salt-card-shadow' ],
	'button-style': [ '--salt-button-radius' ],
	density: [ '--space-section-sm', '--space-section-md', '--space-section-lg' ],
};
// The corners dial moves --radius-md, which a button reads while the button style is unset.
const FOLLOWS = { corners: [ 'button' ] };

const { url } = JSON.parse( site( 'seed' ) );
const browser = await chromium.launch();
try {
	// ── The administrator: the four dials, as choices, saved through the form ─────────────────
	const admin = await browser.newContext( { viewport: { width: 1400, height: 1000 } } );
	const a = await admin.newPage();
	await a.goto( `${ base }/wp-login.php` );
	await a.fill( '#user_login', 'admin' );
	await a.fill( '#user_pass', process.env.SALT_HARNESS_ADMIN_PASS );
	await Promise.all( [ a.waitForURL( /wp-admin/ ), a.click( '#wp-submit' ) ] );
	const errors = [];
	a.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await a.goto( `${ base }/wp-admin/admin.php?page=salt-settings-brand` );
	await a.waitForFunction( () => window.acf && document.readyState === 'complete' );
	const offered = await a.evaluate( () =>
		Object.fromEntries(
			[ 'dial_corners', 'dial_shadows', 'dial_button_style', 'dial_density' ].map( ( name ) => {
				const select = document.querySelector( `.acf-field[data-name="${ name }"] select` );
				return [ name, select ? [ ...select.options ].map( ( o ) => `${ o.value }=${ o.text }` ) : null ];
			} )
		)
	);
	const want = {
		dial_corners: [ 'square=Square', 'soft=Soft', 'round=Round' ],
		dial_shadows: [ 'none=None', 'subtle=Subtle', 'raised=Raised' ],
		dial_button_style: [ 'square=Square', 'soft=Soft', 'pill=Pill' ],
		dial_density: [ 'compact=Compact', 'comfortable=Comfortable', 'spacious=Spacious' ],
	};
	result( JSON.stringify( offered ) === JSON.stringify( want ), 'an administrator sees the four dials on Brand & Design, offered as choices', JSON.stringify( offered ) );
	await a.click( '.acf-tab-button:has-text("Appearance")' ).catch( () => {} );
	if ( shots ) {
		fs.mkdirSync( shots, { recursive: true } );
		await a.screenshot( { path: path.join( shots, 'dials-admin-settings.png' ), fullPage: true } );
	}
	await a.selectOption( '.acf-field[data-name="dial_shadows"] select', 'raised' );
	await Promise.all( [ a.waitForURL( /[?&]message=1/, { timeout: 60000 } ), a.click( '#publish' ) ] );
	const saved = JSON.parse( site( 'state' ) );
	result( saved.shadows === 'raised' && saved.corners === 'soft', 'the form saves a dial as its choice', JSON.stringify( saved ) );
	result( errors.length === 0, 'no page errors on Brand & Design', errors.join( ' | ' ) );
	await admin.close();
	site( 'set', 'shadows', '' );

	// ── The page in each state ───────────────────────────────────────────────────────────────
	const read = async ( state, width = 1280 ) => {
		const context = await browser.newContext( { viewport: { width, height: 900 } } );
		await context.addInitScript( () => {
			window.saltHarnessRefusals = [];
			document.addEventListener( 'securitypolicyviolation', ( e ) => window.saltHarnessRefusals.push( `${ e.effectiveDirective } ${ e.blockedURI }` ) );
		} );
		const page = await context.newPage();
		const response = await page.goto( url, { waitUntil: 'networkidle' } );
		const csp = response.headers()[ 'content-security-policy' ] || '';
		const seen = await page.evaluate( ( tokens ) => {
			const root = getComputedStyle( document.documentElement );
			const style = document.getElementById( 'salt-brand-vars' );
			const card = document.querySelector( '.salt-card' );
			const button = document.querySelector( '.salt-button' );
			const sections = [ ...document.querySelectorAll( '.salt-section[data-spacing]' ) ];
			return {
				tokens: Object.fromEntries( tokens.map( ( t ) => [ t, root.getPropertyValue( t ).trim() ] ) ),
				style: style ? { nonce: style.nonce !== '', css: style.textContent } : null,
				card: card ? { radius: getComputedStyle( card ).borderTopLeftRadius, shadow: getComputedStyle( card ).boxShadow } : null,
				button: button ? getComputedStyle( button ).borderTopLeftRadius : null,
				space: Object.fromEntries( sections.map( ( s ) => [ s.dataset.spacing, getComputedStyle( s ).paddingTop ] ) ),
				refusals: window.saltHarnessRefusals,
			};
		}, TOKENS );
		if ( shots ) {
			await page.screenshot( { path: path.join( shots, `dials-${ state }${ width === 1280 ? '' : `-${ width }` }.png` ), fullPage: true } );
		}
		await context.close();
		return { ...seen, cspNonce: /style-src[^;]*'nonce-/.test( csp ) || /'nonce-/.test( csp ) };
	};

	const unset = await read( 'unset' );
	result(
		unset.style === null || ! /radius|shadow|space-section/.test( unset.style.css ),
		'with every dial unset no dial token is written',
		unset.style ? unset.style.css.slice( 0, 120 ) : 'no brand <style> printed'
	);
	result(
		unset.tokens[ '--salt-card-shadow' ] === '' && unset.tokens[ '--salt-button-radius' ] === '' && unset.card?.shadow === 'none' && unset.card?.radius === '8px' && unset.button === '6px',
		'unset, the page renders with the theme\'s own corners, no card shadow and the control corner on buttons',
		JSON.stringify( { card: unset.card, button: unset.button, space: unset.space, tokens: unset.tokens } )
	);

	const choices = { corners: 'square', shadows: 'raised', 'button-style': 'pill', density: 'spacious' };
	for ( const [ id, value ] of Object.entries( choices ) ) {
		site( 'set', id, value );
		const on = await read( `${ id }-${ value }` );
		site( 'set', id, '' );
		const changed = TOKENS.filter( ( t ) => on.tokens[ t ] !== unset.tokens[ t ] );
		result(
			JSON.stringify( changed.filter( ( t ) => ! OWN[ id ].includes( t ) ) ) === '[]' && changed.length > 0,
			`${ id } ${ value } changes only its own tokens`,
			`changed ${ changed.join( ', ' ) }`
		);
		const drawn = {
			corners: on.card?.radius === '0px' && on.button === '0px',
			shadows: on.card?.shadow !== 'none' && /rgba\(0, 0, 0, 0\.1\)/.test( on.card?.shadow ?? '' ),
			'button-style': parseFloat( on.button ) > 1000 && on.card?.radius === unset.card?.radius,
			density: JSON.stringify( on.space ) !== JSON.stringify( unset.space ) && on.space.lg === unset.space.lg,
		}[ id ];
		const others = {
			corners: on.card?.shadow === 'none' && JSON.stringify( on.space ) === JSON.stringify( unset.space ),
			shadows: on.card?.radius === unset.card?.radius && on.button === unset.button && JSON.stringify( on.space ) === JSON.stringify( unset.space ),
			'button-style': on.card?.shadow === 'none' && JSON.stringify( on.space ) === JSON.stringify( unset.space ),
			density: on.card?.radius === unset.card?.radius && on.button === unset.button && on.card?.shadow === 'none',
		}[ id ];
		result( drawn && others, `${ id } ${ value } draws on the page, and nothing else moves${ FOLLOWS[ id ] ? ' (buttons follow the control corner)' : '' }`, JSON.stringify( { card: on.card, button: on.button, space: on.space } ) );
		result( on.style?.nonce && on.refusals.length === 0, `${ id } ${ value }: the brand <style> carries the CSP nonce and the browser refuses nothing`, on.refusals.join( ' | ' ) || 'no refusal' );
	}

	// Density at a narrow width, where the section rungs take their narrow values.
	site( 'set', 'density', 'compact' );
	const narrow = await read( 'density-compact', 600 );
	const narrowUnset = ( site( 'set', 'density', '' ), await read( 'unset', 600 ) );
	result( narrow.space.md === narrowUnset.space.sm && narrow.space.lg === narrowUnset.space.md && narrow.space.sm === narrowUnset.space.sm, 'density compact at 600px: md takes sm\'s narrow value, lg md\'s', JSON.stringify( { compact: narrow.space, unset: narrowUnset.space } ) );

	// ── The Editor: refused the page, and a posted dial is not saved ───────────────────────────
	const editor = await browser.newContext();
	const e = await editor.newPage();
	await e.goto( `${ base }/wp-login.php` );
	await e.fill( '#user_login', 'harness-dials-editor' );
	await e.fill( '#user_pass', process.env.SALT_HARNESS_EDITOR_PASS );
	await Promise.all( [ e.waitForURL( /wp-admin/ ), e.click( '#wp-submit' ) ] );
	const linked = await e.locator( 'a[href*="page=salt-settings-brand"]' ).count();
	const direct = await e.goto( `${ base }/wp-admin/admin.php?page=salt-settings-brand` );
	const dials = await e.locator( '.acf-field[data-name^="dial_"]' ).count();
	if ( shots ) {
		await e.screenshot( { path: path.join( shots, 'dials-editor-refused.png' ) } );
	}
	await editor.close();
	result( linked === 0 && direct.status() >= 400 && dials === 0, 'an Editor has no Brand & Design link, and the page refuses them with no dial drawn', `links ${ linked }; answered ${ direct.status() }; dials drawn ${ dials }` );
	const asEditor = JSON.parse( site( 'as-editor' ) );
	const after = JSON.parse( site( 'state' ) );
	result(
		! asEditor.can && Object.values( asEditor.drawn ).every( ( d ) => ! d ) && asEditor.dark_mode_saved && Object.values( after ).every( ( v ) => v === null || v === '' ),
		'for an Editor, ACF draws no dial and its own save drops posted dials (and still saves the other fields)',
		JSON.stringify( { ...asEditor, stored: after } )
	);
} finally {
	await browser.close();
	site( 'restore' );
}
process.exit( failures ? 1 : 0 );
