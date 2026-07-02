/**
 * PostHog init — FIRST-PARTY BUNDLED (spec v2 analytics; PRIV01-compatible).
 *
 * posthog-js is bundled into this entry at build time: no CDN, no third-party
 * script origin — the only external traffic is the capture endpoint itself
 * (connect-src, extended by salt_analytics_posthog_csp() when active).
 *
 * Config comes from the non-executable JSON data block PHP emits
 * (<script type="application/json" id="salt-posthog-config">). Consent Mode
 * pattern: init runs immediately with cookieless_mode 'on_reject' — until the
 * CMP grants 'statistics' the SDK runs cookieless (server hash mode); the
 * consent.js entry flips opt-in/opt-out on consent changes.
 *
 * NOTE: cookieless_mode 'on_reject' requires "Cookieless server hash mode"
 * enabled in the PostHog project settings.
 */
import posthog from 'posthog-js';

(() => {
	'use strict';

	const el = document.getElementById('salt-posthog-config');
	if (!el) {
		return;
	}

	let config = null;
	try {
		config = JSON.parse(el.textContent || '');
	} catch {
		return;
	}

	if (
		!config ||
		typeof config.key !== 'string' ||
		config.key === '' ||
		typeof config.host !== 'string' ||
		config.host === ''
	) {
		return;
	}

	posthog.init(config.key, {
		api_host: config.host,
		cookieless_mode: 'on_reject',
		autocapture: true,
		capture_pageview: true,
		capture_pageleave: true,
		disable_session_recording: !config.recording,
		persistence: 'localStorage+cookie',
	});

	window.salt = window.salt || {};
	window.salt.posthog = posthog;
})();
