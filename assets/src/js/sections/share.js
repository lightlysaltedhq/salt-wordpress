/**
 * Share-row copy-link enhancement (single post build-out; FAIL02).
 *
 * Progressive enhancement over a FUNCTIONAL no-JS base: the x-share-links
 * component renders its copy button inside a `hidden` <li> — without this
 * file (or without a clipboard API) the button simply never appears, so
 * there is no dead control. This module reveals the button, copies the URL
 * on click, and flips the accessible label to a "copied" confirmation for
 * a moment.
 *
 * Self-contained IIFE, plain ES2020, no imports. No-ops gracefully when
 * the DOM trigger ([data-share-copy]) or navigator.clipboard is absent.
 */
(() => {
	'use strict';

	if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
		return; // No clipboard API — button stays hidden (no dead control).
	}

	document.querySelectorAll('[data-share-copy]').forEach((button) => {
		const item = button.closest('[data-share-copy-item]');
		if (item) {
			item.hidden = false;
		}

		const idleLabel = button.getAttribute('aria-label') || '';
		const copiedLabel = button.dataset.copiedLabel || idleLabel;
		let timer = 0;

		button.addEventListener('click', () => {
			navigator.clipboard.writeText(button.dataset.shareUrl || window.location.href).then(() => {
				button.setAttribute('aria-label', copiedLabel);
				button.classList.add('text-brand', 'border-brand');
				window.clearTimeout(timer);
				timer = window.setTimeout(() => {
					button.setAttribute('aria-label', idleLabel);
					button.classList.remove('text-brand', 'border-brand');
				}, 2000);
			});
		});
	});
})();
