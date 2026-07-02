<?php
/**
 * x-heading — semantic heading with decoupled visual size.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   text    string  required  Heading text (escaped here).
 *   level   int     required  Semantic level, clamped 1..6 (out of range is
 *                             clamped + salt_log()ged — never a fatal).
 *   size    string  ''        Visual size override: ''|xl|lg|md|sm. '' derives
 *                             from level: 1=>xl, 2=>lg, 3=>md, 4+=>sm.
 *   classes string  ''        Extra utility classes appended to the class list.
 *
 * Render via salt_component( 'heading', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'text'    => array(
			'type'     => 'string',
			'required' => true,
		),
		'level'   => array(
			'type'     => 'int',
			'required' => true,
			'default'  => 2,
		),
		'size'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'heading'
);

$salt_level = (int) $args['level'];
if ( $salt_level < 1 || $salt_level > 6 ) {
	salt_log( sprintf( 'x-heading: level %d out of range — clamped to 1..6.', $salt_level ) );
	$salt_level = max( 1, min( 6, $salt_level ) );
}

$salt_size_classes = array(
	'xl' => 'text-4xl md:text-5xl font-bold tracking-tight',
	'lg' => 'text-3xl md:text-4xl font-bold tracking-tight',
	'md' => 'text-xl md:text-2xl font-semibold',
	'sm' => 'text-lg font-semibold',
);

$salt_size = $args['size'];
if ( '' !== $salt_size && ! isset( $salt_size_classes[ $salt_size ] ) ) {
	salt_log( sprintf( 'x-heading: unknown size "%s" — deriving from level.', $salt_size ) );
	$salt_size = '';
}
if ( '' === $salt_size ) {
	$salt_size = match ( $salt_level ) {
		1       => 'xl',
		2       => 'lg',
		3       => 'md',
		default => 'sm',
	};
}

$salt_classes = salt_classnames(
	array(
		'x-heading',
		$salt_size_classes[ $salt_size ],
		$args['classes'],
	)
);

printf(
	'<h%1$d class="%2$s">%3$s</h%1$d>',
	(int) $salt_level,
	esc_attr( $salt_classes ),
	esc_html( $args['text'] )
);
