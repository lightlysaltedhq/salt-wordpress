<?php
/**
 * x-eyebrow — small uppercase kicker line above a heading.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   text    string  required  Eyebrow text (escaped here).
 *   classes string  ''        Extra utility classes appended to the class list.
 *
 * Render via salt_component( 'eyebrow', … ) / salt_get_component().
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
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'eyebrow'
);

$salt_classes = salt_classnames(
	array(
		'x-eyebrow',
		'text-sm font-semibold uppercase tracking-widest text-brand',
		$args['classes'],
	)
);
?>
<p class="<?php echo esc_attr( $salt_classes ); ?>"><?php echo esc_html( $args['text'] ); ?></p>
