/**
 * Consent wiring for the analytics providers (Consent Mode pattern).
 *
 * Enqueued when EITHER provider is active. Both providers boot in a
 * privacy-default state (PostHog cookieless 'on_reject'; GA4 Consent Mode v2
 * with everything denied) — this module listens for the visitor's verdict and
 * flips them, on load and on every consent change:
 *
 * - WP Consent API document event 'wp_listen_for_consent_change'
 *   (Complianz / CookieYes / Cookiebot …) — wp_has_consent() is authoritative
 *   when present.
 * - Custom 'salt:consent' document event — the documented bridge for CMPs
 *   without Consent API support: dispatch with
 *   detail = { statistics: bool, marketing: bool }.
 *
 * 'statistics' drives PostHog opt-in/out + GA4 analytics_storage; 'marketing'
 * drives GA4 ad_storage / ad_user_data / ad_personalization. Everything is
 * typeof-guarded: zero errors when only one provider is active.
 */
(() => {
	'use strict';

	/**
	 * Resolve a consent category.
	 *
	 * @param {string} category WP Consent API category name.
	 * @param {*}      detail   Event detail (custom-event fallback).
	 * @return {boolean} Whether the category is granted.
	 */
	const granted = (category, detail) => {
		if (typeof window.wp_has_consent === 'function') {
			return window.wp_has_consent(category) === true;
		}
		if (detail && typeof detail === 'object') {
			const value = detail[category];
			return value === true || value === 'allow';
		}
		return false;
	};

	/**
	 * Apply the current consent state to whichever providers are present.
	 *
	 * @param {*} detail Event detail, or null on initial load.
	 */
	const apply = (detail) => {
		const statistics = granted('statistics', detail);
		const marketing = granted('marketing', detail);

		const ph = window.salt && window.salt.posthog;
		if (ph) {
			if (statistics && typeof ph.opt_in_capturing === 'function') {
				ph.opt_in_capturing();
			} else if (!statistics && typeof ph.opt_out_capturing === 'function') {
				ph.opt_out_capturing();
			}
		}

		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', {
				analytics_storage: statistics ? 'granted' : 'denied',
				ad_storage: marketing ? 'granted' : 'denied',
				ad_user_data: marketing ? 'granted' : 'denied',
				ad_personalization: marketing ? 'granted' : 'denied',
			});
		}
	};

	document.addEventListener('wp_listen_for_consent_change', (event) => apply(event.detail));
	document.addEventListener('salt:consent', (event) => apply(event.detail));

	apply(null);
})();
