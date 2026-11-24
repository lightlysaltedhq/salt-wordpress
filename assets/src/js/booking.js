/**
 * Booking facade upgrade (spec §9; PRIV01/§7 progressive click-to-load).
 *
 * The booking component (a.salt-booking) is a PLAIN LINK to the provider's booking page.
 * This module intercepts the click ONLY when the visitor has granted the
 * 'marketing' consent category via the WP Consent API bridge
 * (window.wp_has_consent). Granted → prevent navigation and swap the link
 * for the provider's inline embed (the first third-party load, from the
 * CSP-allow-listed provider origin). Denied or no CMP on the page → do
 * NOTHING: the plain link navigates and our page loads zero third-party
 * bytes (progressive fallback, fail closed).
 *
 * No-ops gracefully when a.salt-booking is absent.
 */
(() => {
	'use strict';

	const links = document.querySelectorAll('a.salt-booking');
	if (!links.length) {
		return;
	}

	/**
	 * Fail-closed consent verdict for the marketing category.
	 *
	 * @return {boolean} True only on an explicit CMP grant.
	 */
	const hasConsent = () =>
		typeof window.wp_has_consent === 'function' &&
		// No consent type: no plugin manages consent, and the API's true is no grant.
		Boolean(window.wp_consent_type !== undefined ? window.wp_consent_type : window.wp_fallback_consent_type) &&
		window.wp_has_consent('marketing') === true;

	/**
	 * Inject a third-party script once.
	 *
	 * @param {string} src Script URL (provider origin, CSP allow-listed).
	 */
	const injectScript = (src) => {
		if (document.querySelector(`script[src="${src}"]`)) {
			return;
		}
		const script = document.createElement('script');
		script.src = src;
		script.async = true;
		document.head.appendChild(script);
	};

	/**
	 * Replace the link with a Calendly inline widget.
	 *
	 * Calendly's widget.js scans for .calendly-inline-widget[data-url]
	 * elements on load, so the holder must exist before the script runs.
	 *
	 * @param {HTMLAnchorElement} link The facade link.
	 */
	const embedCalendly = (link) => {
		const holder = document.createElement('div');
		holder.className = 'calendly-inline-widget salt-booking__embed';
		holder.setAttribute('data-url', link.href);
		// CSSOM sizing (the style ATTRIBUTE would be blocked by style-src).
		holder.style.minWidth = '320px';
		holder.style.height = '640px';
		link.after(holder);
		injectScript('https://assets.calendly.com/assets/external/widget.js');
	};

	/**
	 * Replace the link with a Cal.com inline embed.
	 *
	 * Minimal port of Cal.com's official loader snippet: a queueing
	 * window.Cal stub collects calls until embed.js loads and drains it.
	 *
	 * @param {HTMLAnchorElement} link The facade link.
	 */
	const embedCalcom = (link) => {
		if (!window.Cal) {
			const cal = function (...args) {
				cal.q.push(args);
			};
			cal.q = [];
			cal.ns = {};
			cal.loaded = true;
			window.Cal = cal;
			injectScript('https://app.cal.com/embed/embed.js');
		}

		const holder = document.createElement('div');
		holder.className = 'salt-booking__embed';
		holder.style.width = '100%';
		holder.style.minHeight = '640px';
		link.after(holder);

		const calLink = new URL(link.href).pathname.replace(/^\/+/, '');
		window.Cal('init', { origin: 'https://app.cal.com' });
		window.Cal('inline', { elementOrSelector: holder, calLink });
	};

	links.forEach((link) => {
		link.addEventListener('click', (event) => {
			if (!hasConsent()) {
				return; // Plain navigation — loads nothing on our page.
			}

			event.preventDefault();

			if (link.dataset.saltBookingLoaded === 'true') {
				return;
			}
			link.dataset.saltBookingLoaded = 'true';

			try {
				if (link.dataset.provider === 'calendly') {
					embedCalendly(link);
				} else {
					embedCalcom(link);
				}
				link.hidden = true;
			} catch {
				// Any embed failure falls back to the plain link next click.
				link.dataset.saltBookingLoaded = '';
				link.hidden = false;
			}
		});
	});
})();
