<?php
/**
 * The site logo (salt-contract markup/site-logo.json; spec §12a).
 *
 * ARGS CONTRACT (changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   light_id int    0         Attachment ID of the light-scheme logo.
 *   dark_id  int    0         Attachment ID of the dark-scheme logo; drawn
 *                              only beside a light one, and then the link
 *                              carries data-swaps (the stylesheet swaps them).
 *   text     string ''        Wordmark when no image resolves; falls back to
 *                              the site title. NEVER an empty brand slot.
 *   url      string ''        Link destination; '' means home_url( '/' ).
 *                              Drawn root-relative (salt_contract_url()).
 *   height   int    0         The drawn height in px; 0 for the stylesheet's
 *                              2rem (no style attribute).
 *   context  string 'header'  header|footer. Only the header's default-scheme
 *                              logo loads eagerly (SC-025): the light one,
 *                              since the server cannot know the visitor's
 *                              scheme. The footer's are always lazy.
 *   slots    array            Unused (reserved by salt_get_component()).
 *
 * The link is named by aria-label when an image is drawn and both images
 * are alt="". Its style="--logo-height" is the contract's markup; under the
 * theme's CSP it is applied by the nonce-stamped salt_header_runtime_css()
 * rule. An unresolvable attachment degrades to the wordmark (light) or to
 * light-only (dark), logged (FAIL01).
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
		'height'   => array(
			'type'    => 'int',
			'default' => 0,
		),
		'context'  => array(
			'type'    => 'string',
			'default' => 'header',
		),
		'slots'    => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'logo'
);

$salt_site_name = (string) get_bloginfo( 'name' );
$salt_home      = salt_contract_url( '' !== $salt_args['url'] ? $salt_args['url'] : home_url( '/' ) );
$salt_height    = $salt_args['height'] > 0 ? $salt_args['height'] : 32;

/**
 * One logo image as the contract's media, sized to the drawn height.
 *
 * @param int    $id      Attachment ID.
 * @param string $variant light|dark.
 * @param int    $height  Drawn height (px).
 * @return array<string,mixed>|null
 */
$salt_logo_image = static function ( int $id, string $variant, int $height ): ?array {
	if ( $id <= 0 ) {
		return null;
	}
	$full = wp_get_attachment_image_src( $id, 'full' );
	if ( ! is_array( $full ) || empty( $full[0] ) ) {
		salt_log( sprintf( 'x-logo: %s logo attachment %d is unresolvable — %s.', $variant, $id, 'light' === $variant ? 'rendered the wordmark' : 'rendered the light logo only' ) );
		return null;
	}
	$width   = (int) ( $full[1] ?? 0 );
	$natural = (int) ( $full[2] ?? 0 );
	$drawn   = $natural > 0 ? (int) round( $height * $width / $natural ) : $width;
	return salt_image_contract( $id, $drawn . 'px' );
};

$salt_light = $salt_logo_image( $salt_args['light_id'], 'light', $salt_height );
$salt_dark  = null !== $salt_light ? $salt_logo_image( $salt_args['dark_id'], 'dark', $salt_height ) : null;

$salt_text = trim( $salt_args['text'] );
if ( '' === $salt_text ) {
	$salt_text = $salt_site_name;
}

$salt_eager = 'header' === $salt_args['context'];

$salt_link = '<a class="salt-logo" href="' . esc_url( $salt_home ) . '"';
if ( null !== $salt_light ) {
	$salt_link .= ' aria-label="' . esc_attr( $salt_site_name ) . '"';
}
if ( null !== $salt_dark ) {
	$salt_link .= ' data-swaps';
}
if ( $salt_args['height'] > 0 ) {
	$salt_link .= ' style="' . esc_attr( '--logo-height: ' . $salt_args['height'] . 'px' ) . '"';
}
$salt_link .= '>';

$salt_img = static function ( array $image, string $variant, bool $eager ): string {
	$html = salt_image_contract_html(
		$image,
		false,
		array(
			'class' => 'salt-logo__image salt-logo__' . $variant,
			'alt'   => '',
		)
	);
	return $eager ? str_replace( ' loading="lazy"', ' loading="eager"', $html ) : $html;
};

echo $salt_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value escaped as the tag was built.
if ( null !== $salt_light ) {
	echo $salt_img( $salt_light, 'light', $salt_eager ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salt_image_contract_html() escapes every attribute.
	if ( null !== $salt_dark ) {
		echo $salt_img( $salt_dark, 'dark', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- salt_image_contract_html() escapes every attribute.
	}
} else {
	echo '<span class="salt-logo__wordmark">' . esc_html( $salt_text ) . '</span>';
}
echo '</a>';
