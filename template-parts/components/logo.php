<?php
/**
 * x-logo — the site brand link (spec §12a).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   light_id int    0   Attachment ID of the light-surface logo.
 *   dark_id  int    0   Attachment ID of the dark-mode logo. Rendered
 *                       aria-hidden next to the light one; the .has-dark
 *                       class (set only when BOTH resolve) drives the CSS
 *                       swap in app.css ([data-theme="dark"]).
 *   text     string ''  Text fallback when no logo resolves; falls back to
 *                       the site title. NEVER an empty brand slot.
 *   url      string ''  Link destination; '' means home_url( '/' ).
 *   slots    array      Unused (reserved by salt_get_component()).
 *
 * An unresolvable attachment ID degrades to the text fallback (light) or
 * to light-only (dark) — logged, never blank (FAIL01). Rendered height is
 * option-driven via --logo-height (salt_header_runtime_css()).
 *
 * Render via salt_component( 'logo', salt_logo_args() ).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'light_id' => array(
			'type'    => 'int',
			'default' => 0,
		),
		'dark_id'  => array(
			'type'    => 'int',
			'default' => 0,
		),
		'text'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'url'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots'    => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'logo'
);

$salt_site_name = (string) get_bloginfo( 'name' );
$salt_home      = '' !== $salt_args['url'] ? $salt_args['url'] : home_url( '/' );

$salt_light = false;
if ( $salt_args['light_id'] > 0 ) {
	$salt_light = wp_get_attachment_image_src( $salt_args['light_id'], 'full' );
	if ( false === $salt_light ) {
		salt_log( sprintf( 'x-logo: light logo attachment %d is unresolvable — rendered the text fallback.', $salt_args['light_id'] ) );
	}
}

$salt_dark = false;
if ( is_array( $salt_light ) && $salt_args['dark_id'] > 0 ) {
	$salt_dark = wp_get_attachment_image_src( $salt_args['dark_id'], 'full' );
	if ( false === $salt_dark ) {
		salt_log( sprintf( 'x-logo: dark logo attachment %d is unresolvable — rendered the light logo only.', $salt_args['dark_id'] ) );
	}
}

$salt_text = trim( $salt_args['text'] );
if ( '' === $salt_text ) {
	$salt_text = $salt_site_name;
}

$salt_classes = salt_classnames(
	array(
		'x-logo',
		'inline-flex items-center',
		is_array( $salt_dark ) ? 'has-dark' : '',
	)
);
?>
<a class="<?php echo esc_attr( $salt_classes ); ?>" href="<?php echo esc_url( $salt_home ); ?>">
	<?php if ( is_array( $salt_light ) ) : ?>
		<img class="x-logo-light" src="<?php echo esc_url( $salt_light[0] ); ?>" width="<?php echo esc_attr( (string) $salt_light[1] ); ?>" height="<?php echo esc_attr( (string) $salt_light[2] ); ?>" alt="<?php echo esc_attr( $salt_site_name ); ?>">
		<?php if ( is_array( $salt_dark ) ) : ?>
			<img class="x-logo-dark" src="<?php echo esc_url( $salt_dark[0] ); ?>" width="<?php echo esc_attr( (string) $salt_dark[1] ); ?>" height="<?php echo esc_attr( (string) $salt_dark[2] ); ?>" alt="" aria-hidden="true">
		<?php endif; ?>
	<?php else : ?>
		<span class="x-logo-text text-xl font-bold"><?php echo esc_html( $salt_text ); ?></span>
	<?php endif; ?>
</a>
