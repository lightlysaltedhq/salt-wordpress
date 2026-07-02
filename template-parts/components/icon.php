<?php
/**
 * x-icon — inline SVG icon (bundled set or pre-sanitised upload markup).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   name       string ''    Built-in icon (assets/src/icons/{name}.svg). Takes precedence over svg.
 *   svg        string ''    Pre-sanitised upload markup — the CALLER must pass it through
 *                           salt_sanitize_svg(); this partial runs it through salt_sanitize_svg()
 *                           AGAIN as defence-in-depth (SEC04).
 *   size       string 'md'  sm|md|lg => size-4|size-6|size-8.
 *   decorative bool   true  aria-hidden="true"; screen readers skip the icon.
 *   label      string ''    Accessible name — REQUIRED when decorative is false
 *                           (role="img" aria-label). Missing label degrades to decorative + log.
 *
 * Both name and svg empty => renders nothing.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template-args variable, not a theme global.
$args = salt_require_args(
	$args ?? array(),
	array(
		'name'       => array(
			'type'    => 'string',
			'default' => '',
		),
		'svg'        => array(
			'type'    => 'string',
			'default' => '',
		),
		'size'       => array(
			'type'    => 'string',
			'default' => 'md',
		),
		'decorative' => array(
			'type'    => 'bool',
			'default' => true,
		),
		'label'      => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'icon'
);

$salt_size_classes = array(
	'sm' => 'size-4',
	'md' => 'size-6',
	'lg' => 'size-8',
);

if ( ! isset( $salt_size_classes[ $args['size'] ] ) ) {
	salt_log( sprintf( 'x-icon: unknown size "%s" — using default.', $args['size'] ) );
	$args['size'] = 'md';
}

// name takes precedence; svg (upload markup) is re-sanitised as defence-in-depth (SEC04).
$salt_svg = '';
if ( '' !== $args['name'] ) {
	$salt_svg = salt_icon_svg( $args['name'] );
} elseif ( '' !== $args['svg'] ) {
	$salt_svg = salt_sanitize_svg( $args['svg'] );
}

if ( '' === $salt_svg ) {
	return;
}

$salt_decorative = $args['decorative'];
if ( ! $salt_decorative && '' === $args['label'] ) {
	salt_log( 'x-icon: non-decorative icon requires a label — degraded to decorative (A11Y).' );
	$salt_decorative = true;
}

$salt_classes = salt_classnames(
	array(
		'x-icon',
		'inline-flex',
		$salt_size_classes[ $args['size'] ],
	)
);
?>
<span class="<?php echo esc_attr( $salt_classes ); ?>"<?php echo $salt_decorative ? ' aria-hidden="true"' : ' role="img" aria-label="' . esc_attr( $args['label'] ) . '"'; ?>><?php echo $salt_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-bundled icon markup or salt_sanitize_svg()-cleaned upload markup (SEC04). ?></span>
