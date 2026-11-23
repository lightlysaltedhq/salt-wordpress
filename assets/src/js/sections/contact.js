/**
 * Contact form progressive enhancement (spec §9; contract
 * markup/contact-form.json, SC-025).
 *
 * Without script the form posts to its own page, which answers it in the
 * same response. With script each form submits in place: it fetches a
 * form token from the credentials endpoint (never drawn in the cached
 * page; both REST addresses come from window.saltContact), posts the contract's fields to the REST route, marks each field
 * that failed, fills the status region and moves focus to it. CAPTCHA
 * tokens (Turnstile/reCAPTCHA render their own hidden inputs into the
 * form) are forwarded verbatim; the server verifies (SEC02).
 */
(() => {
	'use strict';

	// The REST addresses, from rest_url() (salt_contact_script_settings()):
	// ?rest_route= on plain permalinks, never a hard-coded /wp-json/.
	const settings = window.saltContact || {};
	const endpoint = settings.send || '';

	const credentials = () =>
		(settings.credentials
			? fetch(settings.credentials, { credentials: 'same-origin', cache: 'no-store' })
			: Promise.reject(new Error('contact: no REST address')))
			.then((response) => response.json())
			.then((body) => (body && body.ok && typeof body.formToken === 'string' ? body.formToken : ''));

	// A form token is refused until it is this old (the time trap).
	const tokenMinAge = 3500;
	const settle = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

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

			const post = (formToken) => {
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
				}).then((response) =>
					response.json().then((body) => ({ ok: response.ok && body.ok, status: response.status, body }))
				);
			};

			prefetch()
				.then(post)
				.then((result) => {
					if (result.status !== 403) {
						return result;
					}
					// A refused token (open over 4 hours, or the session changed):
					// take a fresh one and send once more, when it has aged past
					// the time trap.
					token = null;
					return prefetch().then((formToken) => settle(tokenMinAge).then(() => post(formToken)));
				})
				.then(({ ok, status: code, body }) => {
					// The token stays good for a retry after a refused send: a fresh
					// one would fail the time trap (it must be 3 seconds old). Only a
					// send that arrived, or a token refused again (403), takes a new
					// one, fetched straight away so it has aged by the next send.
					if (ok || code === 403) {
						token = null;
						prefetch();
					}
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
