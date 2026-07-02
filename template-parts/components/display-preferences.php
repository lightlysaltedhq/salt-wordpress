<?php
/**
 * x-display-preferences — the visitor display-preferences widget (spec §14;
 * A11Y05/06; FAIL02).
 *
 * Server-rendered [hidden]: assets/src/js/display-prefs.js removes the
 * attribute, seeds initial state from the <html> data-* attributes and
 * wires the controls. No JS = no visible widget = no dead controls
 * (FAIL02). Disclosure via native <details>/<summary> (APG disclosure
 * pattern — role and keyboard behaviour come from the platform).
 *
 * Every button has a >=24x24 hit target (p-2 + min-w-6/min-h-6 — A11Y05)
 * and consumes semantic role tokens only. Toggle buttons carry
 * aria-pressed; the font-size group is a labelled role=group of three
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
$salt_toggles = array(
	'contrast'  => array(
		'pref'  => 'contrast',
		'label' => __( 'High contrast', 'salt' ),
	),
	'grayscale' => array(
		'pref'  => 'grayscale',
		'label' => __( 'Grayscale', 'salt' ),
	),
	'dark_mode' => array(
		'pref'  => 'theme',
		'label' => __( 'Dark mode', 'salt' ),
	),
	'motion'    => array(
		'pref'  => 'motion',
		'label' => __( 'Reduce motion', 'salt' ),
	),
);

// >=24x24 hit target (A11Y05): p-2 padding + min-w-6/min-h-6 floor.
$salt_button = 'inline-flex min-h-6 min-w-6 items-center justify-center gap-2 rounded-md border border-border bg-surface p-2 text-sm text-ink aria-pressed:bg-brand aria-pressed:border-brand aria-pressed:text-on-brand focus-visible:outline-2 focus-visible:outline-offset-2';
?>
<div
	class="x-display-preferences"
	hidden
	data-salt-display-prefs
	<?php if ( $salt_args['motion_default'] ) : ?>
	data-motion-default="reduce"
	<?php endif; ?>
>
	<details class="x-display-preferences-panel rounded-md border border-border bg-surface text-ink">
		<summary class="x-display-preferences-summary flex min-h-6 min-w-6 cursor-pointer items-center gap-2 p-2 text-sm font-medium">
			<?php esc_html_e( 'Display preferences', 'salt' ); ?>
		</summary>
		<div class="x-display-preferences-controls flex flex-col gap-2 border-t border-border p-3">
			<?php foreach ( $salt_controls as $salt_control ) : ?>
				<?php if ( 'font_size' === $salt_control ) : ?>
			<div class="x-display-preferences-group flex items-center justify-between gap-4" role="group" aria-label="<?php echo esc_attr__( 'Font size', 'salt' ); ?>">
				<span class="x-display-preferences-label text-sm" aria-hidden="true"><?php esc_html_e( 'Font size', 'salt' ); ?></span>
				<span class="inline-flex gap-1">
					<button
						type="button"
						class="<?php echo esc_attr( $salt_button ); ?>"
						data-salt-pref="scale"
						data-salt-action="decrease"
						aria-label="<?php echo esc_attr__( 'Decrease font size', 'salt' ); ?>"
					><span aria-hidden="true">A&minus;</span></button>
					<button
						type="button"
						class="<?php echo esc_attr( $salt_button ); ?>"
						data-salt-pref="scale"
						data-salt-action="reset"
						aria-label="<?php echo esc_attr__( 'Reset font size', 'salt' ); ?>"
					><span aria-hidden="true">A</span></button>
					<button
						type="button"
						class="<?php echo esc_attr( $salt_button ); ?>"
						data-salt-pref="scale"
						data-salt-action="increase"
						aria-label="<?php echo esc_attr__( 'Increase font size', 'salt' ); ?>"
					><span aria-hidden="true">A+</span></button>
				</span>
			</div>
				<?php else : ?>
			<button
				type="button"
				class="<?php echo esc_attr( $salt_button ); ?> w-full justify-between"
				aria-pressed="false"
				data-salt-pref="<?php echo esc_attr( $salt_toggles[ $salt_control ]['pref'] ); ?>"
			><?php echo esc_html( $salt_toggles[ $salt_control ]['label'] ); ?></button>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</details>
</div>
