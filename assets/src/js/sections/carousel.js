/**
 * The carousel track's previous and next controls (salt-contract
 * markup/carousel-track.json, rc.7; EP-72 M3 unit 8).
 *
 * Progressive: without this file the track is a scroll-snapping list whose
 * every slide is reachable by scrolling, swiping and Tab (the server makes
 * the track a tab stop when its slides hold no link). With it, each
 * .salt-carousel region gains div.salt-carousel__controls after its track:
 * two buttons that scroll by one slide, report the edges with
 * aria-disabled (never disabled, so focus is not lost) and scroll
 * instantly when the visitor prefers reduced motion. Behaviour follows
 * Salt for Next.js's <Carousel> (packages/core/src/lib/carousel.ts).
 *
 * The buttons' text is labels.previous and labels.next, which the theme
 * hands over as window.saltCarousel.labels (salt_carousel_script_labels()).
 */
(() => {
	'use strict';

	// Sub-pixel slack: a fully scrolled track can report a fraction short.
	const EDGE_TOLERANCE = 1;

	const labels = (window.saltCarousel && window.saltCarousel.labels) || {};

	/**
	 * Whether the track is at its start and at its end.
	 *
	 * @param {HTMLElement} track The track.
	 * @return {{atStart: boolean, atEnd: boolean}} The edges.
	 */
	const edges = (track) => {
		// Math.abs: a right-to-left track's scrollLeft runs negative.
		const travelled = Math.abs(track.scrollLeft);
		return {
			atStart: travelled <= EDGE_TOLERANCE,
			atEnd: travelled >= track.scrollWidth - track.clientWidth - EDGE_TOLERANCE,
		};
	};

	/**
	 * Mount the controls on one carousel.
	 *
	 * @param {HTMLElement} region div.salt-carousel.
	 */
	const mount = (region) => {
		const track = region.querySelector(':scope > .salt-carousel__track');
		if (!track || region.querySelector(':scope > .salt-carousel__controls')) {
			return;
		}

		const move = (direction) => {
			const first = track.firstElementChild;
			const width = first instanceof HTMLElement ? first.offsetWidth : 0;
			if (width === 0) {
				return;
			}
			const styles = window.getComputedStyle(track);
			const gap = Number.parseFloat(styles.columnGap);
			const step = width + (Number.isFinite(gap) ? gap : 0);
			const forward = direction === 'next' ? 1 : -1;
			track.scrollBy({
				left: step * forward * (styles.direction === 'rtl' ? -1 : 1),
				behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
			});
		};

		const control = (direction, text) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'salt-button salt-carousel__control';
			button.dataset.style = 'secondary';
			button.dataset.direction = direction;
			button.dataset.trackControl = 'carousel-' + direction;
			button.textContent = text;
			button.addEventListener('click', () => {
				if (button.getAttribute('aria-disabled') !== 'true') {
					move(direction);
				}
			});
			return button;
		};

		const previous = control('previous', labels.previous || '');
		const next = control('next', labels.next || '');
		const controls = document.createElement('div');
		controls.className = 'salt-carousel__controls';
		controls.append(previous, next);
		track.after(controls);

		const mark = (button, atEdge) => {
			if (atEdge) {
				button.setAttribute('aria-disabled', 'true');
			} else {
				button.removeAttribute('aria-disabled');
			}
		};
		const refresh = () => {
			const state = edges(track);
			mark(previous, state.atStart);
			mark(next, state.atEnd);
		};
		track.addEventListener('scroll', refresh, { passive: true });
		window.addEventListener('resize', refresh);
		refresh();
	};

	const init = () => {
		document.querySelectorAll('.salt-carousel').forEach(mount);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
