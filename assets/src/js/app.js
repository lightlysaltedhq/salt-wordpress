/**
 * Salt core JS entry: the chrome's progressive enhancement
 * (salt-contract markup/site-header.json, site-footer.json).
 *
 * Without script the server's markup stands alone: links, submenus shown on
 * hover and focus-within, the head's noscript style showing the full nav in
 * place of the burger, the theme toggle's empty span and an aria-disabled
 * consent control. With script:
 *
 * - submenu disclosure: each span toggle becomes a button that opens its
 *   submenu (aria-expanded, data-open; Escape and a click outside close it);
 * - the burger opens a modal drawer dialog holding a copy of the nav (ids
 *   site-header__drawer-*), the phone link and the call to action;
 * - the theme toggle becomes its button form with the scheme's glyph and
 *   flips the same salt-theme preference the pre-paint script reads;
 * - a sticky-focus guard and the measured header height (--header-height);
 * - the consent control, drawn only when an integration needs consent and a
 *   cookie plugin is wired to the WP Consent API (SC-030), opens the
 *   preferences dialog with the server form's boxes: one per WP Consent API
 *   category in use (statistics, marketing). Save writes each box's
 *   category through the plugin's wp_set_consent(), and statistics-anonymous
 *   with the statistics box's verdict; the theme stores nothing itself.
 *   Cancel closes without writing.
 *
 * The words and glyphs come from the inert JSON in #salt-chrome-data
 * (salt_chrome_script_data()).
 */

const data = (() => {
	try {
		return JSON.parse(document.getElementById('salt-chrome-data')?.textContent || '{}');
	} catch {
		return {};
	}
})();
const labels = data.labels || {};

/**
 * Whether a cookie plugin manages consent through the WP Consent API: it
 * has set a consent type. Without one the API answers true for every
 * category, which must never read as a grant.
 */
const consentManaged = () =>
	typeof window.wp_has_consent === 'function' &&
	Boolean(window.wp_consent_type !== undefined ? window.wp_consent_type : window.wp_fallback_consent_type);

/** Build an element from trusted, theme-made markup. */
const fromHtml = (html) => {
	const template = document.createElement('template');
	template.innerHTML = html.trim();
	return template.content.firstElementChild;
};

// -- Submenu disclosure ------------------------------------------------------

/**
 * Mount the disclosure on one nav: span toggles become buttons.
 *
 * @param {HTMLElement} nav A .salt-nav.
 */
const mountNav = (nav) => {
	nav.querySelectorAll('.salt-nav__item').forEach((item) => {
		const span = item.querySelector(':scope > .salt-nav__toggle');
		if (!span) {
			return;
		}
		const button = document.createElement('button');
		button.className = 'salt-nav__toggle';
		button.type = 'button';
		for (const name of ['aria-expanded', 'aria-controls', 'aria-label']) {
			button.setAttribute(name, span.getAttribute(name) || '');
		}
		button.append(...span.childNodes);
		span.replaceWith(button);
		item.setAttribute('data-enhanced', '');

		const setOpen = (open) => {
			button.setAttribute('aria-expanded', String(open));
			item.toggleAttribute('data-open', open);
		};
		button.addEventListener('click', () => {
			const open = button.getAttribute('aria-expanded') !== 'true';
			nav.querySelectorAll('.salt-nav__item[data-open]').forEach((other) => {
				if (other !== item) {
					other.removeAttribute('data-open');
					other.querySelector(':scope > .salt-nav__toggle')?.setAttribute('aria-expanded', 'false');
				}
			});
			setOpen(open);
		});
		// A label with no address opens its submenu too.
		const label = item.querySelector(':scope > span.salt-nav__link');
		label?.addEventListener('click', () => button.click());
		label?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				button.click();
			}
		});
		item.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && item.hasAttribute('data-open')) {
				// Only the submenu closes: not the drawer dialog around it.
				event.preventDefault();
				event.stopPropagation();
				setOpen(false);
				button.focus();
			}
		});
		document.addEventListener('click', (event) => {
			if (item.hasAttribute('data-open') && !item.contains(event.target)) {
				setOpen(false);
			}
		});
	});
};

// -- Theme toggle ------------------------------------------------------------

const root = document.documentElement;
const isDark = () => root.getAttribute('data-theme') === 'dark';

/**
 * Mount a theme toggle: the server's empty span becomes the button form;
 * a button form the server drew (it knew the scheme) is wired as it is.
 *
 * @param {HTMLElement} toggle A .salt-theme-toggle.
 */
