<?php
/**
 * Fixture child VIEW of the call-to-action section (same layout slug): tier
 * "view" in salt-overrides.json. Draws the parent resolver's props only.
 *
 * $args:
 *   props  array{ heading: string|null, body: string|null,
 *                 buttons: array<int, array{label: string, href: string, style: string, newTab: bool}> }
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Grain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grain_props = $args['props'];
$grain_level = (int) $args['plan']['headingLevel'];
?>
<div class="grain-cta">
<?php if ( null !== $grain_props['heading'] ) : ?>
	<h<?php echo esc_attr( (string) $grain_level ); ?>><?php echo esc_html( $grain_props['heading'] ); ?></h<?php echo esc_attr( (string) $grain_level ); ?>>
<?php endif; ?>
<?php foreach ( $grain_props['buttons'] as $grain_button ) : ?>
	<a class="grain-cta__button" href="<?php echo esc_url( $grain_button['href'] ); ?>"><?php echo esc_html( $grain_button['label'] ); ?></a>
<?php endforeach; ?>
</div>
