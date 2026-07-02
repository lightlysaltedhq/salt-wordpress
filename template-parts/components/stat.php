<?php
/**
 * x-stat — atomic stat / counter (spec Appendix B; used by Stats §10 #7
 * and Work results).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   number  string  required  The big figure, e.g. '45' or '+45%' (escaped here).
 *   suffix  string  ''        Optional unit appended to the number, e.g. '%',
 *                             '+', 'k' (escaped here).
 *   label   string  ''        Short description below the number (escaped here).
 *
 *   classes string  ''        Extra utility classes appended to the class list.
 *
 * Markup: a <p> pair — the big number (span + optional suffix span) and the
 * label. Deliberately NOT a heading: stats carry no document-outline weight
 * (A11Y01).
 *
 * Render via salt_component( 'stat', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'number'  => array(
			'type'     => 'string',
			'required' => true,
		),
		'suffix'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'label'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'stat'
);

if ( '' === trim( $args['number'] ) ) {
	return; // Nothing to show — the section skips these, but stay safe (COMPL02).
}

$salt_classes = salt_classnames(
	array(
		'x-stat flex flex-col gap-1',
		$args['classes'],
	)
);

$salt_value = '<span class="x-stat-number">' . esc_html( $args['number'] ) . '</span>';
if ( '' !== $args['suffix'] ) {
	$salt_value .= '<span class="x-stat-suffix">' . esc_html( $args['suffix'] ) . '</span>';
}
?>
<div class="<?php echo esc_attr( $salt_classes ); ?>">
	<p class="x-stat-value text-4xl font-bold text-brand"><?php echo $salt_value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from esc_html() parts. ?></p>
	<?php if ( '' !== trim( $args['label'] ) ) : ?>
	<p class="x-stat-label text-sm text-ink-muted"><?php echo esc_html( $args['label'] ); ?></p>
	<?php endif; ?>
</div>
