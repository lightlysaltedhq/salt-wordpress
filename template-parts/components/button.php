<?php
/**
 * x-button — the shared button/link component.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   label   string  required  Visible button text (escaped here).
 *   url     string  required  Destination (esc_url at print; javascript:
 *                             and friends neutralise to an empty href).
 *   style   string  'primary' primary|secondary|ghost|link-arrow
 *                             (semantic tokens only).
 *   size    string  'md'      sm|md.
 *   target  string  ''        '' or '_blank' only — anything else is
 *                             dropped to '' and salt_log()ged. _blank adds
 *                             rel="noopener noreferrer".
 *   classes string  ''        Extra utility classes appended verbatim.
 *   slots   array             Unused (reserved by salt_get_component()).
 *
 * Render via salt_component( 'button', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'label'   => array(
			'type'     => 'string',
			'required' => true,
		),
		'url'     => array(
			'type'     => 'string',
			'required' => true,
		),
		'style'   => array(
			'type'    => 'string',
			'default' => 'primary',
		),
		'size'    => array(
			'type'    => 'string',
			'default' => 'md',
		),
		'target'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots'   => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'button'
);

$salt_style_classes = array(
	'primary'    => 'bg-brand text-on-brand hover:bg-brand-strong',
	'secondary'  => 'border border-brand text-brand hover:bg-brand-tint',
	'ghost'      => 'text-brand hover:bg-surface-alt',
	'link-arrow' => 'text-brand underline-offset-4 hover:underline',
);

$salt_size_classes = array(
	'sm' => 'px-3 py-1.5 text-sm',
	'md' => 'px-5 py-2.5',
);

if ( ! isset( $salt_style_classes[ $salt_args['style'] ] ) ) {
	salt_log( sprintf( 'x-button: unknown style "%s" — using primary.', $salt_args['style'] ) );
	$salt_args['style'] = 'primary';
}

if ( ! isset( $salt_size_classes[ $salt_args['size'] ] ) ) {
	salt_log( sprintf( 'x-button: unknown size "%s" — using md.', $salt_args['size'] ) );
	$salt_args['size'] = 'md';
}

if ( ! in_array( $salt_args['target'], array( '', '_blank' ), true ) ) {
	salt_log( sprintf( 'x-button: invalid target "%s" — dropped.', $salt_args['target'] ) );
	$salt_args['target'] = '';
}

$salt_classes = salt_classnames(
	array(
		'x-button',
		'x-button-' . $salt_args['style'],
		'inline-flex items-center justify-center gap-2 rounded-md font-medium focus-visible:outline-2 focus-visible:outline-offset-2',
		$salt_style_classes[ $salt_args['style'] ],
		$salt_size_classes[ $salt_args['size'] ],
		$salt_args['classes'],
	)
);
?>
<a class="<?php echo esc_attr( $salt_classes ); ?>" href="<?php echo esc_url( $salt_args['url'] ); ?>"<?php echo '_blank' === $salt_args['target'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $salt_args['label'] ) . ( 'link-arrow' === $salt_args['style'] ? '<span aria-hidden="true">&#8594;</span>' : '' ); ?></a>