const mountThemeToggle = (toggle) => {
	if (!data.glyphs) {
		return;
	}
	let button = toggle;
	if (toggle.tagName !== 'BUTTON') {
		if (!labels.theme) {
			return;
		}
		button = document.createElement('button');
		button.className = 'salt-theme-toggle';
		button.type = 'button';
		button.setAttribute('aria-label', labels.theme);
	}
	const sync = () => {
		const dark = isDark();
		button.setAttribute('aria-pressed', String(dark));
		button.replaceChildren(fromHtml(dark ? data.glyphs.moon : data.glyphs.sun));
	};
	button.addEventListener('click', () => {
		const dark = !isDark();
		if (dark) {
			root.setAttribute('data-theme', 'dark');
		} else {
			root.removeAttribute('data-theme');
		}
		try {
			// The init script's enum: an explicit light only where the system is dark.
			if (dark) {
				localStorage.setItem('salt-theme', 'dark');
			} else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
				localStorage.setItem('salt-theme', 'light');
			} else {
				localStorage.removeItem('salt-theme');
			}
		} catch {
			/* Storage unavailable: this page view only. */
		}
		document.dispatchEvent(new CustomEvent('salt:prefs-changed', { detail: { pref: 'theme', value: dark } }));
	});
	document.addEventListener('salt:prefs-changed', (event) => {
		if (event.detail?.pref === 'theme') {
			sync();
		}
	});
	sync();
	if (button !== toggle) {
		toggle.replaceWith(button);
	}
};

// -- Drawer ------------------------------------------------------------------

/**
 * Mount the burger: it opens a modal drawer dialog, drawn only while open.
 *
 * @param {HTMLElement} header The .salt-header.
 */
const mountDrawer = (header) => {
	const burger = header.querySelector('.salt-header__burger');
	if (!burger) {
		return;
	}
	let dialog = null;

	const close = () => {
		if (!dialog) {
			return;
		}
		dialog.close();
		dialog.remove();
		dialog = null;
		burger.setAttribute('aria-expanded', 'false');
		burger.setAttribute('data-morph', 'closing');
		const done = () => burger.removeAttribute('data-morph');
		burger.addEventListener('animationend', done, { once: true });
		setTimeout(done, 400);
		burger.focus();
	};

	const open = () => {
		dialog = document.createElement('dialog');
		dialog.className = 'salt-drawer';
		dialog.setAttribute('aria-label', burger.getAttribute('aria-label') || '');
		dialog.tabIndex = -1;

		const closeButton = document.createElement('button');
		closeButton.className = 'salt-drawer__close';
		closeButton.type = 'button';
		closeButton.setAttribute('aria-label', labels.closeMenu || '');
		closeButton.append(burger.querySelector('.salt-menu-morph').cloneNode(true));
		closeButton.addEventListener('click', close);

		const panel = document.createElement('div');
		panel.className = 'salt-drawer__panel';
		const nav = header.querySelector('#site-navigation');
		if (nav) {
			const copy = nav.cloneNode(true);
			copy.id = 'site-header__drawer-navigation';
			copy.querySelectorAll('.salt-nav__item').forEach((item) => {
				item.removeAttribute('data-enhanced');
				item.removeAttribute('data-open');
			});
			copy.querySelectorAll('[id^="site-header__submenu-"]').forEach((list) => {
				list.id = list.id.replace('site-header__submenu-', 'site-header__drawer-submenu-');
			});
			copy.querySelectorAll('[aria-controls^="site-header__submenu-"]').forEach((toggle) => {
				const span = document.createElement('span');
				span.className = 'salt-nav__toggle';
				span.setAttribute('aria-expanded', 'false');
				span.setAttribute('aria-controls', toggle.getAttribute('aria-controls').replace('site-header__submenu-', 'site-header__drawer-submenu-'));
				span.setAttribute('aria-label', toggle.getAttribute('aria-label') || '');
				span.setAttribute('aria-hidden', 'true');
				span.append(...toggle.childNodes);
				toggle.replaceWith(span);
			});
			panel.append(copy);
			mountNav(copy);
		}
		for (const selector of ['.salt-header__phone', '.salt-header__cta']) {
			const node = header.querySelector(`.salt-header__inner > ${selector}`);
			if (node) {
				panel.append(node.cloneNode(true));
			}
		}

		// The close button sits on the burger's box, so the two read as one control.
		const box = burger.getBoundingClientRect();
		dialog.style.setProperty('--salt-drawer-toggle-block-start', `${box.top}px`);
		dialog.style.setProperty('--salt-drawer-toggle-inline-start', `${box.left}px`);
		dialog.style.setProperty('--salt-drawer-toggle-inline-end', 'auto');
		dialog.style.setProperty('--salt-drawer-toggle-inline-size', `${box.width}px`);
		dialog.style.setProperty('--salt-drawer-toggle-block-size', `${box.height}px`);

		dialog.append(closeButton, panel);
		dialog.addEventListener('cancel', (event) => {
			event.preventDefault();
			close();
		});
		dialog.addEventListener('click', (event) => {
			if (event.target === dialog) {
				close();
			}
		});
		burger.after(dialog);
		burger.setAttribute('aria-expanded', 'true');
		burger.removeAttribute('data-morph');
		dialog.showModal();
		closeButton.focus();
	};

	burger.addEventListener('click', () => (dialog ? close() : open()));
	window.matchMedia('(min-width: 48.01rem)').addEventListener('change', (event) => {
		if (event.matches) {
			close();
		}
	});
};

// -- Header height and the sticky-focus guard --------------------------------

