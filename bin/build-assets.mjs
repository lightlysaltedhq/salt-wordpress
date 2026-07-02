/**
 * Salt asset build — runs ONCE in core CI (spec §7); client sites never build.
 *
 * Outputs minified, content-hashed CSS/JS into assets/dist/ plus a
 * manifest.json mapping logical names → hashed filenames (ARCH03).
 * The build must be deterministic: the same commit rebuilt anywhere must
 * produce byte-identical output, because CI byte-compares the committed
 * dist against a fresh rebuild (FLEET08).
 */

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const dist = path.join(root, 'assets', 'dist');
const tmp = fs.mkdtempSync(path.join(root, 'node_modules', '.salt-build-'));

const bin = (name) => path.join(root, 'node_modules', '.bin', name);

function hashedName(logical, contents) {
	const hash = createHash('sha256').update(contents).digest('hex').slice(0, 10);
	const { name, ext } = path.parse(logical);
	return `${name}.${hash}${ext}`;
}

try {
	// 1. Tailwind CSS (minified).
	execFileSync(
		bin('tailwindcss'),
		[ '--input', 'assets/src/css/app.css', '--output', path.join(tmp, 'app.css'), '--minify' ],
		{ cwd: root, stdio: 'inherit' }
	);

	// 2. JS via esbuild: the core entry plus one bundle per section module
	// (assets/src/js/sections/*.js — enqueued only when the section is on
	// the page; see salt_enqueue_section_assets()).
	const sectionEntries = fs.existsSync(path.join(root, 'assets/src/js/sections'))
		? fs.readdirSync(path.join(root, 'assets/src/js/sections')).filter((f) => f.endsWith('.js')).sort()
		: [];

	execFileSync(
		bin('esbuild'),
		[
			'assets/src/js/app.js',
			'--bundle',
			'--minify',
			'--format=iife',
			'--target=es2020',
			`--outfile=${path.join(tmp, 'app.js')}`,
		],
		{ cwd: root, stdio: 'inherit' }
	);

	fs.mkdirSync(path.join(tmp, 'sections'), { recursive: true });
	for (const entry of sectionEntries) {
		execFileSync(
			bin('esbuild'),
			[
				`assets/src/js/sections/${entry}`,
				'--bundle',
				'--minify',
				'--format=iife',
				'--target=es2020',
				`--outfile=${path.join(tmp, 'sections', entry)}`,
			],
			{ cwd: root, stdio: 'inherit' }
		);
	}

	// 3. Content-hash into a clean dist/ and write the manifest.
	fs.rmSync(dist, { recursive: true, force: true });
	fs.mkdirSync(path.join(dist, 'sections'), { recursive: true });

	const manifest = {};
	for (const logical of [ 'app.css', 'app.js', ...sectionEntries.map((e) => `sections/${e}`) ]) {
		const contents = fs.readFileSync(path.join(tmp, logical));
		const outName = path.posix.join(path.posix.dirname(logical) === '.' ? '' : path.posix.dirname(logical), hashedName(path.posix.basename(logical), contents));
		fs.writeFileSync(path.join(dist, outName), contents);
		manifest[logical] = outName;
	}

	// Stable key order + trailing newline so the file is byte-reproducible.
	const ordered = Object.fromEntries(Object.entries(manifest).sort(([ a ], [ b ]) => a.localeCompare(b)));
	fs.writeFileSync(path.join(dist, 'manifest.json'), `${JSON.stringify(ordered, null, '\t')}\n`);

	console.log('Built assets/dist:', ordered);
} finally {
	fs.rmSync(tmp, { recursive: true, force: true });
}
