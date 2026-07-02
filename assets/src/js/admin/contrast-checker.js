/**
 * Admin live contrast checker for the Brand & Design screen (spec §13/§14).
 *
 * The pure colour math below is a FAITHFUL port of inc/colors.php — same
 * OKLab matrices, same chroma-reduction gamut binary search, same lightness
 * ladder and chroma taper, same WCAG luminance breakpoint (0.03928), same
 * text-on candidates. Parity is enforced against the committed fixture
 * tests/fixtures/color-vectors.json by bin/check-color-parity.mjs (JS side)
 * and tests/unit/ColorVectorsTest.php (PHP side). Change one side and the
 * gate trips.
 *
 * Dual-context single file (documented decision, in place of a separate
 * color-math.js — every file in assets/src/js/admin/ becomes its own build
 * entry, so a split module would ship a dead extra bundle):
 * - Browser: classic script (esbuild IIFE); wires an advisory panel onto the
 *   ACF Brand group. It NEVER blocks saving.
 * - Node (bin/check-color-parity.mjs, via a vm sandbox that defines
 *   `module`): exports the pure math and skips all UI wiring because
 *   `window`/`acf` are absent.
 *
 * Config arrives via wp_localize_script as `saltContrast`
 * (inc/services/contrast-checker.php — the pinned app.css targets).
 */
