/**
 * The share links' copy control (salt-contract markup/share-links.json).
 *
 * Progressive enhancement: the server draws span.salt-copy-link with its
 * button not ready (data-ready="false"), which the shared stylesheet hides,
 * so without this file, or without a clipboard API, there is no dead
 * control. This module marks each button ready, copies the page's address
 * (its canonical link, else the address bar) on click and writes the result
 * into the control's status (role=status, aria-live=polite), which the
 * theme hands over as window.saltShare.labels (salt_share_script_labels()).
 *
 * Self-contained IIFE, plain ES2020, no imports.
 */
(() => {
	'use strict';

	if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
		return; // No clipboard API: the button stays not ready (hidden).
	}

	const labels = (window.saltShare && window.saltShare.labels) || {};
	const canonical = document.querySelector('link[rel="canonical"]');
	const url = canonical && canonical.href ? canonical.href : window.location.href;

	document.querySelectorAll('.salt-copy-link').forEach((control) => {
		const button = control.querySelector('.salt-copy-link__button');
		const status = control.querySelector('.salt-copy-link__status');
		if (!button) {
			return;
		}
		button.dataset.ready = 'true';
		let timer = 0;

		button.addEventListener('click', () => {
			const say = (text) => {
				if (!status) {
					return;
				}
				status.textContent = text || '';
				window.clearTimeout(timer);
				timer = window.setTimeout(() => {
					status.textContent = '';
				}, 4000);
			};
			navigator.clipboard.writeText(url).then(
				() => say(labels.copied),
				() => say(labels.copyFailed),
			);
		});
	});
})();
