<?php
/**
 * x-process-step — one step in the Process / Steps section (spec Appendix B).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   number  string  required  Position-derived step number, e.g. '1' (escaped
 *                             here). Rendered aria-hidden — the parent <ol>
 *                             carries the semantic numbering (A11Y01).
 *   title   string  required  Step title (escaped inside x-heading).
 *   text    string  ''        Step description (escaped here).
 *   icon    string  ''        Optional built-in icon name (x-icon).
 *   level   int     3         Title heading level; the section passes its
 *                             own level + 1 (A11Y01).
 *
 *   classes string  ''        Extra utility classes appended to the class list.
 *
 * Render via salt_component( 'process-step', … ) / salt_get_component().
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
		'title'   => array(
			'type'     => 'string',
			'required' => true,
		),
		'text'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'icon'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'level'   => array(
			'type'    => 'int',
			'default' => 3,
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'process-step'
);

if ( '' === trim( $args['title'] ) ) {
	return; // Nothing to show — the section skips these, but stay safe (COMPL02).
}

$salt_classes = salt_classnames(
	array(
		'x-process-step flex flex-col gap-2',
		$args['classes'],
	)
);
?>
<div class="<?php echo esc_attr( $salt_classes ); ?>">
	<div class="x-process-step-marker flex items-center gap-3">
		<span class="x-process-step-number flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-on-brand" aria-hidden="true"><?php echo esc_html( $args['number'] ); ?></span>
		<?php
		if ( '' !== trim( $args['icon'] ) ) {
			salt_component(
				'icon',
				array(
					'name' => $args['icon'],
					'size' => 'md',
				)
			);
		}
		?>
	</div>
	<?php
	salt_component(
		'heading',
		array(
			'text'    => $args['title'],
			'level'   => $args['level'],
			'size'    => 'sm',
			'classes' => 'x-process-step-title',
		)
	);
	if ( '' !== trim( $args['text'] ) ) :
		?>
	<p class="x-process-step-text text-ink-muted"><?php echo esc_html( $args['text'] ); ?></p>
	<?php endif; ?>
</div>
