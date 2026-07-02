/**
 * On-demand MapLibre bundle (spec §9; loaded by maps.js post-click,
 * post-consent — NEVER on initial page load; it is large by nature).
 *
 * Exposes the library as window.maplibregl for maps.js and installs the
 * ESSENTIAL subset of maplibre-gl.css. Our esbuild pipeline emits JS only,
 * so the styles ship as a string and are installed via a constructable
 * stylesheet (new CSSStyleSheet + document.adoptedStyleSheets): CSP
 * style-src governs <style> elements and style attributes, but NOT
 * CSSOM-constructed stylesheets, so this needs no nonce and cannot violate
 * the policy. Browsers without constructable-stylesheet support get a
 * degraded but functional marker map.
 */
import maplibregl from 'maplibre-gl';

// Essential rules distilled from maplibre-gl.css: canvas sizing, marker
// positioning, attribution + control corners. Decorative control skinning
// is intentionally omitted (the facade map ships no navigation controls).
const SALT_MAPLIBRE_CSS = `
.maplibregl-map{position:relative;overflow:hidden;width:100%;height:100%;font:12px/20px system-ui,sans-serif;-webkit-tap-highlight-color:rgb(0 0 0/0)}
.maplibregl-canvas-container,.maplibregl-canvas{position:absolute;top:0;left:0;width:100%;height:100%}
.maplibregl-canvas-container.maplibregl-interactive{cursor:grab;user-select:none;-webkit-user-select:none}
.maplibregl-canvas-container.maplibregl-interactive:active{cursor:grabbing}
.maplibregl-marker{position:absolute;top:0;left:0;will-change:transform;opacity:1;transition:opacity .2s}
.maplibregl-control-container{position:absolute;inset:0;pointer-events:none;z-index:2}
.maplibregl-ctrl-top-left,.maplibregl-ctrl-top-right,.maplibregl-ctrl-bottom-left,.maplibregl-ctrl-bottom-right{position:absolute;pointer-events:none}
.maplibregl-ctrl-top-left{top:0;left:0}
.maplibregl-ctrl-top-right{top:0;right:0}
.maplibregl-ctrl-bottom-left{bottom:0;left:0}
.maplibregl-ctrl-bottom-right{bottom:0;right:0}
.maplibregl-ctrl{clear:both;pointer-events:auto}
.maplibregl-ctrl-attrib{background-color:rgb(255 255 255/.75);margin:0;padding:0 5px;font-size:11px}
.maplibregl-ctrl-attrib a{color:inherit;text-decoration:underline}
.maplibregl-ctrl-attrib-button{display:none}
`;

try {
	const sheet = new CSSStyleSheet();
	sheet.replaceSync(SALT_MAPLIBRE_CSS);
	document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
} catch {
	// No constructable-stylesheet support: the map still renders (canvas
	// gets inline sizing from MapLibre itself); only chrome styling is lost.
}

window.maplibregl = maplibregl;
