/** ID-3963: real native form posts at max_input_vars=1000, including a destructive negative control. */
/* global window, document, HTMLFormElement */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const url = process.env.SALT_HARNESS_URL;
const php = ( ...args ) => JSON.parse( execFileSync( process.env.SALT_HARNESS_PHP_BIN, [ path.join( here, 'php/input-vars.php' ), ...args.map( String ) ], { encoding: 'utf8' } ) || 'null' );
let failures = 0;
const check = ( ok, label, detail ) => {
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ label }: ${ detail }` );
	if ( ! ok ) {
		failures++;
	}
};
const ids = php( 'setup' );
const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
context.setDefaultTimeout( 120000 );
try {
	const page = await context.newPage();
	await page.goto( `${ url }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', process.env.SALT_HARNESS_ADMIN_PASS );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

	// The same native multipart form submission for guarded and unguarded pages.
	// Bypass only ACF's AJAX validation, just as a no-script or Save Draft request can.
	for ( const name of [ 'guarded', 'control', 'small' ] ) {
		php( name === 'control' ? 'guard-off' : 'guard-on' );
		const id = ids[ name ];
		await page.goto( `${ url }/wp-admin/post.php?post=${ id }&action=edit` );
		await page.waitForFunction( () => window.acf && document.readyState === 'complete' );
		const before = php( id );
		await page.fill( '#title', `Changed ${ name }` );
		const form = await page.evaluate( () => {
			const entries = [ ...new FormData( document.getElementById( 'post' ) ).entries() ];
			return { count: entries.length, last: entries.at( -1 )[ 0 ] };
		} );
		check( form.last === 'salt_form_complete', `${ name }: completion marker is last`, JSON.stringify( form ) );
		const start = Date.now();
		const [ response ] = await Promise.all( [
			page.waitForNavigation( { timeout: 600000 } ),
			page.evaluate( () => HTMLFormElement.prototype.submit.call( document.getElementById( 'post' ) ) ),
		] );
		const after = php( id );
		const detail = `${ form.count } inputs; HTTP ${ response.status() }; ${ before.rows } → ${ after.rows } rows; ${ ( Date.now() - start ) / 1000 } s`;
		if ( name === 'guarded' ) {
			check( form.count > 1000 && response.status() === 400 && before.snapshot === after.snapshot && ( await page.textContent( 'body' ) ).includes( 'Nothing from this save was written' ), 'oversized save refused before ANY post, meta or revision changes', detail );
			if ( process.env.SALT_HARNESS_SCREENSHOTS ) {
				fs.mkdirSync( process.env.SALT_HARNESS_SCREENSHOTS, { recursive: true } );
				await page.screenshot( { path: path.join( process.env.SALT_HARNESS_SCREENSHOTS, 'input-vars-refused.png' ), fullPage: true } );
			}
		} else if ( name === 'control' ) {
			check( response.status() === 200 && after.rows > 0 && after.rows < before.rows && after.title === 'Changed control', 'negative control: disabling ONLY the guard reproduces silent row loss', detail );
		} else {
			check( form.count < 1000 && response.status() === 200 && after.rows === 1 && after.title === 'Changed small', 'under-limit save still persists edits', detail );
		}
	}
	// No-script editors also receive the final control; a complete form missing
	// the marker (for example an incompletely loaded editor) must fail closed.
	const noScript = await browser.newContext( { javaScriptEnabled: false, storageState: await context.storageState() } );
	const plain = await noScript.newPage();
	await plain.goto( `${ url }/wp-admin/post.php?post=${ ids.small }&action=edit` );
	check( await plain.locator( 'input[form="post"][name="salt_form_complete"]' ).count() === 1, 'no JavaScript required for completion marker', 'server-rendered control present' );
	const payload = await page.goto( `${ url }/wp-admin/post.php?post=${ ids.small }&action=edit` ).then( () => page.evaluate( () => [ ...new FormData( document.getElementById( 'post' ) ).entries() ].filter( ( [ key ] ) => key !== 'salt_form_complete' ).map( ( [ key, value ] ) => [ key, String( value ) ] ) ) );
	const before = php( ids.small );
	const missing = await context.request.post( `${ url }/wp-admin/post.php`, { data: new URLSearchParams( payload ).toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } } );
	check( missing.status() === 400 && before.snapshot === php( ids.small ).snapshot, 'missing marker fails closed even under limit (URL-encoded)', `HTTP ${ missing.status() }` );
	for ( const count of [ 1000, 1001, 1002 ] ) {
		// Use native scalar controls so this does not depend on ACF's form shape.
		// The built-in server's URL-encoded POST parser retains one extra input
		// (unlike parse_str); 1001 is complete, 1002 loses the final marker.
		const entries = payload.filter( ( [ key ] ) => [ '_wpnonce', 'action', 'post_ID', 'post_type', 'post_status', 'post_title', 'original_post_status' ].includes( key ) ).map( ( [ key, value ] ) => [ key, key === 'post_title' ? `Boundary ${ count }` : value ] );
		while ( entries.length < count - 1 ) {
			entries.push( [ `padding_${ entries.length }`, 'x' ] );
		}
		entries.push( [ 'salt_form_complete', '1' ] );
		const before = php( ids.small );
		const response = await context.request.post( `${ url }/wp-admin/post.php`, { data: new URLSearchParams( entries ).toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } } );
		const after = php( ids.small );
		check( count <= 1001 ? response.status() === 200 && after.title === `Boundary ${ count }` && after.rows === 1 : response.status() === 400 && after.snapshot === before.snapshot, `exact input boundary ${ count }`, `HTTP ${ response.status() }; title ${ after.title }; ${ after.rows } rows` );
	}
} catch ( error ) {
	check( false, 'input-limit checks ran to completion', error.stack );
} finally {
	php( 'guard-on' );
	await browser.close();
}
process.exitCode = failures ? 1 : 0;
