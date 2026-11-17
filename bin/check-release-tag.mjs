/**
 * Release tag gate (Notion ID-3630).
 *
 * Usage: node bin/check-release-tag.mjs vX.Y.Z   (run inside a full-history checkout)
 *
 * Client sites update through Git Updater, which offers the newest tag and requires
 * the theme's `Version` header to match it. A tag that disagrees with the header, or
 * that sits on a commit outside `main`, would offer sites the wrong release. This
 * fails (exit 1, every problem listed) unless ALL hold:
 *
 *   1. The tag is a plain release, `vX.Y.Z`. RELEASE-POLICY and CONTRIBUTING define
 *      only that form; no pre-release suffix is allowed (v0.2.0-foundation predates it).
 *   2. `Version:` in style.css at the tagged commit equals the tag without the `v`.
 *   3. SALT_VERSION in functions.php at the tagged commit equals it too (they are
 *      bumped together, CONTRIBUTING).
 *   4. The tagged commit is `origin/main` or an ancestor of it.
 *
 * It reads the files from the tagged commit, not the working tree, and needs
 * `origin/main` present (CI: actions/checkout with fetch-depth: 0). A missing
 * origin/main fails closed rather than skipping.
 */

import { execFileSync } from 'node:child_process';

const tag = process.argv[2];
const errors = [];

const git = ( ...args ) =>
	execFileSync( 'git', args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } );
const gitOk = ( ...args ) => {
	try {
		git( ...args );
		return true;
	} catch {
		return false;
	}
};

if ( ! tag ) {
	console.error( 'usage: node bin/check-release-tag.mjs vX.Y.Z' );
	process.exit( 2 );
}

if ( ! /^v\d+\.\d+\.\d+$/.test( tag ) ) {
	errors.push( `Tag "${ tag }" is not a release tag of the form vX.Y.Z (no pre-release or other suffix).` );
}
const expected = tag.replace( /^v/, '' );

let commit = '';
try {
	commit = git( 'rev-parse', '--verify', '--quiet', `refs/tags/${ tag }^{commit}` ).trim();
} catch {
	errors.push( `Tag "${ tag }" does not exist in this checkout (fetch tags / use fetch-depth: 0).` );
}

if ( commit ) {
	const show = ( file ) => {
		try {
			return git( 'show', `${ commit }:${ file }` );
		} catch {
			errors.push( `${ file } not found at ${ tag } (${ commit.slice( 0, 7 ) }).` );
			return null;
		}
	};

	const style = show( 'style.css' );
	if ( style !== null ) {
		const m = style.match( /^[ \t/*#@]*Version:(.*)$/mi );
		if ( ! m ) {
			errors.push( 'style.css has no "Version:" header.' );
		} else if ( m[ 1 ].trim() !== expected ) {
			errors.push( `style.css Version is "${ m[ 1 ].trim() }" but ${ tag } requires "${ expected }".` );
		}
	}

	const fns = show( 'functions.php' );
	if ( fns !== null ) {
		const m = fns.replace( /^[ \t]*(?:\/\/|#|\*).*$/gm, '' ).match( /define\(\s*['"]SALT_VERSION['"]\s*,\s*['"]([^'"]*)['"]\s*\)/ );
		if ( ! m ) {
			errors.push( 'functions.php does not define SALT_VERSION.' );
		} else if ( m[ 1 ] !== expected ) {
			errors.push( `SALT_VERSION in functions.php is "${ m[ 1 ] }" but ${ tag } requires "${ expected }".` );
		}
	}

	if ( ! gitOk( 'rev-parse', '--verify', '--quiet', 'refs/remotes/origin/main^{commit}' ) ) {
		errors.push( 'origin/main is not available, so ancestry cannot be checked (fetch full history).' );
	} else if ( ! gitOk( 'merge-base', '--is-ancestor', commit, 'refs/remotes/origin/main' ) ) {
		errors.push( `${ tag } (${ commit.slice( 0, 7 ) }) is not on main: it is not an ancestor of origin/main.` );
	}
}

if ( errors.length ) {
	console.error( `Release tag gate FAILED for ${ tag }:` );
	for ( const e of errors ) {
		console.error( `  - ${ e }` );
	}
	process.exit( 1 );
}
console.log( `Release tag gate OK: ${ tag } (${ commit.slice( 0, 7 ) }) matches style.css and SALT_VERSION and is on main.` );
