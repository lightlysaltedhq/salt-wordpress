<?php
/**
 * x-section — the shared section wrapper (spec §11).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   name       string  required  Section slug (data attribute + class hook).
 *   spacing    string  'md'      none|sm|md|lg.
 *   background string  'surface' surface|surface-alt|brand-tint|inverse (semantic tokens only).
 *   container  string  'default' full|wide|default|narrow.
 *   anchor     string  ''        Pre-sanitised anchor ID (salt_anchor_id()).
 *   divider    bool    false     Rule line at the section boundary.
 *   index      int     0         Document position (from the section loop).
 *   slots      array             'default' => pre-rendered body HTML.
 *
 * Render via salt_section() (which derives the heading level and sanitises
 * the anchor) — not directly.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args(
	$args ?? array(),
	array(
		'name'       => array(
			'type'     => 'string',
			'required' => true,
		),
		'spacing'    => array(
			'type'    => 'string',
			'default' => 'md',
		),
		'background' => array(
			'type'    => 'string',
			'default' => 'surface',
		),
		'container'  => array(
			'type'    => 'string',
			'default' => 'default',
		),
		'anchor'     => array(
			'type'    => 'string',
			'default' => '',
		),
		'divider'    => array(
			'type'    => 'bool',
			'default' => false,
		),
		'index'      => array(
			'type'    => 'int',
			'default' => 0,
		),
		'slots'      => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'section'
);

$salt_spacing_classes = array(
	'none' => '',
	'sm'   => 'py-8 md:py-12',
	'md'   => 'py-14 md:py-20',
	'lg'   => 'py-20 md:py-32',
);

$salt_background_classes = array(
	'surface'     => 'bg-surface text-ink',
	'surface-alt' => 'bg-surface-alt text-ink',
	'brand-tint'  => 'bg-brand-tint text-ink',
	'inverse'     => 'bg-surface-inverse text-ink-inverse',
);

$salt_container_classes = array(
	'full'    => 'w-full',
	'wide'    => 'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8',
	'default' => 'mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8',
	'narrow'  => 'mx-auto w-full max-w-3xl px-4 sm:px-6 lg:px-8',
);

foreach ( array(
	'spacing'    => $salt_spacing_classes,
	'background' => $salt_background_classes,
	'container'  => $salt_container_classes,
) as $salt_arg => $salt_map ) {
	if ( ! isset( $salt_map[ $args[ $salt_arg ] ] ) ) {
		salt_log( sprintf( 'x-section: unknown %s "%s" — using default.', $salt_arg, $args[ $salt_arg ] ) );
		$args[ $salt_arg ] = array(
			'spacing'    => 'md',
			'background' => 'surface',
			'container'  => 'default',
		)[ $salt_arg ];
	}
}

$salt_classes = salt_classnames(
	array(
		'x-section',
		'section-' . $args['name'],
		$salt_spacing_classes[ $args['spacing'] ],
		$salt_background_classes[ $args['background'] ],
	)
);
?>
<section<?php echo '' !== $args['anchor'] ? ' id="' . esc_attr( $args['anchor'] ) . '"' : ''; ?> class="<?php echo esc_attr( $salt_classes ); ?>" data-section="<?php echo esc_attr( $args['name'] ); ?>">
	<div class="<?php echo esc_attr( $salt_container_classes[ $args['container'] ] ); ?>">
		<?php echo $args['slots']['default'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Slot HTML is pre-rendered by the section partial, which escapes at print. ?>
	</div>
	<?php if ( $args['divider'] ) : ?>
		<hr class="x-section-divider mx-auto w-full max-w-5xl border-border">
	<?php endif; ?>
</section>
