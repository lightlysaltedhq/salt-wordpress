// The contact form over real requests, in Chromium with the theme's CSP on
// (HARNESS ONLY, run by run.sh). The unit suite and the contract adapter
// seed the form's answered state; this posts to the real page, so it sees
// what WordPress does with the posted fields (a posted `name` is a public
// query var) and what the browser's script does between sends.
//
//  - Without script, on plain and pretty permalinks: the first send has no
//    credentials and is refused on the page itself (no redirect, not
//    cacheable), with every entry kept and a fresh challenge; answering it
//    sends, and the enquiry is stored.
//  - With script (pretty permalinks, where /wp-json/ answers): a send
//    refused for a field error, then corrected straight away, arrives (the
//    form token is kept for the retry, so the time trap passes); a second
//    message after it arrives too (a new token is fetched at once). A form
//    left open 5 hours (its token minted 5 hours back, through the harness
//    clock) is refused 403 as stale and sent after one refetch.
//  - A single post's own address, posted a contact `name`: the post still
//    answers, by its permalink's own name.
//  - A form-encoded REST send with the self-post flag in its address: the
//    REST API reads every field it was sent (they are back in $_POST first).
//  - Another form posted to the answered page, whose address keeps the
//    flag: not handled as a contact send.
/* global document */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const php = process.env.SALT_HARNESS_PHP_BIN;
const site = ( ...args ) => execFileSync( php, [ new URL( './php/contact-form.php', import.meta.url ).pathname, ...args ], { encoding: 'utf8' } ).trim();
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};
const info = ( line ) => console.log( `     ${ line }` );
const send = ( page, form ) => Promise.all( [ page.waitForResponse( ( r ) => r.request().method() === 'POST' && r.url().includes( '/salt/v1/contact' ) ), form.locator( '.salt-contact__submit' ).click() ] ).then( ( [ r ] ) => r );
const wait = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

