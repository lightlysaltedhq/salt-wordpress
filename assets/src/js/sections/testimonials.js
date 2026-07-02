/**
 * Testimonials slider enhancement (spec §10 #8; FAIL02).
 *
 * Progressive enhancement over a FUNCTIONAL no-JS base: the section renders
 * a CSS scroll-snap track (overflow-x-auto, snap-x, snap-start items) that
 * works fully without this file. This module injects prev/next buttons
 * (none exist in the PHP markup — no dead buttons no-JS), keeps them in
 * sync with the scroll position, and respects prefers-reduced-motion.
 *
 * Self-contained IIFE, plain ES2020, no imports. No-ops gracefully when
 * the DOM trigger ([data-salt-testimonials]) is absent; every item stays
 * reachable without it (the track itself scrolls and is focusable).
 */
(() => {
	'use strict';

	const TRIGGER = '[data-salt-testimonials]';

	/**
	 * Read the track's inter-item gap in px (falls back to 24 = gap-6).
	 *
	 * @param {HTMLElement} track The snap track.
	 * @return {number} Gap in pixels.
	 */
	const trackGap = (track) => {
		const gap = parseFloat(window.getComputedStyle(track).columnGap);
		return Number.isFinite(gap) ? gap : 24;
	};

	/**
	 * Enhance one snap track with prev/next controls.
	 *
	 * @param {HTMLElement} track The snap track.
	 */
	const enhance = (track) => {
		if (track.dataset.saltTestimonialsEnhanced === 'true' || track.children.length < 2) {
			return;
		}
		track.dataset.saltTestimonialsEnhanced = 'true';

		const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
		// RTL: scrollLeft runs 0 → negative and "next" scrolls negative-left.
		const isRtl = window.getComputedStyle(track).direction === 'rtl';

		const scrollAmount = () => {
			const first = track.firstElementChild;
			const width = first ? first.getBoundingClientRect().width : track.clientWidth;
			return width + trackGap(track);
		};

		const makeButton = (label, direction) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.className =
				'x-testimonials-button inline-flex items-center justify-center rounded-full border border-border bg-surface p-2 text-ink disabled:opacity-40';
			button.setAttribute('aria-label', label);
			const back = direction < 0;
			button.innerHTML =
				back !== isRtl
					? '<span aria-hidden="true">&larr;</span>'
					: '<span aria-hidden="true">&rarr;</span>';
			button.addEventListener('click', () => {
				track.scrollBy({
					left: (isRtl ? -direction : direction) * scrollAmount(),
					behavior: reducedMotion.matches ? 'auto' : 'smooth',
				});
			});
			return button;
		};

		const prev = makeButton(track.dataset.saltPrevLabel || 'Previous', -1);
		const next = makeButton(track.dataset.saltNextLabel || 'Next', 1);

		const controls = document.createElement('div');
		controls.className = 'x-testimonials-controls mt-4 flex gap-2';
		controls.append(prev, next);
		track.after(controls);

		const setDisabled = (button, disabled, sibling) => {
			// Never let a disabling button swallow keyboard focus.
			if (disabled && document.activeElement === button) {
				if (sibling.disabled) {
					track.setAttribute('tabindex', track.getAttribute('tabindex') || '-1');
					track.focus();
				} else {
					sibling.focus();
				}
			}
			button.disabled = disabled;
		};

		const update = () => {
			// Math.abs: RTL scrollLeft is negative over the same magnitude range.
			const pos = Math.abs(track.scrollLeft);
			setDisabled(prev, pos <= 0, next);
			setDisabled(next, pos + track.clientWidth >= track.scrollWidth - 1, prev);
		};
		track.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
	};

	const init = () => {
		document.querySelectorAll(TRIGGER).forEach(enhance);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
