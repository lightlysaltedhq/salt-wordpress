/**
 * Map facade loader (spec §9; PRIV01/§7 click-to-load).
 *
 * Small, first-party, enqueued only on pages whose section pre-scan can
 * contain a map. On "Load map" click it:
 *
 *   1. Checks consent CLIENT-SIDE and fail-closed: the click is user
 *      intent, but the tiles are a third-party load, so the maps→marketing
 *      consent category still gates. No window.wp_has_consent on the page
 *      (no CMP) or a denied verdict → dispatch 'salt:consent-required'
 *      (bubbling, so a CMP integration can open its banner) + show the
 *      inline i18n'd notice from the facade's data attribute, and abort.
 *   2. Granted → injects a <script> for the SAME-origin MapLibre bundle
 *      (window.saltMaps.maplibre, from the nonce-stamped inline script
 *      salt_maps_enqueue() adds; CSP script-src 'self' allows it — no
 *      third-party script ever) and initialises a raster map from the
 *      facade's data-lat/lng/zoom/tile with a marker. OpenStreetMap attribution is
 *      REQUIRED and always rendered via the raster source.
 *
 * The facade is the contract's (salt-contract markup/map.json):
 * div.salt-map with an optional p.salt-map__label and the
 * button.salt-map__load. No-ops gracefully when no .salt-map is present.
 */
(() => {
	'use strict';

	const containers = document.querySelectorAll('.salt-map');
	if (!containers.length) {
		return;
	}

	/** @type {Promise<void>|null} Single shared MapLibre bundle load. */
	let libraryLoading = null;

	/**
	 * Load the same-origin MapLibre bundle once (sets window.maplibregl).
	 *
	 * @param {string} src Hashed bundle URL (same origin).
	 * @return {Promise<void>} Resolves when window.maplibregl exists.
	 */
	const loadLibrary = (src) => {
		if (window.maplibregl) {
			return Promise.resolve();
		}
		if (!src) {
			return Promise.reject(new Error('maps: no MapLibre bundle address.'));
		}
		if (!libraryLoading) {
			libraryLoading = new Promise((resolve, reject) => {
				const script = document.createElement('script');
				script.src = src;
				script.async = true;
				script.onload = () => resolve();
				script.onerror = () => {
					libraryLoading = null;
					reject(new Error('maps: MapLibre bundle failed to load.'));
				};
				document.head.appendChild(script);
			});
		}
		return libraryLoading;
	};

	/**
	 * Show (or update) the facade's inline notice.
	 *
	 * @param {HTMLElement} container The .salt-map facade.
	 * @param {string}      message   Pre-translated message text.
	 */
	const showNotice = (container, message) => {
		let notice = container.querySelector('.salt-map__notice');
		if (!notice) {
			notice = document.createElement('p');
			notice.className = 'salt-map__notice';
			notice.setAttribute('role', 'status');
			container.appendChild(notice);
		}
		notice.textContent = message;
	};

	/**
	 * Fail-closed consent verdict for the maps→marketing category.
	 *
	 * @return {boolean} True only on an explicit CMP grant.
	 */
	const hasConsent = () =>
		typeof window.wp_has_consent === 'function' && window.wp_has_consent('marketing') === true;

	/**
	 * Build the raster map inside the facade (post-click, post-consent).
	 *
	 * @param {HTMLElement} container The .salt-map facade.
	 */
	const initMap = (container) => {
		const lat = parseFloat(container.dataset.lat);
		const lng = parseFloat(container.dataset.lng);
		const zoom = parseInt(container.dataset.zoom, 10);

		// The shared stylesheets have no salt-map rules yet (map.json,
		// stylesheet-owes): until they do, the canvas sizes itself here
		// (CSSOM, which the CSP's style-src allows) to fill a 16:9 facade.
		container.style.position = 'relative';
		container.style.aspectRatio = '16 / 9';
		const target = document.createElement('div');
		target.className = 'salt-map__canvas';
		target.style.position = 'absolute';
		target.style.inset = '0';
		container.appendChild(target);

		const map = new window.maplibregl.Map({
			container: target,
			style: {
				version: 8,
				sources: {
					'salt-raster': {
						type: 'raster',
						tiles: [container.dataset.tile],
						tileSize: 256,
						// REQUIRED credit for OSM-derived tiles.
						attribution: '© OpenStreetMap contributors',
					},
				},
				layers: [{ id: 'salt-raster', type: 'raster', source: 'salt-raster' }],
			},
			center: [lng, lat],
			zoom: Number.isFinite(zoom) ? zoom : 14,
			attributionControl: { compact: false },
		});
		new window.maplibregl.Marker().setLngLat([lng, lat]).addTo(map);
	};

	containers.forEach((container) => {
		const button = container.querySelector('.salt-map__load');
		if (!button) {
			return;
		}

		button.addEventListener('click', () => {
			if (!hasConsent()) {
				// Fail closed: no CMP or a denied verdict both land here.
				container.dispatchEvent(
					new CustomEvent('salt:consent-required', {
						bubbles: true,
						detail: { category: 'marketing', source: 'maps' },
					})
				);
				showNotice(container, container.dataset.consentMessage || '');
				return;
			}

			button.disabled = true;
			loadLibrary((window.saltMaps && window.saltMaps.maplibre) || '')
				.then(() => {
					button.remove();
					const label = container.querySelector('.salt-map__label');
					if (label) {
						label.remove();
					}
					initMap(container);
				})
				.catch(() => {
					button.disabled = false;
					showNotice(container, container.dataset.errorMessage || '');
				});
		});
	});
})();
