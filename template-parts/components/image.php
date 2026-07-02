<?php
/**
 * x-image — THE single authority for image markup (spec §8, review AMBIG01).
 *
 * AMBIG01 (frozen resolution): the DEFAULT output is a PLAIN
 * <img srcset sizes>; a <picture> element is emitted ONLY when the caller
 * opts in via picture_sources. No other code path in the theme may
 * hand-roll an <img>.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   image_id        int    0        Attachment ID, resolved via salt_image_data().
 *   src             string ''       Manual src (used only when image_id is 0).
 *   srcset          string ''       Manual srcset (used only when image_id is 0).
 *   sizes_context   string 'card'   hero|full|half|card|gallery (salt_image_sizes()).
 *   alt             string required Alt text. EMPTY STRING = decorative — alt="" is still emitted.
 *   width           int    0        Intrinsic width (CLS); >0 overrides the resolved value.
 *   height          int    0        Intrinsic height (CLS); >0 overrides the resolved value.
 *   priority        bool   false    LCP image: loading="eager" fetchpriority="high" (never lazy).
 *   picture_sources array  []       Opt-in <picture>: each entry
 *                                   array{srcset: string, type?: string, media?: string}.
 *   classes         string ''       Extra classes appended to x-image.
 *   slots           array  []       Reserved by salt_get_component() (unused here).
 *
 * Alt precedence: an explicit non-empty alt arg ALWAYS wins; when the alt
 * arg is '' and the attachment resolves with a stored alt, the stored alt
 * is used; otherwise the image is decorative (alt="").
 *
 * Failure paths (fail loud, never a broken <img> — FAIL01/DATA05):
 * - image_id > 0 but unresolvable → renders ONLY the HTML comment
 *   '<!-- salt: x-image missing attachment -->' (salt_image_data() logged).
 * - image_id 0 AND empty src → renders nothing, salt_log().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is the WordPress-core load_template() template variable, normalised in place.
	$args ?? array(),
	array(
		'image_id'        => array(
			'type'    => 'int',
			'default' => 0,
		),
		'src'             => array(
			'type'    => 'string',
			'default' => '',
		),
		'srcset'          => array(
			'type'    => 'string',
			'default' => '',
		),
		'sizes_context'   => array(
			'type'    => 'string',
			'default' => 'card',
		),
		'alt'             => array(
			'type'     => 'string',
			'required' => true,
		),
		'width'           => array(
			'type'    => 'int',
			'default' => 0,
		),
		'height'          => array(
			'type'    => 'int',
			'default' => 0,
		),
		'priority'        => array(
			'type'    => 'bool',
			'default' => false,
		),
		'picture_sources' => array(
			'type'    => 'array',
			'default' => array(),
		),
		'classes'         => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots'           => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'image'
);

$salt_data = null;
if ( $args['image_id'] > 0 ) {
	$salt_data = salt_image_data( $args['image_id'], $args['sizes_context'] );
	if ( null === $salt_data ) {
		// Never a broken img: salt_image_data() already logged (DATA05).
		echo '<!-- salt: x-image missing attachment -->';
		return;
	}
}

$salt_src    = null !== $salt_data ? $salt_data['src'] : $args['src'];
$salt_srcset = null !== $salt_data ? $salt_data['srcset'] : $args['srcset'];

if ( '' === $salt_src ) {
	salt_log( 'x-image: image_id 0 and empty src — rendered nothing.' );
	return;
}

$salt_alt = $args['alt'];
if ( '' === $salt_alt && null !== $salt_data ) {
	$salt_alt = $salt_data['alt'];
}

$salt_width  = $args['width'] > 0 ? $args['width'] : ( null !== $salt_data ? $salt_data['width'] : 0 );
$salt_height = $args['height'] > 0 ? $args['height'] : ( null !== $salt_data ? $salt_data['height'] : 0 );

// Every fragment below is escaped where it is concatenated.
$salt_img = '<img src="' . esc_url( $salt_src ) . '"';
if ( '' !== $salt_srcset ) {
	$salt_img .= ' srcset="' . esc_attr( $salt_srcset ) . '"';
	$salt_img .= ' sizes="' . esc_attr( salt_image_sizes( $args['sizes_context'] ) ) . '"';
}
$salt_img .= ' alt="' . esc_attr( $salt_alt ) . '"';
if ( $salt_width > 0 ) {
	$salt_img .= ' width="' . (int) $salt_width . '"';
}
if ( $salt_height > 0 ) {
	$salt_img .= ' height="' . (int) $salt_height . '"';
}
$salt_img .= ' class="' . esc_attr( salt_classnames( array( 'x-image', $args['classes'] ) ) ) . '"';
$salt_img .= $args['priority']
	? ' loading="eager" fetchpriority="high" decoding="async"'
	: ' loading="lazy" decoding="async"';
$salt_img .= '>';

if ( array() === $args['picture_sources'] ) {
	// AMBIG01: the default output is a plain <img> — no <picture>.
	echo $salt_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped where concatenated above.
	return;
}

echo "<picture>\n";
foreach ( $args['picture_sources'] as $salt_source ) {
	if ( ! is_array( $salt_source ) || empty( $salt_source['srcset'] ) || ! is_string( $salt_source['srcset'] ) ) {
		salt_log( 'x-image: picture source without a srcset — skipped.' );
		continue;
	}
	$salt_source_tag = '<source srcset="' . esc_attr( $salt_source['srcset'] ) . '"';
	if ( ! empty( $salt_source['type'] ) && is_string( $salt_source['type'] ) ) {
		$salt_source_tag .= ' type="' . esc_attr( $salt_source['type'] ) . '"';
	}
	if ( ! empty( $salt_source['media'] ) && is_string( $salt_source['media'] ) ) {
		$salt_source_tag .= ' media="' . esc_attr( $salt_source['media'] ) . '"';
	}
	$salt_source_tag .= '>';
	echo "\t" . $salt_source_tag . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped where concatenated above.
}
echo "\t" . $salt_img . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped where concatenated above.
echo '</picture>';
