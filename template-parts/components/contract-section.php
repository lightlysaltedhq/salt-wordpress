<?php
/**
 * The contract's section wrapper (salt-contract markup/section.json), for
 * every parent section (EP-72 M4). components/section.php, the x-section
 * band, is left only for a child theme's own whole sections and is
 * deprecated (docs/contracts/OVERRIDES.md, tier 5).
 *
 * Render via salt_section() with `contract` true, which settles the id,
 * the heading level, the track and the collapse — not directly.
 *
 * ARGS:
 *   anchor              string  The settled id (never empty).
 *   labelled            bool    The block draws a heading: a section named
 *                               by <anchor>__heading, else a div.
 *   tone, tone_dark     string  surface|surface-alt|brand-tint|surface-inverse.
 *   spacing             string  none|sm|md|lg.
 *   width               string  full|wide|default|narrow.
 *   track               string  data-track (<section id>-<n>).
 *   collapse_top        bool    data-collapse-top.
 *   divider             bool    data-divider.
 *   background_image    array|null salt_section_background(), or null.
 *   background_priority bool    The background is the page's priority image.
 *   slots               array   'default' => the block's HTML.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'anchor'              => array( 'type' => 'string' ),
		'labelled'            => array( 'type' => 'bool' ),
		'tone'                => array(
			'type'    => 'string',
			'default' => 'surface',
		),
		'tone_dark'           => array(
			'type'    => 'string',
			'default' => 'surface',
		),
		'spacing'             => array(
			'type'    => 'string',
			'default' => 'md',
		),
		'width'               => array(
			'type'    => 'string',
			'default' => 'default',
		),
		'track'               => array( 'type' => 'string' ),
		'collapse_top'        => array( 'type' => 'bool' ),
		'divider'             => array( 'type' => 'bool' ),
		'background_image'    => array(
			'type'    => 'mixed',
			'default' => null,
		),
		'background_priority' => array( 'type' => 'bool' ),
		'slots'               => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'contract-section'
);

$salt_background = is_array( $salt_args['background_image'] ) ? $salt_args['background_image'] : null;
$salt_element    = $salt_args['labelled'] ? 'section' : 'div';

$salt_attributes = array(
	'class'             => 'salt-section',
	'id'                => $salt_args['anchor'],
	'aria-labelledby'   => $salt_args['labelled'] ? $salt_args['anchor'] . '__heading' : null,
	'data-tone'         => $salt_args['tone'],
	'data-tone-dark'    => $salt_args['tone_dark'],
	'data-spacing'      => $salt_args['spacing'],
	'data-width'        => $salt_args['width'],
	'data-collapse-top' => $salt_args['collapse_top'] ? '' : null,
	'data-divider'      => $salt_args['divider'] ? '' : null,
	'data-media'        => null !== $salt_background ? '' : null,
	'data-track'        => $salt_args['track'],
);

echo '<' . $salt_element; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 'section' or 'div'.
foreach ( $salt_attributes as $salt_name => $salt_value ) {
	if ( null !== $salt_value ) {
		echo ' ' . esc_attr( $salt_name ) . '="' . esc_attr( $salt_value ) . '"';
	}
}
echo '>';

if ( null !== $salt_background ) {
	// The fit and position as data attributes, never a style
	// (section#background-position, section#no-inline-style): the nine
	// positions as their steps; focal-point as the stored focal point,
	// already snapped (salt_image_focal_point()), and with none neither
	// attribute, for the stylesheet's centre.
	$salt_positions = array(
		'top-left'     => array( 0, 0 ),
		'top'          => array( 50, 0 ),
		'top-right'    => array( 100, 0 ),
		'left'         => array( 0, 50 ),
		'centre'       => array( 50, 50 ),
		'right'        => array( 100, 50 ),
		'bottom-left'  => array( 0, 100 ),
		'bottom'       => array( 50, 100 ),
		'bottom-right' => array( 100, 100 ),
	);
	$salt_focal     = $salt_background['image']['focalPoint'] ?? null;
	$salt_xy        = $salt_positions[ $salt_background['position'] ] ?? ( 'focal-point' === $salt_background['position'] && is_array( $salt_focal ) ? array( $salt_focal['x'], $salt_focal['y'] ) : null );
	echo salt_image_contract_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
		$salt_background['image'],
		$salt_args['background_priority'],
		array(
			'class'        => 'salt-section__media',
			'alt'          => '',
			'aria-hidden'  => 'true',
			'data-fit'     => $salt_background['fit'],
			'data-focal-x' => null !== $salt_xy ? (string) $salt_xy[0] : null,
			'data-focal-y' => null !== $salt_xy ? (string) $salt_xy[1] : null,
		)
	);
	if ( $salt_background['scrim'] ) {
		echo '<div class="salt-section__scrim" data-strength="' . esc_attr( $salt_background['scrimStrength'] ) . '"></div>';
	}
}

echo '<div class="salt-section__content">';
echo $salt_args['slots']['default'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The block's HTML, escaped where it was drawn.
echo '</div></' . $salt_element . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 'section' or 'div'.