(() => {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Pure colour math — port of inc/colors.php.                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Parse '#rgb' or '#rrggbb' into gamma-encoded sRGB channels (0..1).
	 *
	 * @param {string} hex Hex colour.
	 * @return {number[]|null} [r, g, b] or null when invalid.
	 */
	const parseHex = (hex) => {
		let h = String(hex).trim().replace(/^#/, '');
		if (/^[0-9a-f]{3}$/i.test(h)) {
			h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
		}
		if (!/^[0-9a-f]{6}$/i.test(h)) {
			return null;
		}
		return [
			parseInt(h.slice(0, 2), 16) / 255.0,
			parseInt(h.slice(2, 4), 16) / 255.0,
			parseInt(h.slice(4, 6), 16) / 255.0,
		];
	};

	/**
	 * Linearise one gamma-encoded sRGB channel (IEC 61966-2-1).
	 *
	 * @param {number} channel Gamma-encoded channel 0..1.
	 * @return {number} Linear-light channel.
	 */
	const srgbToLinear = (channel) =>
		channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);

	/**
	 * Gamma-encode one linear sRGB channel.
	 *
	 * @param {number} channel Linear-light channel.
	 * @return {number} Gamma-encoded channel.
	 */
	const linearToSrgb = (channel) =>
		channel <= 0.0031308 ? 12.92 * channel : 1.055 * Math.pow(channel, 1.0 / 2.4) - 0.055;

	/**
	 * OKLCH → linear sRGB (unclamped; may fall outside 0..1).
	 *
	 * @param {number} l Lightness 0..1.
	 * @param {number} c Chroma (>= 0).
	 * @param {number} h Hue in degrees.
	 * @return {number[]} Linear [r, g, b].
	 */
	const oklchToLinearRgb = (l, c, h) => {
		const hRad = (h * Math.PI) / 180.0;
		const a = c * Math.cos(hRad);
		const b = c * Math.sin(hRad);

		const l_ = l + 0.3963377774 * a + 0.2158037573 * b;
		const m_ = l - 0.1055613458 * a - 0.0638541728 * b;
		const s_ = l - 0.0894841775 * a - 1.291485548 * b;

		const l3 = l_ * l_ * l_;
		const m3 = m_ * m_ * m_;
		const s3 = s_ * s_ * s_;

		return [
			4.0767416621 * l3 - 3.3077115913 * m3 + 0.2309699292 * s3,
			-1.2684380046 * l3 + 2.6097574011 * m3 - 0.3413193965 * s3,
			-0.0041960863 * l3 - 0.7034186147 * m3 + 1.707614701 * s3,
		];
	};

	/**
	 * Hex → OKLCH (invalid input degrades to black, like the PHP side).
	 *
	 * @param {string} hex Hex colour.
	 * @return {{l: number, c: number, h: number}} l 0..1, h in degrees 0..360.
	 */
	const hexToOklch = (hex) => {
		const rgbRaw = parseHex(hex) || [0.0, 0.0, 0.0];

		const r = srgbToLinear(rgbRaw[0]);
		const g = srgbToLinear(rgbRaw[1]);
		const b = srgbToLinear(rgbRaw[2]);

		const l_ = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b;
		const m_ = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b;
		const s_ = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b;

		const lc = Math.pow(Math.max(0.0, l_), 1.0 / 3.0);
		const mc = Math.pow(Math.max(0.0, m_), 1.0 / 3.0);
		const sc = Math.pow(Math.max(0.0, s_), 1.0 / 3.0);

		const labL = 0.2104542553 * lc + 0.793617785 * mc - 0.0040720468 * sc;
		const labA = 1.9779984951 * lc - 2.428592205 * mc + 0.4505937099 * sc;
		const labB = 0.0259040371 * lc + 0.7827717662 * mc - 0.808675766 * sc;

		const chroma = Math.sqrt(labA * labA + labB * labB);
		let hue = ((Math.atan2(labB, labA) * 180.0) / Math.PI) % 360.0;
		if (hue < 0.0) {
			hue += 360.0;
		}

		return { l: labL, c: chroma, h: hue };
	};

	/**
	 * OKLCH → hex, gamut-mapping by chroma reduction (binary search, 20
	 * iterations, epsilon 1e-6 — identical to salt_oklch_to_hex()).
	 *
	 * @param {number} l Lightness 0..1.
	 * @param {number} c Chroma (>= 0).
	 * @param {number} h Hue in degrees.
	 * @return {string} '#rrggbb'.
	 */
	const oklchToHex = (l, c, h) => {
		c = Math.max(0.0, c);
		const eps = 0.000001;

		const inGamut = (rgb) => rgb.every((channel) => channel >= -eps && channel <= 1.0 + eps);

		let rgb = oklchToLinearRgb(l, c, h);

		if (!inGamut(rgb)) {
			let lo = 0.0;
			let hi = c;
			for (let i = 0; i < 20; i++) {
				const mid = (lo + hi) / 2.0;
				if (inGamut(oklchToLinearRgb(l, mid, h))) {
					lo = mid;
				} else {
					hi = mid;
				}
			}
			rgb = oklchToLinearRgb(l, lo, h);
		}

		return (
			'#' +
			rgb
				.map((channel) => {
					const srgb = linearToSrgb(Math.max(0.0, Math.min(1.0, channel)));
					const byte = Math.round(Math.max(0.0, Math.min(1.0, srgb)) * 255.0);
					return byte.toString(16).padStart(2, '0');
				})
				.join('')
		);
	};

	/**
	 * Derive the 11-step tonal ramp (port of salt_derive_ramp(): fixed
	 * lightness ladder, hue held, chroma taper 1.0 at 500/600 falling
	 * linearly to 0.25 at the 50/950 extremes).
	 *
	 * @param {string} baseHex Base brand colour.
	 * @return {Object<number, string>} Step (50..950) → '#rrggbb'.
	 */
	const deriveRamp = (baseHex) => {
		const base = hexToOklch(baseHex);

		const ladder = [
			[50, 0.97],
			[100, 0.94],
			[200, 0.88],
			[300, 0.8],
			[400, 0.7],
			[500, 0.62],
			[600, 0.53],
			[700, 0.45],
			[800, 0.37],
			[900, 0.28],
			[950, 0.2],
		];

		const ramp = {};
		for (const [step, lightness] of ladder) {
			let taper;
			if (step <= 500) {
				taper = 0.25 + (0.75 * (step - 50)) / (500 - 50);
			} else if (step >= 600) {
				taper = 0.25 + (0.75 * (950 - step)) / (950 - 600);
			} else {
				taper = 1.0;
			}
			ramp[step] = oklchToHex(lightness, base.c * taper, base.h);
		}

		return ramp;
	};

	/**
	 * WCAG 2.x relative luminance (0.03928/12.92 breakpoint — the WCAG
	 * definition, matching salt_relative_luminance()).
	 *
	 * @param {string} hex Hex colour.
	 * @return {number} Relative luminance 0..1.
	 */
	const relativeLuminance = (hex) => {
		const rgb = parseHex(hex) || [0.0, 0.0, 0.0];
		const linear = rgb.map((channel) =>
			channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4)
		);
		return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
	};

	/**
	 * WCAG contrast ratio between two hex colours (1..21).
	 *
	 * @param {string} hexA First colour.
	 * @param {string} hexB Second colour.
	 * @return {number} (L1 + 0.05) / (L2 + 0.05), lighter on top.
	 */
	const contrastRatio = (hexA, hexB) => {
		const lumA = relativeLuminance(hexA);
		const lumB = relativeLuminance(hexB);
		return (Math.max(lumA, lumB) + 0.05) / (Math.min(lumA, lumB) + 0.05);
	};

	/**
	 * AA-safe text colour for a background: near-white/near-black candidates
	 * first, then whichever of #fff/#000 contrasts more (salt_text_on()).
	 *
	 * @param {string} backgroundHex Background colour.
	 * @return {string} Text colour hex passing 4.5:1.
	 */
	const textOn = (backgroundHex) => {
		for (const candidate of ['#f8fafc', '#0f172a']) {
			if (contrastRatio(candidate, backgroundHex) >= 4.5) {
				return candidate;
			}
		}
		const white = contrastRatio('#ffffff', backgroundHex);
		const black = contrastRatio('#000000', backgroundHex);
		return white >= black ? '#ffffff' : '#000000';
	};

	/* ------------------------------------------------------------------ */
	/* Node export seam (parity gate) — no UI beyond this point runs there. */
	/* ------------------------------------------------------------------ */

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			parseHex,
			hexToOklch,
			oklchToHex,
			deriveRamp,
			relativeLuminance,
			contrastRatio,
			textOn,
		};
	}

	if (typeof window === 'undefined' || !window.acf || !window.saltContrast) {
		return;
	}

	/* ------------------------------------------------------------------ */
	/* Admin UI wiring (browser only).                                     */
	/* ------------------------------------------------------------------ */

	const config = window.saltContrast;
	const i18n = config.i18n || {};
	const PANEL_ID = 'salt-contrast-panel';
	const FIELD_KEYS = Object.values(config.fields || {});

	/**
	 * Current value of an ACF field by key ('' when absent/unset).
	 *
	 * @param {string} key ACF field key.
	 * @return {string} Field value.
	 */
	const fieldValue = (key) => {
		const el = document.querySelector(`.acf-field[data-key="${key}"] input[type="text"]`);
		return el && typeof el.value === 'string' ? el.value.trim() : '';
	};

	/**
	 * Build the check list for a derived primary ramp — exactly the pinned
	 * pairs from saltContrast (see salt_contrast_targets()).
	 *
	 * @param {Object<number, string>} ramp Derived primary ramp.
	 * @return {Array<{theme: string, label: string, a: string, b: string, min: number}>} Checks.
	 */
	const buildChecks = (ramp) => {
		const checks = [];
		const t = config.thresholds || { text: 4.5, ui: 3.0 };

		for (const step of config.light.steps) {
			const bg = ramp[step];
			checks.push({
				theme: i18n.light,
				label: `${i18n.textOn} ${textOn(bg)} ${i18n.onWord} ${i18n.brand} ${step} (${bg})`,
				a: textOn(bg),
				b: bg,
				min: t.text,
			});
		}
		for (const surface of config.light.surfaces) {
			checks.push({
				theme: i18n.light,
				label: `${i18n.textOn} ${config.light.text} ${i18n.onWord} ${surface}`,
				a: config.light.text,
				b: surface,
				min: t.text,
			});
			checks.push({
				theme: i18n.light,
				label: `${i18n.border} ${config.border} ${i18n.onWord} ${surface}`,
				a: config.border,
				b: surface,
				min: t.ui,
			});
		}
		for (const step of config.dark.steps) {
			for (const surface of config.dark.surfaces) {
				checks.push({
					theme: i18n.dark,
					label: `${i18n.brand} ${step} (${ramp[step]}) ${i18n.onWord} ${surface}`,
					a: ramp[step],
					b: surface,
					min: t.ui,
				});
			}
		}
		for (const surface of config.dark.surfaces) {
			checks.push({
				theme: i18n.dark,
				label: `${i18n.textOn} ${config.dark.text} ${i18n.onWord} ${surface}`,
				a: config.dark.text,
				b: surface,
				min: t.text,
			});
		}

		return checks;
	};

	/**
	 * Locate (or create) the panel container under the Brand group.
	 *
	 * @return {HTMLElement|null} The panel element.
	 */
	const panelEl = () => {
		let panel = document.getElementById(PANEL_ID);
		if (panel) {
			return panel;
		}

		const host =
			document.querySelector('#acf-group_salt_options_brand .inside') ||
			(document.querySelector(`.acf-field[data-key="${FIELD_KEYS[0]}"]`) || {}).parentElement;
		if (!host) {
			return null;
		}

		panel = document.createElement('div');
		panel.id = PANEL_ID;
		panel.className = 'salt-contrast-panel notice notice-info inline';
		host.appendChild(panel);
		return panel;
	};

	/**
	 * Render the advisory pass/fail panel (soft warnings only — never
	 * blocks save, never touches field validation).
	 *
	 * @return {void}
	 */
	const render = () => {
		const panel = panelEl();
		if (!panel) {
			return;
		}

		const primary = fieldValue(config.fields.primary) || config.fallback;
		const ramp = deriveRamp(primary);
		const checks = buildChecks(ramp);

		panel.textContent = '';

		const heading = document.createElement('p');
		const strong = document.createElement('strong');
		strong.textContent = i18n.heading;
		heading.appendChild(strong);
		panel.appendChild(heading);

		const intro = document.createElement('p');
		intro.className = 'description';
		intro.textContent = i18n.intro;
		panel.appendChild(intro);

		let currentTheme = null;
		let list = null;
		for (const check of checks) {
			if (check.theme !== currentTheme) {
				currentTheme = check.theme;
				const themeHeading = document.createElement('p');
				themeHeading.className = 'salt-contrast-theme';
				themeHeading.textContent = currentTheme;
				panel.appendChild(themeHeading);
				list = document.createElement('ul');
				list.className = 'salt-contrast-list ul-disc';
				panel.appendChild(list);
			}

			const ratio = contrastRatio(check.a, check.b);
			const pass = ratio >= check.min;

			const item = document.createElement('li');
			item.className = pass ? 'salt-contrast-pass' : 'salt-contrast-fail';

			const chip = document.createElement('span');
			chip.className = pass
				? 'salt-contrast-chip dashicons-before dashicons-yes'
				: 'salt-contrast-chip dashicons-before dashicons-warning';
			chip.textContent = pass ? i18n.pass : i18n.fail;
			item.appendChild(chip);

			const needs = String(i18n.needs || 'needs %s:1').replace('%s', String(check.min));
			item.appendChild(
				document.createTextNode(` ${check.label} — ${ratio.toFixed(2)}:1 (${needs})`)
			);
			list.appendChild(item);
		}
	};

	let timer = null;

	/**
	 * Debounced re-render (~150ms) for change/input storms from the colour
	 * picker.
	 *
	 * @return {void}
	 */
	const scheduleRender = () => {
		clearTimeout(timer);
		timer = setTimeout(render, 150);
	};

	/**
	 * Initial render + change wiring (idempotent across ready/append).
	 *
	 * @return {void}
	 */
	const init = () => {
		if (!document.querySelector(`.acf-field[data-key="${config.fields.primary}"]`)) {
			return;
		}
		render();
	};

	window.acf.addAction('ready', init);
	window.acf.addAction('append', init);

	const $ = window.jQuery;
	if ($) {
		const selector = FIELD_KEYS.map((key) => `.acf-field[data-key="${key}"] input`).join(', ');
		$(document).on('input change', selector, scheduleRender);
	}
})();
