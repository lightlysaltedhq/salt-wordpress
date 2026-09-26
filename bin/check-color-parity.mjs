/**
 * PHP↔JS colour-math parity gate (spec §13).
 *
 * tests/fixtures/color-vectors.json is generated ONCE from inc/colors.php
 * (the source of truth) and committed. tests/unit/ColorVectorsTest.php holds
 * the PHP side to the fixture; this script holds the JS port
 * (assets/src/js/admin/contrast-checker.js) to the same fixture, and to the
 * contract's quantise vectors (design-foundations vectors.json#quantise),
 * which it reads from node_modules. Exit 0 on full parity, 1 with a
 * per-vector report otherwise.
 *
 * The checker is a classic browser script with a CommonJS-style export seam
 * (`module.exports` guarded by typeof), so it is evaluated in a vm sandbox
 * that provides `module` — the package is "type": "module", which rules out
 * createRequire on a .js file.
 */

import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const source = fs.readFileSync(
	path.join(root, 'assets/src/js/admin/contrast-checker.js'),
	'utf8'
);
const sandbox = { module: { exports: {} } };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'contrast-checker.js' });
const math = sandbox.module.exports;

for (const fn of [ 'hexToOklch', 'oklchToHex', 'deriveRamp', 'relativeLuminance', 'contrastRatio', 'textOn', 'roundHalfEven', 'quantise' ]) {
	if (typeof math[fn] !== 'function') {
		console.error(`check-color-parity: contrast-checker.js did not export ${fn}().`);
		process.exit(1);
	}
}

const vectors = JSON.parse(
	fs.readFileSync(path.join(root, 'tests/fixtures/color-vectors.json'), 'utf8')
);

// Numeric fields are rounded in the fixture (4dp oklch, 6dp luminance,
// 3dp contrast); allow half-ulp-of-rounding tolerance rather than
// re-implementing PHP's round() semantics. Hex strings must match exactly.
const TOL = { oklch: 0.00005, luminance: 0.0000005, contrast: 0.0005 };

let failures = 0;
const fail = (input, field, expected, actual) => {
	failures++;
	console.error(`FAIL ${input} ${field}: fixture=${expected} js=${actual}`);
};

for (const vector of vectors) {
	const { input } = vector;

	const oklch = math.hexToOklch(input);
	for (const key of [ 'l', 'c', 'h' ]) {
		if (Math.abs(oklch[key] - vector.oklch[key]) > TOL.oklch) {
			fail(input, `oklch.${key}`, vector.oklch[key], oklch[key]);
		}
	}

	const ramp = math.deriveRamp(input);
	for (const [ step, hex ] of Object.entries(vector.ramp)) {
		if (ramp[step] !== hex) {
			fail(input, `ramp.${step}`, hex, ramp[step]);
		}
	}

	const luminance = math.relativeLuminance(input);
	if (Math.abs(luminance - vector.luminance) > TOL.luminance) {
		fail(input, 'luminance', vector.luminance, luminance);
	}

	const textOn = math.textOn(input);
	if (textOn !== vector.textOn) {
		fail(input, 'textOn', vector.textOn, textOn);
	}

	const contrastWhite = math.contrastRatio(input, '#ffffff');
	if (Math.abs(contrastWhite - vector.contrastWhite) > TOL.contrast) {
		fail(input, 'contrastWhite', vector.contrastWhite, contrastWhite);
	}
}

// The contract's quantise vectors (design-foundations vectors.json#quantise):
// linear channels whose scaled value is exactly a tie, or one ulp off it. No
// hex seed reaches one, so the fixture above cannot tell half to even from
// Math.round(); these can. Read through the package's exports map, as the PHP
// conformance suite does, and fail closed when the package is absent: this
// gate cannot claim the JS rounds as the contract does without reading it.
const contractDir = path.join(root, 'node_modules/@lightlysaltedhq/design-foundations');
let quantiseVectors;
try {
	const manifest = JSON.parse(fs.readFileSync(path.join(contractDir, 'package.json'), 'utf8'));
	const target = manifest.exports?.['./vectors'];
	if (typeof target !== 'string') {
		throw new Error(`its exports map has no plain './vectors' entry (${JSON.stringify(target)})`);
	}
	quantiseVectors = JSON.parse(fs.readFileSync(path.join(contractDir, target), 'utf8')).quantise;
} catch (error) {
	console.error(`check-color-parity: cannot read the contract's vectors from ${contractDir}: ${error.message}. Fix: npm ci`);
	process.exit(1);
}
if (!Array.isArray(quantiseVectors) || quantiseVectors.length === 0) {
	console.error('check-color-parity: vectors.json has no quantise vectors, so nothing holds the JS rounding to the contract.');
	process.exit(1);
}

for (const { id, linear, hex } of quantiseVectors) {
	const got = math.quantise(linear);
	if (got !== hex.toLowerCase()) {
		fail(`quantise ${id}`, 'hex', hex.toLowerCase(), got);
	}
}

if (failures > 0) {
	console.error(`check-color-parity: ${failures} mismatch(es) across ${vectors.length} vectors and ${quantiseVectors.length} quantise vectors.`);
	process.exit(1);
}

console.log(`check-color-parity: ${vectors.length} vectors, full PHP↔JS parity; ${quantiseVectors.length} contract quantise vectors pass.`);
