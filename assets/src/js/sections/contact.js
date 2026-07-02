/**
 * Contact form progressive enhancement (spec §9).
 *
 * The no-JS base is fully functional (admin-post + redirect-back status).
 * This module upgrades to an async REST submission: inline status into the
 * aria-live region (A11Y09), no page reload, submit disabled in flight.
 * CAPTCHA tokens (Turnstile/reCAPTCHA render their own hidden inputs into
 * the form) are forwarded verbatim; the server verifies (SEC02).
 */
(() => {
	'use strict';

	const form = document.querySelector('form.x-contact-form');
	if (!form || form.dataset.saltContactEnhanced === 'true') {
		return;
	}
	form.dataset.saltContactEnhanced = 'true';

	const status = document.querySelector('[data-salt-contact-status]');
	const submit = form.querySelector('button[type="submit"]');

	const showStatus = (ok, message) => {
		if (!status) {
			return;
		}
		const p = document.createElement('p');
		p.className = ok
			? 'rounded-md bg-brand-tint p-3 text-ink'
			: 'rounded-md border border-error p-3 text-error';
		p.textContent = message;
		status.replaceChildren(p);
		status.scrollIntoView({ block: 'nearest', behavior: 'auto' });
	};

	form.addEventListener('submit', (event) => {
		event.preventDefault();

		const data = new FormData(form);
		const payload = {};
		for (const [key, value] of data.entries()) {
			payload[key] = value;
		}

		if (submit) {
			submit.disabled = true;
		}

		fetch('/wp-json/salt/v1/contact', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload),
		})
			.then((response) => response.json().then((body) => ({ ok: response.ok && body.ok, body })))
			.then(({ ok, body }) => {
				showStatus(ok, body.message || '');
				if (ok) {
					form.reset();
					document.dispatchEvent(new CustomEvent('salt:contact-sent'));
				}
			})
			.catch(() => {
				// Network failure: fall back to the native no-JS path.
				form.dataset.saltContactEnhanced = 'false';
				form.submit();
			})
			.finally(() => {
				if (submit) {
					submit.disabled = false;
				}
			});
	});
})();
