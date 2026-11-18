<?php
/**
 * x-card-service — the service card (extends x-card, spec §12/Appendix B).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   title   string  required  Service title (escaped by x-card).
 *   url     string  ''        Permalink; '' renders an unlinked card.
 *   summary string  ''        Plain-text one-liner (escaped by x-card).
 *   icon    string  ''        Bundled icon name rendered via x-icon; unknown
 *                             or empty names render no icon (x-icon logs).
 *                             Drawn only when the card has no image.
 *   image_id int    0         The service's featured image, rendered ONLY via
 *                             x-image (salt-contract collections/services.json,
 *                             display: image from featuredImage).
 *   level   int     3         Card title heading level (sections pass the
 *                             derived section level + 1).
 *   classes string  ''        Extra utility classes forwarded to x-card.
 *
 * Render via salt_component( 'card-service', … ) / salt_get_component().
 * Used by: Services section (§10 #4), service archive loop (content/card-service).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'title'    => array(
			'type'     => 'string',
			'required' => true,
		),
		'url'      => array(
			'type'    => 'string',
			'default' => '',
		),
		'summary'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'icon'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'image_id' => array(
			'type'    => 'int',
			'default' => 0,
		),
		'level'    => array(
			'type'    => 'int',
			'default' => 3,
		),
		'classes'  => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'card-service'
);

$salt_media = '';
if ( $args['image_id'] > 0 ) {
	// Images ONLY via x-image (§8): '' alt falls back to the stored
	// attachment alt, else decorative — the linked title carries the name.
	$salt_media = salt_get_component(
		'image',
		array(
			'image_id'      => $args['image_id'],
			'alt'           => '',
			'sizes_context' => 'card',
			'classes'       => 'aspect-[4/3] w-full object-cover',
		)
	);
}

$salt_icon_html = '';
if ( '' === $salt_media && '' !== $args['icon'] ) {
	$salt_icon_html = salt_get_component(
		'icon',
		array(
			'name' => $args['icon'],
			'size' => 'lg',
		)
	);
}

salt_component(
	'card',
	array(
		'title'   => $args['title'],
		'url'     => $args['url'],
		'level'   => $args['level'],
		'body'    => $args['summary'],
		'media'   => '' !== $salt_icon_html
			? '<div class="x-card-service-icon px-6 pt-6 text-brand">' . $salt_icon_html . '</div>'
			: $salt_media,
		'classes' => salt_classnames( array( 'x-card-service', $args['classes'] ) ),
	)
);
