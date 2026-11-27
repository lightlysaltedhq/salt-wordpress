// The link underlines in Chromium (HARNESS ONLY, run by run.sh): the links a
// view writes with no class of their own, which 6.0.0 drew as plain text. The
// theme's stylesheet resets a link's line (Tailwind's base, `a {
// text-decoration: inherit }`) and the contract's stylesheet (salt.css, from
// 3.0.0) puts it back by rule; the contract's own stylesheet gate cannot see
// the theme's reset, so this reads the pages as the theme serves them.
//
// On pages php/link-underlines-pages.php seeds, at 320px and 1280px:
//  - a post's breadcrumb, category, byline, tags, previous and next links and
//    its author box's name, a search result's title, the not-found page's
//    link home and an office's phone and email each carry an underline
//    (computed on the link), and the browser draws it: the link's pixels
//    change when the line alone is taken away;
//  - the current breadcrumb and the site logo's link, which the contract
//    styles with no line, still have none.
// And the control: with the contract's rules for these links taken out of the
// served stylesheet, every one of them is back to no line and draws none, so
// the checks above read those rules and fail when the underline is lost.
//
// With SALT_HARNESS_SCREENSHOTS set to a directory, it saves each view there
// (link-underlines-<view>-<width>.png).
/* global document, getComputedStyle, window */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const php = process.env.SALT_HARNESS_PHP_BIN;
const shots = process.env.SALT_HARNESS_SCREENSHOTS ?? '';
const site = ( mode ) => execFileSync( php, [ new URL( './php/link-underlines-pages.php', import.meta.url ).pathname, mode ], { encoding: 'utf8' } ).trim();
let failures = 0;
const result = ( ok, check, detail = '' ) => {
	failures += ok ? 0 : 1;
	console.log( `${ ok ? 'PASS' : 'FAIL' } ${ check }${ detail ? `: ${ detail }` : '' }` );
};

// view => what the link is => [ its selector, the fewest the seeded page draws ].
const UNDERLINED = {
	post: {
		'the breadcrumb\'s link': [ '.salt-post__breadcrumb a:not([aria-current])', 1 ],
		'the category': [ '.salt-post__category a', 1 ],
		'the byline\'s name': [ '.salt-post__byline a', 1 ],
		'the tags': [ '.salt-post__tags a', 2 ],
		'the previous and next links': [ '.salt-post__adjacent a', 2 ],
		'the author box\'s name': [ '.salt-author-box__name a', 1 ],
	},
	search: { 'a result\'s title': [ '.salt-search__results > li > a', 1 ] },
	'not-found': { 'the link home': [ 'main > a', 1 ] },
	offices: {
		'an office\'s phone': [ '.salt-locations__contact a[href^="tel:"]', 1 ],
		'an office\'s email': [ '.salt-locations__contact a[href^="mailto:"]', 1 ],
	},
};
// Links the contract styles with no line: unchanged by its rules for the ones above.
const PLAIN = {
	post: { 'the current breadcrumb': [ '.salt-post__breadcrumb a[aria-current="page"]', 1 ], 'the logo\'s link': [ 'a.salt-logo', 1 ] },
	search: { 'the logo\'s link': [ 'a.salt-logo', 1 ] },
	'not-found': { 'the logo\'s link': [ 'a.salt-logo', 1 ] },
	offices: { 'the logo\'s link': [ 'a.salt-logo', 1 ] },
};

// The contract's rules that underline these links: in the served (minified)
// stylesheet, the rules whose selector names one of them. The control drops
// the underline from each and leaves the rest of the stylesheet as served.
const VIEW_LINK = /\.salt-post__(?:breadcrumb|category|byline|tags|adjacent) a|\.salt-author-box__name a|\.salt-search__results\s*>\s*li\s*>\s*a|main\s*>\s*a|\.salt-locations__contact a/;
const UNDERLINE = /text-decoration(?:-line)?:\s*underline;?/g;
const withoutRules = ( css ) => css.replace( /([^{}]+)\{([^{}]*)\}/g, ( rule, selector, body ) => ( VIEW_LINK.test( selector ) ? `${ selector }{${ body.replace( UNDERLINE, '' ) }}` : rule ) );

// The office the check seeds. The offices are one accordion (<details> sharing a
// name), so one is open at a time: the check opens this one and reads its links.
const OFFICE = 'Link underlines check office';

// Each matching link the page shows (not one in a closed <details>): its text,
// its computed line and where it is on the page.
const read = ( selector ) =>
	[ ...document.querySelectorAll( selector ) ]
		.filter( ( e ) => e.checkVisibility() )
		.map( ( e ) => {
			const r = e.getBoundingClientRect();
			return {
				text: e.textContent.trim().replace( /\s+/g, ' ' ).slice( 0, 40 ),
				line: getComputedStyle( e ).textDecorationLine,
				clip: { x: Math.max( 0, Math.floor( r.left + window.scrollX ) ), y: Math.max( 0, Math.floor( r.top + window.scrollY ) - 2 ), width: Math.ceil( r.width ) + 1, height: Math.ceil( r.height ) + 6 },
			};
		} );

