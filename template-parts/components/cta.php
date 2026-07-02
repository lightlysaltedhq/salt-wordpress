<?php
/**
 * x-cta — heading + supporting text + a row of x-buttons.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   heading string  ''       Optional heading text (escaped here).
 *   text    string  ''       Optional supporting copy (escaped here).
 *   buttons array   array()  Each entry: array{ label: string, url: string,
 *                            style?: string }. Malformed entries (missing
 *                            label/url) are skipped and salt_log()ged.
 *   level   int     2        Heading level. NO heading-claim logic here —
 *                            the level ARRIVES from the section wrapper
 *                            (salt_section() computes it before the body
 *                            renders; AMBIG03/A11Y01).
 *   align   string  'center' left|center.
 *   slots   array            Unused (reserved by salt_get_component()).
 *
 * Render via salt_component( 'cta', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'heading' => array(
			'type'    => 'string',
			'default' => '',
		),
		'text'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'buttons' => array(
			'type'    => 'array',
			'default' => array(),
		),
		'level'   => array(
			'type'    => 'int',
			'default' => 2,
		),
		'align'   => array(
			'type'    => 'string',
			'default' => 'center',
		),
		'slots'   => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'cta'
);

$salt_align_classes = array(
	'left'   => array(
		'wrapper' => 'text-start',
		'row'     => 'justify-start',
	),
	'center' => array(
		'wrapper' => 'text-center',
		'row'     => 'justify-center',
	),
);

if ( ! isset( $salt_align_classes[ $salt_args['align'] ] ) ) {
	salt_log( sprintf( 'x-cta: unknown align "%s" — using center.', $salt_args['align'] ) );
	$salt_args['align'] = 'center';
}

$salt_level = min( 6, max( 1, $salt_args['level'] ) );

$salt_classes = salt_classnames(
	array(
		'x-cta',
		'flex flex-col gap-6',
		'left' === $salt_args['align'] ? 'items-start' : 'items-center',
		$salt_align_classes[ $salt_args['align'] ]['wrapper'],
	)
);

$salt_row_classes = salt_classnames(
	array(
		'flex flex-wrap gap-4',
		$salt_align_classes[ $salt_args['align'] ]['row'],
	)
);
?>
<div class="<?php echo esc_attr( $salt_classes ); ?>">
	<?php if ( '' !== $salt_args['heading'] ) : ?>
		<h<?php echo (int) $salt_level; ?> class="x-heading text-3xl font-bold tracking-tight"><?php echo esc_html( $salt_args['heading'] ); ?></h<?php echo (int) $salt_level; ?>>
	<?php endif; ?>
	<?php if ( '' !== $salt_args['text'] ) : ?>
		<p class="text-ink-muted"><?php echo esc_html( $salt_args['text'] ); ?></p>
	<?php endif; ?>
	<?php if ( array() !== $salt_args['buttons'] ) : ?>
		<div class="<?php echo esc_attr( $salt_row_classes ); ?>">
			<?php
			foreach ( $salt_args['buttons'] as $salt_button ) {
				if (
					! is_array( $salt_button )
					|| ! is_string( $salt_button['label'] ?? null ) || '' === ( $salt_button['label'] ?? '' )
					|| ! is_string( $salt_button['url'] ?? null ) || '' === ( $salt_button['url'] ?? '' )
				) {
					salt_log( 'x-cta: malformed button entry skipped (label and url are required).' );
					continue;
				}

				salt_component(
					'button',
					array(
						'label' => $salt_button['label'],
						'url'   => $salt_button['url'],
						'style' => is_string( $salt_button['style'] ?? null ) && '' !== ( $salt_button['style'] ?? '' )
							? $salt_button['style']
							: 'primary',
					)
				);
			}
			?>
		</div>
	<?php endif; ?>
</div>
