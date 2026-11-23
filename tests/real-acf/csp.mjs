// The theme's pages under its own CSP, in Chromium (HARNESS ONLY, run by
// run.sh): what the policy refuses shows up only in a real browser.
//
//  - WordPress core's speculation rules (printed on pretty permalinks)
//    carry the request's nonce, so the CSP lets them in: the console has
//    no refusal for them.
//  - A locations map: before consent nothing of MapLibre loads; with
//    consent it loads MapLibre's CSP build, whose worker runs from the
//    theme's own file (no blob: worker, which the CSP would refuse), and
//    the map draws its raster tiles (answered here, one colour) on its
//    canvas.
//  - Map settings that are not addresses load nothing.
/* global document, window */
import { execFileSync } from 'node:child_process';
import { deflateSync, crc32 } from 'node:zlib';
import { chromium } from 'playwright';

const php = process.env.SALT_HARNESS_PHP_BIN;
const site = ( ...args ) => execFileSync( php, [ new URL( './php/csp-page.php', import.meta.url ).pathname, ...args ], { encoding: 'utf8' } ).trim();
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};

// A 256-pixel square PNG of one colour: the tile every request is answered with.
const TILE_RGB = [ 200, 40, 120 ];
const tile = ( () => {
	const chunk = ( type, data ) => {
		const head = Buffer.alloc( 4 );
		head.writeUInt32BE( data.length );
		const body = Buffer.concat( [ Buffer.from( type ), data ] );
		const crc = Buffer.alloc( 4 );
		crc.writeUInt32BE( crc32( body ) );
		return Buffer.concat( [ head, body, crc ] );
	};
	const header = Buffer.alloc( 13 );
	header.writeUInt32BE( 256, 0 );
	header.writeUInt32BE( 256, 4 );
	header.set( [ 8, 2, 0, 0, 0 ], 8 );
	const row = Buffer.concat( [ Buffer.from( [ 0 ] ), Buffer.from( Array.from( { length: 256 }, () => TILE_RGB ).flat() ) ] );
	const pixels = Buffer.concat( Array.from( { length: 256 }, () => row ) );
	return Buffer.concat( [ Buffer.from( [ 137, 80, 78, 71, 13, 10, 26, 10 ] ), chunk( 'IHDR', header ), chunk( 'IDAT', deflateSync( pixels ) ), chunk( 'IEND', Buffer.alloc( 0 ) ) ] );
} )();

const url = site( 'seed' );
const browser = await chromium.launch();
try {
	const context = await browser.newContext();
	// Tiles: the configured https host, answered here with one colour.
	let tiles = 0;
	await context.route( 'https://tiles.salt-harness.test/**', ( route ) => {
		tiles++;
		return route.fulfill( { status: 200, contentType: 'image/png', body: tile, headers: { 'access-control-allow-origin': '*' } } );
	} );
	// Keep the map the theme creates, with its drawing buffer kept, to read its canvas.
	await context.addInitScript( () => {
		let library;
		Object.defineProperty( window, 'maplibregl', {
			configurable: true,
			get: () => library,
			set: ( value ) => {
				const Base = value.Map;
				value.Map = class extends Base {
					constructor( options ) {
						super( { ...options, canvasContextAttributes: { preserveDrawingBuffer: true } } );
						window.saltHarnessMap = this;
					}
				};
				library = value;
			},
		} );
	} );
	const page = await context.newPage();
	const requests = [];
	const workers = [];
	page.on( 'request', ( r ) => requests.push( r.url() ) );
	page.on( 'worker', ( w ) => workers.push( w.url() ) );
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

	// The map, before consent.
	const loaded = () => requests.filter( ( u ) => /\/maps\/maplibre/.test( u ) );
	const maps = await page.locator( '.salt-map' ).evaluateAll( ( els ) => els.map( ( e ) => e.dataset.zoom ) );
	result( maps.includes( '15' ) && maps.includes( '14' ), 'the offices\' maps: the stored zoom and the fallback', JSON.stringify( maps ) );
	// The map sits in its office's closed disclosure: a click as a visitor's.
	const load = () => page.locator( '.salt-map__load' ).first().evaluate( ( button ) => button.click() );
	await load();
	await page.waitForTimeout( 500 );
	result( loaded().length === 0 && tiles === 0 && await page.locator( '.salt-map__notice' ).count() > 0, 'before consent: the notice, and nothing of MapLibre or a tile loads', requests.filter( ( u ) => /maplibre|tiles\./.test( u ) ).join( ', ' ) );

	// With consent (a consent management plugin's grant, as the WP Consent API exposes it).
	await page.evaluate( () => {
		window.wp_consent_type = 'optin';
		window.wp_has_consent = ( category ) => category === 'marketing';
	} );
	await load();
	const drawn = await page.waitForFunction( () => window.saltHarnessMap && window.saltHarnessMap.loaded() && window.saltHarnessMap.areTilesLoaded(), null, { timeout: 20000 } ).then( () => true, () => false );
	const workerUrls = workers.join( ', ' );
	result( workers.length > 0 && workers.every( ( w ) => w.startsWith( new URL( url ).origin + '/' ) && /\/maps\/maplibre-worker\.[0-9a-f]+\.js$/.test( w ) ), 'MapLibre\'s worker runs from the theme\'s own file', workerUrls || '(no worker)' );
	const pixel = drawn ? await page.evaluate( () => {
		const canvas = window.saltHarnessMap.getCanvas();
		const copy = document.createElement( 'canvas' );
		copy.width = canvas.width;
		copy.height = canvas.height;
		const context2d = copy.getContext( '2d' );
		context2d.drawImage( canvas, 0, 0 );
		return [ ...context2d.getImageData( 10, 10, 1, 1 ).data ].slice( 0, 3 );
	} ) : [];
	result( drawn && tiles > 0 && pixel.every( ( c, i ) => Math.abs( c - TILE_RGB[ i ] ) <= 4 ), 'a consented map draws its tiles on its canvas', `${ tiles } tiles served; canvas pixel ${ JSON.stringify( pixel ) }` );
	const blocked = errors.filter( ( e ) => /worker|blob:|Content Security Policy/i.test( e ) && ! /Applying inline style/.test( e ) );
	result( blocked.length === 0, 'the CSP refuses no worker and no blob:', blocked.join( ' | ' ) );
	await context.close();

	// Map settings that are not addresses (another script overwrote them):
	// a consented click loads nothing and shows the facade's error message.
	const tampered = await browser.newContext();
	await tampered.addInitScript( () => {
		Object.defineProperty( window, 'saltMaps', { get: () => ( { maplibre: { src: '/x' }, worker: [ 'elsewhere' ] } ), set: () => {} } );
		window.wp_consent_type = 'optin';
		window.wp_has_consent = ( category ) => category === 'marketing';
	} );
	const odd = await tampered.newPage();
	const oddRequests = [];
	odd.on( 'request', ( r ) => oddRequests.push( r.url() ) );
	await odd.goto( url, { waitUntil: 'networkidle' } );
	const before = oddRequests.length;
	await odd.locator( '.salt-map__load' ).first().evaluate( ( button ) => button.click() );
	await odd.waitForTimeout( 1000 );
	const after = oddRequests.slice( before );
	result( after.length === 0 && await odd.locator( '.salt-map__notice' ).count() > 0, 'map settings that are not addresses: nothing loads, the error message shows', after.join( ', ' ) || 'no request' );
	await tampered.close();
} finally {
	await browser.close();
	site( 'restore' );
}
process.exit( failures ? 1 : 0 );
