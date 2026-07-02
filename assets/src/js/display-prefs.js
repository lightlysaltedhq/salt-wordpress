/**
 * Display preferences widget wiring (spec §14; A11Y05/06; FAIL02).
 *
 * The x-display-preferences component is server-rendered [hidden]; this
 * module reveals it (no JS = no dead controls), seeds the buttons from the
 * <html> data-* attributes the pre-paint init script already applied
 * (salt_theme_init_js in inc/setup.php), and persists changes to the SAME
 * localStorage enum contract that script reads:
 *
 *   salt-scale     "-1" | "1" | "2"   (0 = default: key removed)
 *   salt-contrast  "high"             (any other value = explicit off)
 *   salt-grayscale "1"
 *   salt-theme     "dark" | "light"   (absent = follow OS preference)
 *   salt-motion    "reduce"           ("auto" = explicit off when the site
 *                                      defaults to reduced motion)
 *
 * Every change dispatches document CustomEvent('salt:prefs-changed',
 * {detail: {pref, value}}).
 */
(() => {
	'use strict';

	const SCALE_STEPS = [-1, 0, 1, 2];

	/**
	 * localStorage helpers — private-mode/quota failures must never break
	 * the controls (the attribute still applies for this page view).
	 *
	 * @param {string}      key   Storage key.
	 * @param {string|null} value Value, or null to remove the key.
	 */
	const store = (key, value) => {
		try {
			if (value === null) {
				localStorage.removeItem(key);
			} else {
				localStorage.setItem(key, value);
			}
		} catch {
			/* Storage unavailable — session-only preferences. */
		}
	};

	const init = () => {
		const widget = document.querySelector('[data-salt-display-prefs]');
		if (!widget) {
			return;
		}

		const root = document.documentElement;

		/**
		 * Announce a preference change.
		 *
		 * @param {string}         pref  Preference name.
		 * @param {number|boolean} value New value.
		 */
		const announce = (pref, value) => {
			document.dispatchEvent(new CustomEvent('salt:prefs-changed', { detail: { pref, value } }));
		};

		// -- Font size (stepped enum -1|0|1|2; 0 = default) -----------------

		const currentScale = () => {
			const value = parseInt(root.getAttribute('data-scale') || '0', 10);
			return SCALE_STEPS.includes(value) ? value : 0;
		};

		/**
		 * Apply a scale step (already clamped to the enum).
		 *
		 * @param {number} value Scale step.
		 */
		const applyScale = (value) => {
			if (value === 0) {
				root.removeAttribute('data-scale');
				store('salt-scale', null);
			} else {
				root.setAttribute('data-scale', String(value));
				store('salt-scale', String(value));
			}
			announce('scale', value);
		};

		const stepScale = (action) => {
			if (action === 'reset') {
				applyScale(0);
				return;
			}
			const index = SCALE_STEPS.indexOf(currentScale());
			const next = action === 'increase' ? index + 1 : index - 1;
			applyScale(SCALE_STEPS[Math.max(0, Math.min(SCALE_STEPS.length - 1, next))]);
		};

		// -- Toggles ---------------------------------------------------------
		// offValue: what to PERSIST when turning off — null removes the key
		// (pure default), a sentinel records an explicit opt-out where the
		// no-key default would re-enable the preference on the next load.

		const TOGGLES = {
			contrast: {
				attr: 'data-contrast',
				on: 'high',
				key: 'salt-contrast',
				stored: 'high',
				offValue: () => (window.matchMedia('(prefers-contrast: more)').matches ? 'auto' : null),
			},
			grayscale: {
				attr: 'data-grayscale',
				on: '',
				key: 'salt-grayscale',
				stored: '1',
				offValue: () => null,
			},
			theme: {
				attr: 'data-theme',
				on: 'dark',
				key: 'salt-theme',
				stored: 'dark',
				offValue: () => (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'light' : null),
			},
			motion: {
				attr: 'data-motion',
				on: 'reduce',
				key: 'salt-motion',
				stored: 'reduce',
				offValue: () => (widget.dataset.motionDefault === 'reduce' ? 'auto' : null),
			},
		};

		const isOn = (toggle) =>
			toggle.on === '' ? root.hasAttribute(toggle.attr) : root.getAttribute(toggle.attr) === toggle.on;

		/**
		 * Flip a toggle preference and sync its button.
		 *
		 * @param {string}            pref   Preference name (TOGGLES key).
		 * @param {HTMLButtonElement} button The pressed button.
		 */
		const flip = (pref, button) => {
			const toggle = TOGGLES[pref];
			const next = !isOn(toggle);

			if (next) {
				root.setAttribute(toggle.attr, toggle.on);
				store(toggle.key, toggle.stored);
			} else {
				root.removeAttribute(toggle.attr);
				store(toggle.key, toggle.offValue());
			}

			button.setAttribute('aria-pressed', String(next));
			announce(pref, next);
		};

		// -- Reveal + seed initial state (FAIL02) ----------------------------

		widget.removeAttribute('hidden');

		widget.querySelectorAll('button[data-salt-pref][aria-pressed]').forEach((button) => {
			const toggle = TOGGLES[button.dataset.saltPref];
			if (toggle) {
				button.setAttribute('aria-pressed', String(isOn(toggle)));
			}
		});

		widget.addEventListener('click', (event) => {
			const button = event.target.closest('button[data-salt-pref]');
			if (!button || !widget.contains(button)) {
				return;
			}

			const pref = button.dataset.saltPref;
			if (pref === 'scale') {
				stepScale(button.dataset.saltAction);
			} else if (TOGGLES[pref]) {
				flip(pref, button);
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
