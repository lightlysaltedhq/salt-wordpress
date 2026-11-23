/**
 * Contact form progressive enhancement (spec §9; contract
 * markup/contact-form.json, SC-025).
 *
 * Without script the form posts to its own page, which answers it in the
 * same response. With script each form submits in place: it fetches a
 * form token from the credentials endpoint (never drawn in the cached
 * page), posts the contract's fields to the REST route, marks each field
 * that failed, fills the status region and moves focus to it. CAPTCHA
 * tokens (Turnstile/reCAPTCHA render their own hidden inputs into the
 * form) are forwarded verbatim; the server verifies (SEC02).
 */
(() => {
	'use strict';

	const endpoint = '/wp-json/salt/v1/contact';

	const credentials = () =>
		fetch(`${endpoint}/credentials`, { credentials: 'same-origin', cache: 'no-store' })
			.then((response) => response.json())
			.then((body) => (body && body.ok && typeof body.formToken === 'string' ? body.formToken : ''));

	const kebab = (name) => name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();

	const enhance = (form) => {
		if (form.dataset.saltContactEnhanced === 'true') {
			return;
		}
		form.dataset.saltContactEnhanced = 'true';

		const anchor = form.id.replace(/__form$/, '');
		const status = form.querySelector('.salt-contact__status');
		const submit = form.querySelector('.salt-contact__submit');
		let token = null;

		// Fetch the credentials as the visitor starts, so the send does not wait.
		const prefetch = () => {
			if (!token) {
				token = credentials().catch(() => '');
			}
			return token;
		};
		form.addEventListener('focusin', prefetch, { once: true });

		const showStatus = (tone, message) => {
			if (!status) {
				return;
			}
			status.dataset.tone = tone;
			status.textContent = message;
			if (tone !== 'sending') {
				status.focus();
			}
		};

		const markErrors = (errors) => {
			form.querySelectorAll('.salt-contact__error').forEach((p) => p.remove());
			form.querySelectorAll('[aria-invalid]').forEach((input) => {
				input.removeAttribute('aria-invalid');
				input.removeAttribute('aria-describedby');
			});
			Object.entries(errors || {}).forEach(([name, message]) => {
				const input = form.querySelector(`#${CSS.escape(`${anchor}__${kebab(name)}`)}`);
				if (!input) {
					return;
				}
				const error = document.createElement('p');
				error.className = 'salt-contact__error';
				error.id = `${input.id}-error`;
				error.textContent = message;
				input.before(error);
				input.setAttribute('aria-invalid', 'true');
				input.setAttribute('aria-describedby', `${error.id} ${anchor}__status`);
			});
		};

		form.addEventListener('submit', (event) => {
			event.preventDefault();
			if (submit) {
				submit.disabled = true;
			}
			showStatus('sending', '');

			prefetch()
				.then((formToken) => {
					if (!formToken) {
						throw new Error('no credentials');
					}
					const payload = {};
					for (const [key, value] of new FormData(form).entries()) {
						payload[key] = value;
					}
					payload.formToken = formToken;
					return fetch(endpoint, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(payload),
					}).then((response) => response.json().then((body) => ({ ok: response.ok && body.ok, body })));
				})
				.then(({ ok, body }) => {
					token = null; // A form token serves one send.
					markErrors(ok ? {} : body.errors);
					showStatus(ok ? 'sent' : 'problem', body.message || '');
					if (ok) {
						form.reset();
						document.dispatchEvent(new CustomEvent('salt:contact-sent'));
					}
				})
				.catch(() => {
					// No credentials or no network: fall back to the native path.
					form.dataset.saltContactEnhanced = 'false';
					form.submit();
				})
				.finally(() => {
					if (submit) {
						submit.disabled = false;
					}
				});
		});
	};

	document.querySelectorAll('form.salt-contact__form').forEach(enhance);
})();
