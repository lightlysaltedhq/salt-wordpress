// The page inset in Chromium (HARNESS ONLY, run by run.sh): what <main> draws
// outside a section band, on the views whose title, text, search form and
// link home are <main>'s own children (assets/src/css/app.css, "The page
// inset"). 6.0.0 shipped them at the screen's edge, under the header.
//
// On the not-found page, a search with no result and the services archive, at
// 320px and 1280px:
//  - each of those children keeps at least the header's gutter on both sides
//    (the site logo's left edge is the measure, which this rule does not set);
//  - the title starts where a default band's heading does (read from the
//    archive's listing band, which the contract's stylesheet places);
//  - the first stands off the header and the last off the footer;
//  - the not-found page's link home is at least 24px tall (WCAG 2.5.8);
//  - the page has no sideways scroll.
// And the control: with the inset's rules taken out of the served stylesheet,
// the not-found page's title is back at the screen's edge, so the checks
// above read the rule and not something else.
//
// With SALT_HARNESS_SCREENSHOTS set to a directory, it saves each view there
// (page-inset-<view>-<width>.png).
/* global document, window */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const base = process.env.SALT_HARNESS_URL;
const shots = process.env.SALT_HARNESS_SCREENSHOTS ?? '';
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};

// Plain permalinks: the harness's own until a later phase switches them.
const VIEWS = {
	'not-found': '/?page_id=2147483000',
	search: '/?s=salt-harness-page-inset-nothing',
	// A post type's archive: the theme's front-page template takes '/'.
	archive: '/?post_type=service',
};
const INSET_RULE = /:where\(#main\)/g;

const measure = () => {
	const box = ( e ) => {
		const r = e.getBoundingClientRect();
		return { left: Math.round( r.left ), right: Math.round( window.innerWidth - r.right ), top: Math.round( r.top ), bottom: Math.round( r.bottom ), height: Math.round( r.height ) };
	};
	const main = document.getElementById( 'main' );
	const named = ( e ) => `${ e.tagName.toLowerCase() }${ typeof e.className === 'string' && e.className ? `.${ e.className.split( /\s+/ )[ 0 ] }` : '' }`;
	// What <main> draws outside a band: its children that are neither a band nor hidden.
	const loose = [ ...main.children ].filter( ( e ) => ! e.matches( '.salt-section, .salt-sr-only' ) && e.getClientRects().length > 0 );
	const heading = main.querySelector( '.salt-section[data-width="default"] > .salt-section__content :is(h1, h2)' );
	const link = main.querySelector( ':scope > a' );
	return {
		loose: loose.map( ( e ) => ( { name: named( e ), ...box( e ) } ) ),
		title: main.querySelector( ':scope > h1, :scope > header > h1' ) ? box( main.querySelector( ':scope > h1, :scope > header > h1' ) ) : null,
		gutter: box( document.querySelector( '.salt-logo' ) ).left,
		headerBottom: box( document.querySelector( '.salt-header' ) ).bottom,
		footerTop: box( document.querySelector( '.salt-footer' ) ).top,
		lastIsLoose: loose.length > 0 && loose[ loose.length - 1 ] === main.lastElementChild,
		bandHeading: heading ? box( heading ).left : null,
		link: link ? box( link ) : null,
		sideways: document.documentElement.scrollWidth > window.innerWidth,
	};
};

const browser = await chromium.launch();
try {
	const read = async ( view, width, { withoutRule = false } = {} ) => {
		const context = await browser.newContext( { viewport: { width, height: 900 } } );
		if ( withoutRule ) {
			await context.route( '**/assets/dist/app.*.css', async ( route ) => {
				const served = await route.fetch();
				await route.fulfill( { response: served, body: ( await served.text() ).replace( INSET_RULE, ':where(#salt-harness-no-such-element)' ) } );
			} );
		}
		const page = await context.newPage();
		await page.goto( `${ base }${ VIEWS[ view ] }`, { waitUntil: 'networkidle' } );
		const seen = await page.evaluate( measure );
		if ( shots && ! withoutRule ) {
			fs.mkdirSync( shots, { recursive: true } );
			await page.screenshot( { path: path.join( shots, `page-inset-${ view }-${ width }.png` ), fullPage: true } );
		}
		await context.close();
		return seen;
	};

	for ( const width of [ 320, 1280 ] ) {
		const archive = await read( 'archive', width );
		result( archive.bandHeading !== null && archive.title !== null, `${ width }px: the services archive draws its title and a default listing band to measure against`, JSON.stringify( { title: archive.title, bandHeading: archive.bandHeading } ) );
		for ( const view of Object.keys( VIEWS ) ) {
			const seen = view === 'archive' ? archive : await read( view, width );
			const flush = seen.loose.filter( ( e ) => e.left < seen.gutter || e.right < seen.gutter );
			result(
				seen.loose.length > 0 && flush.length === 0,
				`${ width }px ${ view }: what <main> draws outside a band keeps the header's gutter (${ seen.gutter }px) on both sides`,
				flush.length > 0 ? `at the edge: ${ JSON.stringify( flush ) }` : seen.loose.map( ( e ) => `${ e.name } ${ e.left }/${ e.right }` ).join( ', ' )
			);
			result( seen.title?.left === archive.bandHeading, `${ width }px ${ view }: the title starts where a default band's heading does`, `title ${ seen.title?.left }px, band heading ${ archive.bandHeading }px` );
			const above = seen.loose.length > 0 ? seen.loose[ 0 ].top - seen.headerBottom : 0;
			const below = seen.lastIsLoose ? seen.footerTop - seen.loose[ seen.loose.length - 1 ].bottom : null;
			result( above >= 24 && ( below === null || below >= 24 ), `${ width }px ${ view }: the first stands off the header and the last off the footer`, `${ above }px above; ${ below === null ? 'a band is last' : `${ below }px below` }` );
			result( ! seen.sideways, `${ width }px ${ view }: no sideways scroll` );
			if ( view === 'not-found' ) {
				result( seen.link !== null && seen.link.height >= 24, `${ width }px not-found: the link home is at least 24px tall`, seen.link ? `${ seen.link.height }px` : 'no link home drawn' );
			}
		}
	}

	const control = await read( 'not-found', 1280, { withoutRule: true } );
	result( control.title?.left === 0 && control.loose[ 0 ].top === control.headerBottom, 'control: without the inset\'s rules the not-found title is at the screen\'s edge, under the header', JSON.stringify( { title: control.title, headerBottom: control.headerBottom } ) );
} finally {
	await browser.close();
}
process.exit( failures ? 1 : 0 );
