<?php
/**
 * Display preferences service (spec §14; A11Y05/06; FAIL02).
 *
 * The front-end widget that lets visitors adjust font size, contrast,
 * grayscale, dark mode and motion. Two-level gating (mirrors dark mode):
 * the 'display_preferences' module flag = availability; the
 * display_preferences_enabled option = editor on/off (defaults off — the
 * widget is opt-in, §14). The widget is NOT an accessibility overlay: the
 * site is fully accessible with it off.
 *
 * Progressive enhancement contract (FAIL02): the server renders the widget
 * [hidden]; assets/src/js/display-prefs.js removes the attribute and wires
 * the controls. No JS = no visible widget = no dead controls.
 *
 * The site-footer template calls salt_the_display_preferences()
 * (function_exists-guarded there) — the single render seam.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the display-preferences widget is active on this site.
 *
 * Module flag (availability) AND the editor option (on/off), both behind
 * the ACF guard (DEP01 — no options means the safe default: off).
 *
 * @return bool
 */
function salt_display_preferences_active(): bool {
	return salt_module_enabled( 'display_preferences' )
		&& salt_acf_active()
		&& (bool) salt_option( 'display_preferences_enabled' );
}

/**
 * The enabled widget controls, in canonical render order.
 *
 * Reads the display_controls checkbox option (null — never saved — means
 * all five, matching the field's default_value). Unknown stored values are
 * dropped by the intersection. 'dark_mode' is additionally removed unless
 * dark mode itself is available AND enabled — a dark-mode toggle on a
 * light-only site is a dead control (§14).
 *
 * @return string[] Subset of font_size|contrast|grayscale|dark_mode|motion.
 */
function salt_display_preferences_controls(): array {
	$known = array( 'font_size', 'contrast', 'grayscale', 'dark_mode', 'motion' );

	$value    = salt_acf_active() ? salt_option( 'display_controls' ) : null;
	$controls = null === $value
		? $known
		: array_values( array_intersect( $known, array_map( 'strval', (array) $value ) ) );

	$dark_available = salt_module_enabled( 'dark_mode' )
		&& salt_acf_active()
		&& (bool) salt_option( 'dark_mode' );

	// A placed theme toggle takes the dark-mode control's place.
	if ( ! $dark_available || ( function_exists( 'salt_theme_toggle_placement' ) && null !== salt_theme_toggle_placement() ) ) {
		$controls = array_values( array_diff( $controls, array( 'dark_mode' ) ) );
	}

	return $controls;
}

/**
 * Echo the display-preferences widget (the site-footer seam).
 *
 * Renders nothing when the widget is inactive; logs when it is enabled but
 * every control has been deselected (an empty panel is a defect, not a
 * blank — fail loud, ARCH05).
 *
 * @return void
 */
function salt_the_display_preferences(): void {
	if ( ! salt_display_preferences_active() ) {
		return;
	}

	$controls = salt_display_preferences_controls();
	if ( array() === $controls ) {
		salt_log( 'display-preferences: widget enabled but no controls are selected — rendered nothing.' );
		return;
	}

	salt_component(
		'display-preferences',
		array(
			'controls'       => $controls,
			'motion_default' => (bool) salt_option( 'reduce_motion' ),
		)
	);
}

/**
 * Enqueue the widget's enhancement module when active
 * ('wp_enqueue_scripts', 20).
 *
 * A manifest miss is logged loudly here (unlike per-section modules, this
 * asset is expected whenever the widget is active): without it the widget
 * simply stays [hidden] — degraded but never dead controls (FAIL02).
 *
 * @return void
 */
function salt_display_preferences_enqueue(): void {
	if ( ! salt_display_preferences_active() ) {
		return;
	}

	$src = salt_asset_optional( 'display-prefs.js' );
	if ( null === $src ) {
		salt_log( 'display-preferences: no manifest entry for "display-prefs.js" — the widget stays hidden (FAIL02).' );
		return;
	}

	wp_enqueue_script(
		'salt-display-prefs',
		$src,
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Content hash in the dist filename is the cache-buster (ARCH03).
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'salt_display_preferences_enqueue', 20 );

/**
 * The widget's words (the contract's display-preferences labels):
 * translatable theme strings, with the `salt_display_preferences_labels`
 * filter as the seam (the contract adapter feeds it a case's labels).
 *
 * @return array<string,string>
 */
function salt_display_preferences_labels(): array {
	$labels = array(
		'summary'   => __( 'Display preferences', 'salt' ),
		'fontSize'  => __( 'Text size', 'salt' ),
		'decrease'  => __( 'Smaller text', 'salt' ),
		'reset'     => __( 'Reset text size', 'salt' ),
		'increase'  => __( 'Larger text', 'salt' ),
		'contrast'  => __( 'High contrast', 'salt' ),
		'grayscale' => __( 'Greyscale', 'salt' ),
		'theme'     => __( 'Dark mode', 'salt' ),
		'motion'    => __( 'Reduce motion', 'salt' ),
	);
	$given  = apply_filters( 'salt_display_preferences_labels', $labels );
	return is_array( $given ) ? array_merge( $labels, array_map( 'strval', $given ) ) : $labels;
}