/**
 * @param {HTMLElement} header The .salt-header.
 */
const mountMeasure = (header) => {
	const measure = () => root.style.setProperty('--header-height', `${header.offsetHeight}px`);
	measure();
	if ('ResizeObserver' in window) {
		new ResizeObserver(measure).observe(header);
	}
	if (!header.hasAttribute('data-sticky')) {
		return;
	}
	// Keep a focused element from sitting under the sticky header.
	document.addEventListener('focusin', (event) => {
		const target = event.target;
		if (!(target instanceof Element) || header.contains(target) || target.closest('dialog')) {
			return;
		}
		const bottom = header.getBoundingClientRect().bottom;
		const top = target.getBoundingClientRect().top;
		if (top < bottom) {
			window.scrollBy({ top: top - bottom - 8 });
		}
	});
};

// -- Consent preferences -----------------------------------------------------

/**
 * Hydrate the consent control: it opens the preferences dialog.
 *
 * @param {HTMLElement} wrap The .salt-footer__consent.
 */
const mountConsent = (wrap) => {
	const control = wrap.querySelector('[data-salt-consent-preferences]');
	const status = wrap.querySelector('.salt-sr-only[role="status"]');
	const consent = data.consent;
	// Nothing to write through: the control stays as the server drew it.
	if (!control || !consent || typeof window.wp_set_consent !== 'function') {
		return;
	}
	control.removeAttribute('aria-disabled');
	// The script's dialog is the only one a visitor uses: a panel the server
	// drew goes before the control can open another, so no id is ever on the
	// page twice. Both draw the same boxes with the same ids.
	wrap.querySelectorAll('.salt-consent-panel').forEach((panel) => panel.remove());

	/** Whether the API records this category as allowed; never throws. */
	const allowed = (api) => {
		try {
			return consentManaged() && window.wp_has_consent(api) === true;
		} catch {
			return false;
		}
	};

	const text = (tag, className, value) => {
		const node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		node.textContent = value;
		return node;
	};

	control.addEventListener('click', () => {
		if (wrap.querySelector('.salt-consent-panel')) {
			return;
		}
		const dialog = document.createElement('dialog');
		dialog.className = 'salt-consent-panel';
		dialog.setAttribute('aria-labelledby', 'site-footer__consent-title');
		const form = document.createElement('form');
		form.method = 'dialog';
		const title = text('h2', 'salt-consent-panel__title', consent.labels.title || '');
		title.id = 'site-footer__consent-title';
		form.append(title);

		for (const category of consent.categories) {
			const id = `site-footer__consent-${category.id}`;
			const row = document.createElement('div');
			row.className = 'salt-consent-panel__category';
			const box = document.createElement('input');
			box.className = 'salt-consent-panel__checkbox';
			box.type = 'checkbox';
			box.id = id;
			box.name = category.id;
			box.setAttribute('aria-describedby', `${id}-description`);
			box.checked = allowed(category.id);
			const label = text('label', '', category.label);
			label.htmlFor = id;
			const description = text('div', 'salt-consent-panel__description', category.description);
			description.id = `${id}-description`;
			row.append(box, label, description);
			form.append(row);
		}

		const actions = document.createElement('div');
		actions.className = 'salt-consent-panel__actions';
		const save = text('button', 'salt-button', consent.labels.save || '');
		save.type = 'submit';
		save.dataset.style = 'primary';
		const cancel = text('button', 'salt-button', consent.labels.cancel || '');
		cancel.type = 'button';
		cancel.dataset.style = 'secondary';
		actions.append(save, cancel);
		form.append(actions);
		dialog.append(form);

		const close = () => {
			dialog.close();
			dialog.remove();
			control.focus();
		};
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			// Each box is one WP Consent API category; ticking it grants every
			// Salt category it covers (SC-030). The API keeps anonymous
			// statistics apart, and the statistics box answers both.
			const verdicts = {};
			for (const category of consent.categories) {
				verdicts[category.id] = form.elements.namedItem(category.id)?.checked === true;
			}
			if ('statistics' in verdicts) {
				verdicts['statistics-anonymous'] = verdicts.statistics;
			}
			for (const [api, verdict] of Object.entries(verdicts)) {
				window.wp_set_consent(api, verdict ? 'allow' : 'deny');
			}
			if (status) {
				status.textContent = consent.labels.saved || '';
			}
			close();
		});
		cancel.addEventListener('click', close);
		dialog.addEventListener('cancel', (event) => {
			event.preventDefault();
			close();
		});

		if (status) {
			status.textContent = '';
		}
		(status || control).after(dialog);
		dialog.showModal();
	});
};

// -- Mount -------------------------------------------------------------------

const init = () => {
	const header = document.querySelector('.salt-header');
	if (header) {
		const nav = header.querySelector('#site-navigation');
		if (nav) {
			mountNav(nav);
		}
		mountDrawer(header);
		mountMeasure(header);
	}
	document.querySelectorAll('.salt-theme-toggle').forEach(mountThemeToggle);
	document.querySelectorAll('.salt-footer__consent').forEach(mountConsent);
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
