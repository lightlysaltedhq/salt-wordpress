<?php
/**
 * The contract's section wrapper (salt-contract markup/section.json), for
 * the sections whose markup is the contract's (collection-showcase and
 * carousel since unit 8). Every other section keeps x-section
 * (components/section.php) until it moves (M4).
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
	'style'             => 'none' !== $salt_args['spacing'] ? '--salt-section-space: var(--space-section-' . $salt_args['spacing'] . ')' : null,
);

echo '<' . $salt_element; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 'section' or 'div'.
foreach ( $salt_attributes as $salt_name => $salt_value ) {
	if ( null !== $salt_value ) {
		echo ' ' . esc_attr( $salt_name ) . '="' . esc_attr( $salt_value ) . '"';
	}
}
echo '>';

if ( null !== $salt_background ) {
	// The nine positions as keyword pairs; focal-point is the stored focal
	// point, and WordPress stores none, so it draws the stylesheet's centre
	// (markup/media.json, WordPress owes).
	$salt_positions = array(
		'top-left'     => 'top left',
		'top'          => 'top',
		'top-right'    => 'top right',
		'left'         => 'left',
		'centre'       => 'center',
		'right'        => 'right',
		'bottom-left'  => 'bottom left',
		'bottom'       => 'bottom',
		'bottom-right' => 'bottom right',
	);
	$salt_style     = 'object-fit: ' . $salt_background['fit'];
	if ( isset( $salt_positions[ $salt_background['position'] ] ) ) {
		$salt_style .= '; object-position: ' . $salt_positions[ $salt_background['position'] ];
	}
	echo salt_image_contract_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
		$salt_background['image'],
		$salt_args['background_priority'],
		array(
			'class'       => 'salt-section__media',
			'alt'         => '',
			'aria-hidden' => 'true',
			'style'       => $salt_style,
		)
	);
	if ( $salt_background['scrim'] ) {
		echo '<div class="salt-section__scrim" style="' . esc_attr( '--salt-scrim-alpha: var(--scrim-' . $salt_background['scrimStrength'] . ')' ) . '"></div>';
	}
}

echo '<div class="salt-section__content">';
echo $salt_args['slots']['default'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The block's HTML, escaped where it was drawn.
echo '</div></' . $salt_element . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 'section' or 'div'.
