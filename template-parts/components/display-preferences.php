<?php
/**
 * The visitor display-preferences widget (spec §14; A11Y05/06; FAIL02;
 * salt-contract markup/display-preferences.json).
 *
 * Server-rendered [hidden]: assets/src/js/display-prefs.js removes the
 * attribute, seeds initial state from the <html> data-* attributes and
 * wires the controls. No JS = no visible widget = no dead controls
 * (FAIL02). Disclosure via native <details>/<summary> (APG disclosure
 * pattern — role and keyboard behaviour come from the platform).
 *
 * The contract's stylesheet owes this widget its rules (display-preferences
 * notes, stylesheet-owes). Toggle buttons carry aria-pressed; the font-size group is a labelled role=group of three
 * stepped buttons (the JS steps the -1|0|1|2 enum, §14).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   controls       array required  Subset of
 *                                  font_size|contrast|grayscale|dark_mode|motion,
 *                                  already gated by
 *                                  salt_display_preferences_controls().
 *                                  Unknown entries are skipped + logged;
 *                                  an empty (or all-unknown) list renders
 *                                  NOTHING (logged).
 *   motion_default bool  false     True when the site serves reduced motion
 *                                  by default (reduce_motion option) — the
 *                                  JS needs it to persist an explicit
 *                                  opt-out.
 *
 * Render via salt_component( 'display-preferences', … ).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'controls'       => array(
			'type'     => 'array',
			'required' => true,
		),
		'motion_default' => array(
			'type'    => 'bool',
			'default' => false,
		),
	),
	'display-preferences'
);

// Canonical render order; unknown entries fail loud (FAIL01).
$salt_known    = array( 'font_size', 'contrast', 'grayscale', 'dark_mode', 'motion' );
$salt_unknown  = array_diff( $salt_args['controls'], $salt_known );
$salt_controls = array_values( array_intersect( $salt_known, $salt_args['controls'] ) );

foreach ( $salt_unknown as $salt_bad ) {
	salt_log( sprintf( 'x-display-preferences: unknown control "%s" — skipped.', (string) $salt_bad ) );
}

if ( array() === $salt_controls ) {
	salt_log( 'x-display-preferences: no valid controls — rendered nothing.' );
	return;
}

// data-salt-pref values match the salt-{pref} localStorage/enum contract
// in salt_theme_init_js() (inc/setup.php): the dark_mode control drives
// the 'theme' preference.
$salt_labels  = salt_display_preferences_labels();
$salt_toggles = array(
	'contrast'  => array( 'contrast', $salt_labels['contrast'] ),
	'grayscale' => array( 'grayscale', $salt_labels['grayscale'] ),
	'dark_mode' => array( 'theme', $salt_labels['theme'] ),
	'motion'    => array( 'motion', $salt_labels['motion'] ),
);
$salt_scale   = array(
	'decrease' => 'A-',
	'reset'    => 'A',
	'increase' => 'A+',
);
?>
<div class="salt-display-preferences" hidden<?php echo $salt_args['motion_default'] ? ' data-motion-default="reduce"' : ''; ?>>
	<details class="salt-display-preferences__panel">
		<summary class="salt-display-preferences__summary"><?php echo esc_html( $salt_labels['summary'] ); ?></summary>
		<div class="salt-display-preferences__controls">
			<?php foreach ( $salt_controls as $salt_control ) : ?>
				<?php if ( 'font_size' === $salt_control ) : ?>
			<div class="salt-display-preferences__group" role="group" aria-label="<?php echo esc_attr( $salt_labels['fontSize'] ); ?>">
				<span class="salt-display-preferences__label" aria-hidden="true"><?php echo esc_html( $salt_labels['fontSize'] ); ?></span>
					<?php foreach ( $salt_scale as $salt_action => $salt_glyph ) : ?>
				<button type="button" data-salt-pref="scale" data-salt-action="<?php echo esc_attr( $salt_action ); ?>" aria-label="<?php echo esc_attr( $salt_labels[ $salt_action ] ); ?>"><?php echo esc_html( $salt_glyph ); ?></button>
					<?php endforeach; ?>
			</div>
				<?php else : ?>
			<button type="button" aria-pressed="false" data-salt-pref="<?php echo esc_attr( $salt_toggles[ $salt_control ][0] ); ?>"><?php echo esc_html( $salt_toggles[ $salt_control ][1] ); ?></button>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</details>
</div>