site( 'seed' );
const browser = await chromium.launch();
try {
	for ( const permalinks of [ 'plain', 'pretty' ] ) {
		const url = site( 'permalinks', permalinks );
		site( 'reset' );
		info( `${ permalinks } permalinks: ${ url }` );
		result( ( permalinks === 'pretty' ) === ! url.includes( '?' ), `${ permalinks }: the page's address is a ${ permalinks } permalink`, url );

		// Without script.
		const name = `Ada NoScript ${ permalinks }`;
		const context = await browser.newContext( { javaScriptEnabled: false } );
		const page = await context.newPage();
		await page.goto( url );
		const form = () => page.locator( 'form#enquire__form' );
		await form().locator( '[name=name]' ).fill( name );
		await form().locator( '[name=email]' ).fill( 'ada@example.org' );
		await form().locator( '[name=phone]' ).fill( '0117 496 0123' );
		await form().locator( '[name=enquiryType]' ).selectOption( 'maintenance' );
		await form().locator( '[name=message]' ).fill( 'No script.' );
		const [ refused ] = await Promise.all( [ page.waitForResponse( ( r ) => r.request().method() === 'POST' ), form().locator( '.salt-contact__submit' ).click() ] );
		await page.waitForLoadState();
		const kept = await form().count() === 1 && await form().locator( '[name=name]' ).inputValue() === name && await form().locator( '[name=phone]' ).inputValue() === '0117 496 0123' && await form().locator( '[name=enquiryType]' ).inputValue() === 'maintenance' && await form().locator( '[name=message]' ).inputValue() === 'No script.';
		result( refused.status() === 400 && kept, `${ permalinks }: a first send without credentials is refused on the page, every entry kept`, `status ${ refused.status() }` );
		result( /no-store|no-cache/.test( refused.headers()[ 'cache-control' ] || '' ), `${ permalinks }: the answer is not cacheable`, refused.headers()[ 'cache-control' ] || '(none)' );
		const question = ( await form().locator( 'label[for=enquire__challenge]' ).textContent() ) || '';
		const numbers = question.match( /(\d+)\D+(\d+)/ );
		result( null !== numbers && await form().locator( 'input[name=formToken]' ).count() === 1, `${ permalinks }: a fresh challenge and form token`, question );
		await form().locator( '[name=challengeAnswer]' ).fill( String( Number( numbers?.[ 1 ] ) + Number( numbers?.[ 2 ] ) ) );
		await wait( 3500 ); // The form token's time trap.
		const [ sent ] = await Promise.all( [ page.waitForResponse( ( r ) => r.request().method() === 'POST' ), form().locator( '.salt-contact__submit' ).click() ] );
		await page.waitForLoadState();
		const status = form().locator( '.salt-contact__status' );
		result( sent.status() === 200 && await status.getAttribute( 'data-tone' ) === 'sent' && ! /salt-contact/.test( new URL( page.url() ).search.replace( 'salt-contact-send', '' ) ), `${ permalinks }: the answered send arrives, answered in place`, `status ${ sent.status() }, "${ await status.textContent() }"` );
		result( site( 'stored', name ) === '1', `${ permalinks }: the enquiry is stored once` );
		await context.close();
	}

	// With script, under the CSP: a refused send, then a corrected one at once.
	const scripted = await browser.newContext();
	const page = await scripted.newPage();
	const errors = [];
	page.on( 'console', ( m ) => m.type() === 'error' && errors.push( m.text() ) );
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( site( 'permalinks', 'pretty' ) );
	const form = page.locator( 'form#enquire__form' );
	await form.locator( '[name=name]' ).fill( 'Ada Script' );
	await form.locator( '[name=email]' ).fill( 'ada@' );
	await form.locator( '[name=message]' ).fill( 'With script.' );
	await wait( 3500 ); // The token fetched on first focus ages past the time trap.
	const first = await send( page, form );
	const status = form.locator( '.salt-contact__status' );
	await page.waitForFunction( () => document.querySelector( '#enquire__status' )?.dataset.tone !== 'sending' );
	info( `script: first send ${ first.status() }` );
	result( await form.locator( '#enquire__email-error' ).count() === 1 && await form.locator( '#enquire__email' ).getAttribute( 'aria-invalid' ) === 'true', 'script: a field error is marked in place', await status.textContent() );
	await form.locator( '[name=email]' ).fill( 'ada@example.org' );
	const second = await send( page, form );
	await page.waitForFunction( () => document.querySelector( '#enquire__status' )?.dataset.tone !== 'sending' );
	info( `script: corrected send ${ second.status() }` );
	result( await status.getAttribute( 'data-tone' ) === 'sent', 'script: the corrected send arrives at once (the token is kept)', await status.textContent() );
	result( await status.evaluate( ( s ) => document.activeElement === s ), 'script: focus moves to the status' );
	result( site( 'stored', 'Ada Script' ) === '1', 'script: the enquiry is stored once' );
	await form.locator( '[name=name]' ).fill( 'Ada Script again' );
	await form.locator( '[name=email]' ).fill( 'ada@example.org' );
	await form.locator( '[name=message]' ).fill( 'A second message.' );
	await wait( 3500 ); // A visitor writing the next message.
	const third = await send( page, form );
	await page.waitForFunction( () => document.querySelector( '#enquire__status' )?.dataset.tone !== 'sending' );
	result( third.status() === 200 && await status.getAttribute( 'data-tone' ) === 'sent' && site( 'stored', 'Ada Script again' ) === '1', 'script: a second message after a send arrives', `status ${ third.status() }` );
	// A form left open 5 hours: the token fetched on focus is stale, refused
	// with 403; the script takes a fresh one and the send arrives.
	site( 'reset' ); // A fresh rate limit for the checks below.
	await page.reload();
	const events = [];
	page.on( 'request', ( r ) => r.url().includes( '/contact/credentials' ) && events.push( 'credentials' ) );
	page.on( 'response', ( r ) => r.request().method() === 'POST' && r.url().includes( '/salt/v1/contact' ) && events.push( `send ${ r.status() }` ) );
	site( 'clock', String( -5 * 3600 ) );
	const prefetched = page.waitForResponse( ( r ) => r.url().includes( '/contact/credentials' ) );
	await form.locator( '[name=name]' ).fill( 'Ada Five Hours' );
	await prefetched;
	site( 'clock', '0' );
	await form.locator( '[name=email]' ).fill( 'ada@example.org' );
	await form.locator( '[name=message]' ).fill( 'Left open over lunch.' );
	await form.locator( '.salt-contact__submit' ).click();
	await page.waitForFunction( () => [ 'sent', 'problem' ].includes( document.querySelector( '#enquire__status' )?.dataset.tone ), null, { timeout: 15000 } );
	// The last fetch is the next message's token, taken after the send.
	result( events.join( ', ' ) === 'credentials, send 403, credentials, send 200, credentials' && await status.getAttribute( 'data-tone' ) === 'sent' && site( 'stored', 'Ada Five Hours' ) === '1', 'script: a send 5 hours after focus arrives after one refetch', `${ events.join( ', ' ) }; "${ await status.textContent() }"` );
	// A single post posted a contact `name`: WordPress queries it by its permalink.
	const post = site( 'post' );
	const posted = await scripted.request.post( `${ post }?salt-contact-send=enquire`, { form: { name: 'Ada Lovelace', message: 'x', returnTo: new URL( post ).pathname } } );
	// The handler refuses the credential-less send (400); the post's own page is drawn, never a 404.
	result( posted.status() !== 404 && ( await posted.text() ).includes( 'Contact check post' ), 'a single post keeps its own name under a posted contact name', `status ${ posted.status() } at ${ post }` );
	// A form-encoded REST send with the self-post flag in its address: its
	// fields are back in $_POST before the REST API reads them.
	const rest = new URL( '/wp-json/salt/v1/contact', post );
	const credentialsResponse = await scripted.request.get( `${ rest }/credentials` );
	const credentials = await credentialsResponse.json();
	result( /no-store/.test( credentialsResponse.headers()[ 'cache-control' ] || '' ) && ! ( 'last-modified' in credentialsResponse.headers() ), 'the credentials are not cacheable, with no empty Last-Modified', JSON.stringify( credentialsResponse.headers() ) );
	await wait( 3500 );
	const encoded = await scripted.request.post( `${ rest }?salt-contact-send=enquire`, { form: { name: 'Ada Encoded', email: 'ada@example.org', enquiryType: 'maintenance', message: 'Form-encoded.', website: '', returnTo: '/contact-check/', formToken: credentials.formToken } } );
	// Another form posted to the answered page (its address keeps the flag):
	// not a contact send, so the page draws as usual and spends nothing.
	const answered = `${ site( 'permalinks', 'pretty' ) }?salt-contact-send=enquire`;
	const other = await scripted.request.post( answered, { form: { comment: 'Another form on the page.' } } );
	const otherHtml = await other.text();
	result( other.status() === 200 && otherHtml.includes( 'id="enquire__form"' ) && ! otherHtml.includes( 'name="formToken"' ) && ! /data-tone="problem"/.test( otherHtml ), 'another form posted to the answered page is not a contact send', `status ${ other.status() }` );
	result( encoded.status() === 200 && site( 'stored', 'Ada Encoded' ) === '1', 'a form-encoded REST send flagged as a self-post keeps its name', `status ${ encoded.status() } ${ await encoded.text() }` );
	// Not this check's: WordPress core's speculation rules and the band
	// wrapper's style attribute, which the CSP refuses on every page, and
	// the refused sends' own 400 and 403.
	const own = errors.filter( ( e ) => ! /speculation rules|Applying inline style|status of 40[03]/.test( e ) );
	result( own.length === 0, 'script: no other console error', own.join( ' | ' ) );
	await scripted.close();
} finally {
	await browser.close();
	site( 'permalinks', 'plain' );
	site( 'reset' );
}
process.exit( failures ? 1 : 0 );
