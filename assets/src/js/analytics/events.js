/**
 * Rich salt-instrumented events adapter.
 *
 * Pure DOM delegation over the section markup contracts (x-button,
 * x-accordion-item, x-contact-form, [data-section], [data-salt-carousel],
 * [data-salt-testimonials]) — NO section-file changes, passive listeners,
 * requestIdleCallback-wrapped setup. Every event fans out to BOTH providers
 * when present (window.salt.posthog + window.gtag) and window.salt.track()
 * is exposed for child themes.
 *
 * Events: cta_click, accordion_open, form_start, form_submit, section_view
 * (50% visible, once per element per pageview), carousel_interact.
 */
(() => {
	'use strict';

	window.salt = window.salt || {};

	/**
	 * Dispatch an event to every provider that is present.
	 *
	 * @param {string} name  Event name.
	 * @param {Object} props Event properties.
	 */
	const track = (name, props) => {
		const payload = props || {};
		const ph = window.salt.posthog;
		if (ph && typeof ph.capture === 'function') {
			ph.capture(name, payload);
		}
		if (typeof window.gtag === 'function') {
			window.gtag('event', name, payload);
		}
	};

	window.salt.track = track;

	/**
	 * The [data-section] slug an element belongs to.
	 *
	 * @param {Element} el Element inside a section.
	 * @return {string} Section slug ('' when outside any section).
	 */
	const sectionOf = (el) => {
		const host = el.closest('[data-section]');
		return host ? host.getAttribute('data-section') || '' : '';
	};

	const setup = () => {
		// (1) CTA clicks + (5) carousel interactions — one delegated listener.
		document.addEventListener(
			'click',
			(event) => {
				const target = event.target instanceof Element ? event.target : null;
				if (!target) {
					return;
				}

				const cta = target.closest('.x-button');
				if (cta && cta.closest('[data-section]')) {
					track('cta_click', {
						section: sectionOf(cta),
						label: (cta.textContent || '').trim(),
						href: cta.getAttribute('href') || '',
					});
					return;
				}

				const carouselButton = target.closest(
					'[data-salt-carousel] [class*="-button"], [data-salt-testimonials] [class*="-button"]'
				);
				if (carouselButton) {
					track('carousel_interact', { section: sectionOf(carouselButton) });
				}
			},
			{ passive: true }
		);

		// (2) Accordion opens — 'toggle' does not bubble; capture on document.
		document.addEventListener(
			'toggle',
			(event) => {
				const item = event.target;
				if (item instanceof HTMLElement && item.matches('details.x-accordion-item') && item.open) {
					track('accordion_open', { section: sectionOf(item) });
				}
			},
			true
		);

		// (3) Contact form: first focus (once per pageview) + submit.
		let formStarted = false;
		document.addEventListener(
			'focusin',
			(event) => {
				if (formStarted) {
					return;
				}
				const field = event.target;
				if (field instanceof Element && field.closest('form.x-contact-form')) {
					formStarted = true;
					track('form_start', { section: 'contact' });
				}
			},
			{ passive: true }
		);

		document.addEventListener(
			'submit',
			(event) => {
				const form = event.target;
				if (form instanceof HTMLElement && form.matches('form.x-contact-form')) {
					track('form_submit', { section: 'contact' });
				}
			},
			true
		);

		// (4) Section views — 50% threshold, once per element per pageview.
		if ('IntersectionObserver' in window) {
			const seen = new WeakSet();
			const observer = new IntersectionObserver(
				(entries) => {
					for (const entry of entries) {
						if (entry.isIntersecting && !seen.has(entry.target)) {
							seen.add(entry.target);
							observer.unobserve(entry.target);
							track('section_view', {
								section: entry.target.getAttribute('data-section') || '',
							});
						}
					}
				},
				{ threshold: 0.5 }
			);
			document.querySelectorAll('[data-section]').forEach((el) => observer.observe(el));
		}
	};

	const idle =
		typeof window.requestIdleCallback === 'function'
			? window.requestIdleCallback
			: (cb) => window.setTimeout(cb, 1);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => idle(setup));
	} else {
		idle(setup);
	}
})();