const pages = JSON.parse( site( 'seed' ) );
const browser = await chromium.launch();
try {
	// One view at one width: for each kind of link, what it computes and whether
	// taking its line away (and nothing else) changes what the browser draws.
	const view = async ( name, width, { withoutRule = false } = {} ) => {
		const context = await browser.newContext( { viewport: { width, height: 900 } } );
		let rewritten = 0;
		if ( withoutRule ) {
			await context.route( '**/assets/dist/salt.*/salt.css', async ( route ) => {
				const served = await route.fetch();
				const css = await served.text();
				const body = withoutRules( css );
				rewritten += body === css ? 0 : 1;
				await route.fulfill( { response: served, body } );
			} );
		}
		const page = await context.newPage();
		const response = await page.goto( pages[ name ], { waitUntil: 'networkidle' } );
		if ( 'offices' === name ) {
			await page.locator( 'details', { hasText: OFFICE } ).evaluate( ( office ) => office.setAttribute( 'open', '' ) );
		}
		const seen = { status: response.status(), rewritten: 0, kinds: {} };
		for ( const [ group, kinds ] of [ [ 'underlined', UNDERLINED[ name ] ], [ 'plain', PLAIN[ name ] ] ] ) {
			for ( const [ what, [ selector, fewest ] ] of Object.entries( kinds ) ) {
				const links = await page.evaluate( read, selector );
				for ( const [ i, link ] of links.entries() ) {
					// The same pixels with and without the line: a drawn line changes them.
					const drawn = await page.screenshot( { clip: link.clip, fullPage: true } );
					await page.evaluate( ( [ s, n ] ) => [ ...document.querySelectorAll( s ) ].filter( ( e ) => e.checkVisibility() )[ n ].style.setProperty( 'text-decoration-line', 'none', 'important' ), [ selector, i ] );
					const bare = await page.screenshot( { clip: link.clip, fullPage: true } );
					await page.evaluate( ( [ s, n ] ) => [ ...document.querySelectorAll( s ) ].filter( ( e ) => e.checkVisibility() )[ n ].style.removeProperty( 'text-decoration-line' ), [ selector, i ] );
					link.drawn = ! drawn.equals( bare );
				}
				seen.kinds[ what ] = { group, selector, fewest, links };
			}
		}
		if ( shots && ! withoutRule ) {
			fs.mkdirSync( shots, { recursive: true } );
			await page.screenshot( { path: path.join( shots, `link-underlines-${ name }-${ width }.png` ), fullPage: true } );
		}
		await context.close();
		seen.rewritten = rewritten;
		return seen;
	};
	const told = ( links ) => links.map( ( l ) => `"${ l.text }" ${ l.line }${ l.drawn ? ', drawn' : ', not drawn' }` ).join( '; ' ) || 'no such link shown on the page';

	for ( const width of [ 320, 1280 ] ) {
		for ( const name of Object.keys( UNDERLINED ) ) {
			const seen = await view( name, width );
			const expected = 'not-found' === name ? 404 : 200;
			result( seen.status === expected, `${ width }px ${ name }: the page answers ${ expected }`, `${ pages[ name ].replace( process.env.SALT_HARNESS_URL, '' ) } answered ${ seen.status }` );
			for ( const [ what, { group, fewest, links } ] of Object.entries( seen.kinds ) ) {
				if ( 'underlined' === group ) {
					result(
						links.length >= fewest && links.every( ( l ) => l.line.includes( 'underline' ) && l.drawn ),
						`${ width }px ${ name }, ${ what }: underlined, and the browser draws the line`,
						`${ links.length } of at least ${ fewest }: ${ told( links ) }`
					);
				} else {
					result(
						links.length >= fewest && links.every( ( l ) => ! l.line.includes( 'underline' ) && ! l.drawn ),
						`${ width }px ${ name }, ${ what }: still no line`,
						`${ links.length } of at least ${ fewest }: ${ told( links ) }`
					);
				}
			}
		}
	}

	// The control: the same reading with the contract's rules for these links gone.
	for ( const name of Object.keys( UNDERLINED ) ) {
		const seen = await view( name, 1280, { withoutRule: true } );
		const kinds = Object.entries( seen.kinds ).filter( ( [ , k ] ) => 'underlined' === k.group );
		const lost = kinds.filter( ( [ , k ] ) => k.links.length >= k.fewest && k.links.every( ( l ) => ! l.line.includes( 'underline' ) && ! l.drawn ) );
		result(
			seen.rewritten === 1 && lost.length === kinds.length,
			`control, ${ name }: without the contract's rules for these links, no line on ${ kinds.map( ( [ what ] ) => what ).join( ', ' ) }`,
			`stylesheet rewritten ${ seen.rewritten } time(s); ${ kinds.map( ( [ what, k ] ) => `${ what }: ${ told( k.links ) }` ).join( ' | ' ) }`
		);
	}
} finally {
	await browser.close();
	site( 'restore' );
}
process.exit( failures ? 1 : 0 );
