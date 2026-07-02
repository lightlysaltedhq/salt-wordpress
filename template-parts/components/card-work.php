<?php
/**
 * x-card-work — the work/portfolio card (extends x-card; spec §12/Appendix B).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   title    string  required  Work item title (escaped by x-card).
 *   url      string  ''        Permalink; non-empty makes the title a
 *                              stretched link (x-card behaviour).
 *   client   string  ''        Client name — rendered as the card meta line.
 *   summary  string  ''        Plain-text card blurb (escaped by x-card).
 *   image_id int     0         Thumbnail attachment ID, rendered ONLY via
 *                              x-image (decorative alt fallback: the stored
 *                              attachment alt is used when present).
 *   level    int     3         Card title heading level (arrives from the
 *                              section wrapper: $level + 1 — A11Y01).
 *   classes  string  ''        Extra utility classes appended to the card.
 *   slots    array             Unused (reserved by salt_get_component()).
 *
 * Render via salt_component( 'card-work', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
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
		'client'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'summary'  => array(
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
		'slots'    => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'card-work'
);

$salt_media = '';
if ( $salt_args['image_id'] > 0 ) {
	// Images ONLY via x-image (§8): '' alt falls back to the stored
	// attachment alt, else decorative — the linked title carries the name.
	$salt_media = salt_get_component(
		'image',
		array(
			'image_id'      => $salt_args['image_id'],
			'alt'           => '',
			'sizes_context' => 'card',
			'classes'       => 'aspect-[4/3] w-full object-cover',
		)
	);
}

$salt_meta = '';
if ( '' !== $salt_args['client'] ) {
	$salt_meta = '<span class="x-card-work-client">' . esc_html( $salt_args['client'] ) . '</span>';
}

salt_component(
	'card',
	array(
		'title'   => $salt_args['title'],
		'url'     => $salt_args['url'],
		'level'   => $salt_args['level'],
		'media'   => $salt_media,
		'meta'    => $salt_meta,
		'body'    => $salt_args['summary'],
		'classes' => salt_classnames( array( 'x-card-work', $salt_args['classes'] ) ),
	)
);
