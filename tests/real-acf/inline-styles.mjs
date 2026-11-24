// No style the theme's CSP refuses, in Chromium (HARNESS ONLY, run by
// run.sh). The CSP allows styles from 'self' and the request's nonce only,
// with no 'unsafe-inline' and no hashes (SEC06), so every <style> without
// the nonce and every style attribute is refused, and its rules never
// apply. This loads the representative pages (home, post, archive,
// contact, case study and a page of core blocks), signed out and signed in
// with the admin bar, counts the browser's style-src refusals at page load
// and requires none. It also screenshots the blocks page, to show core's
// block styles apply (SALT_HARNESS_SHOTS names the folder, when set).
/* global document, window, getComputedStyle */
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const php = process.env.SALT_HARNESS_PHP_BIN;
const base = process.env.SALT_HARNESS_URL;
const shots = process.env.SALT_HARNESS_SHOTS || '';
const site = ( mode ) => execFileSync( php, [ new URL( './php/inline-styles-pages.php', import.meta.url ).pathname, mode ], { encoding: 'utf8' } ).trim();
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};

const pages = JSON.parse( site( 'seed' ) );
const browser = await chromium.launch();
try {
	const watch = async ( context ) => {
		await context.addInitScript( () => {
			window.saltHarnessRefusals = [];
			document.addEventListener( 'securitypolicyviolation', ( e ) => {
				if ( e.effectiveDirective.startsWith( 'style-src' ) ) {
					const node = e.target && e.target.nodeType === 1 ? e.target : null;
					const id = node ? `${ node.tagName.toLowerCase() }${ node.id ? '#' + node.id : '' }` : '';
					window.saltHarnessRefusals.push( `${ e.effectiveDirective } ${ id || e.blockedURI } ${ ( e.sample || '' ).slice( 0, 40 ) }`.trim() );
				}
			} );
		} );
	};
	const visit = async ( context, who ) => {
		for ( const [ name, url ] of Object.entries( pages ) ) {
			const page = await context.newPage();
			const response = await page.goto( url, { waitUntil: 'networkidle' } );
			await page.waitForTimeout( 200 );
			const refusals = await page.evaluate( () => window.saltHarnessRefusals );
			const csp = response.headers()[ 'content-security-policy' ] || '';
			result( response.status() === 200 && /style-src 'self' 'nonce-/.test( csp ) && refusals.length === 0, `${ who }: ${ name } loads with no style-src refusal`, refusals.length ? `${ refusals.length } refused: ${ refusals.join( ' | ' ) }` : `${ response.status() }` );
			if ( 'post' === name && 'signed out' === who ) {
				await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ], { origin: new URL( url ).origin } );
				const button = page.locator( '.salt-copy-link__button' );
				const status = page.locator( '.salt-copy-link [role="status"]' );
				await button.click();
				const canonical = await page.locator( 'link[rel="canonical"]' ).getAttribute( 'href' );
				const copied = await page.evaluate( () => navigator.clipboard.readText() );
				result( copied === canonical && await status.textContent() === 'Link copied', 'post: Copy link copies the canonical URL and announces success under CSP' );
				await page.evaluate( () => { navigator.clipboard.writeText = () => Promise.reject( new Error( 'Clipboard refused' ) ); } );
				await button.click();
				result( await status.textContent() === 'Could not copy the link', 'post: Copy link announces a refused clipboard write under CSP' );
			}
			if ( 'blocks' === name ) {
				const applied = await page.evaluate( () => {
					const button = document.querySelector( '.wp-block-button__link' );
					const columns = document.querySelector( '.wp-block-columns' );
					return { button: button ? getComputedStyle( button ).display : null, columns: columns ? getComputedStyle( columns ).display : null };
				} );
				result( applied.columns === 'flex', `${ who }: core's block styles apply (columns lay out in a row)`, JSON.stringify( applied ) );
				if ( shots ) {
					mkdirSync( shots, { recursive: true } );
					await page.screenshot( { path: `${ shots }/blocks-${ who.replace( /\W+/g, '-' ) }.png`, fullPage: true } );
				}
			}
			await page.close();
		}
	};

	const visitor = await browser.newContext();
	await watch( visitor );
	await visit( visitor, 'signed out' );
	// Decoys stay refused: in the head before core's own, a <style> reusing
	// the id of the page's core-block-supports style with other CSS, and one
	// with an unrelated id; in the body, that core id again. Core's own still
	// gets the nonce, so the refusals are those three and no more.
	const decoy = await visitor.newPage();
	await decoy.goto( `${ pages.blocks }?salt_harness_decoy=1`, { waitUntil: 'networkidle' } );
	await decoy.waitForTimeout( 200 );
	const decoyRefusals = await decoy.evaluate( () => window.saltHarnessRefusals );
	const decoyCore = decoyRefusals.filter( ( r ) => r.includes( 'style#core-block-supports-inline-css' ) ).length;
	const decoyUnrelated = decoyRefusals.filter( ( r ) => r.includes( 'style#salt-harness-unrelated' ) ).length;
	result( decoyRefusals.length === 3 && decoyCore === 2 && decoyUnrelated === 1, 'signed out: decoy <style>s (a core id with other CSS in the head and the body, an unrelated id in the head) are refused, and core\'s own is not', decoyRefusals.join( ' | ' ) || 'none refused' );
	await decoy.close();
	await visitor.close();

	const editor = await browser.newContext();
	await watch( editor );
	const login = await editor.newPage();
	await login.goto( `${ base }/wp-login.php` );
	await login.fill( '#user_login', 'admin' );
	await login.fill( '#user_pass', process.env.SALT_HARNESS_ADMIN_PASS );
	await Promise.all( [ login.waitForURL( /wp-admin/ ), login.click( '#wp-submit' ) ] );
	await login.close();
	const bar = await editor.newPage();
	await bar.goto( pages.home, { waitUntil: 'networkidle' } );
	result( await bar.locator( '#wpadminbar' ).count() === 1, 'signed in: the admin bar is drawn on the front end' );
	await bar.close();
	await visit( editor, 'signed in' );
	await editor.close();
} finally {
	await browser.close();
	site( 'restore' );
}
process.exit( failures ? 1 : 0 );
