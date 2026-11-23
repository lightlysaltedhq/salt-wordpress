// The theme's pages under its own CSP, in Chromium (HARNESS ONLY, run by
// run.sh): what the policy refuses shows up only in a real browser.
//
//  - WordPress core's speculation rules (printed on pretty permalinks)
//    carry the request's nonce, so the CSP lets them in: the console has
//    no refusal for them.
/* global document */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const php = process.env.SALT_HARNESS_PHP_BIN;
const site = ( ...args ) => execFileSync( php, [ new URL( './php/csp-page.php', import.meta.url ).pathname, ...args ], { encoding: 'utf8' } ).trim();
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};

const url = site( 'seed' );
const browser = await chromium.launch();
try {
	const context = await browser.newContext();
	const page = await context.newPage();
	const errors = [];
	page.on( 'console', ( m ) => m.type() === 'error' && errors.push( m.text() ) );
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	const response = await page.goto( url, { waitUntil: 'networkidle' } );
	result( /script-src 'self' 'nonce-/.test( response.headers()[ 'content-security-policy' ] || '' ), 'the page carries the theme\'s CSP', url );

	// Speculation rules.
	const rules = await page.evaluate( () => [ ...document.querySelectorAll( 'script[type=speculationrules]' ) ].map( ( s ) => ( { nonce: s.nonce, rules: s.textContent.trim().slice( 0, 60 ) } ) ) );
	result( rules.length === 1 && rules[ 0 ].nonce !== '' && rules[ 0 ].rules.startsWith( '{' ), 'core\'s speculation rules are printed with the nonce', JSON.stringify( rules ) );
	const refused = errors.filter( ( e ) => /speculation rules|speculationrules/i.test( e ) );
	result( refused.length === 0, 'the CSP refuses no speculation rules', refused.join( ' | ' ) );
	await context.close();
} finally {
	await browser.close();
	site( 'restore' );
}
process.exit( failures ? 1 : 0 );
