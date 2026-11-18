<?php
/**
 * x-card-testimonial — blockquote-based testimonial card (card family, §12).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   quote   string  required  Testimonial body (plain text, escaped here;
 *                             line breaks kept via whitespace-pre-line).
 *   author  string  ''        Author name (the testimonial post title).
 *   role    string  ''        Author role, e.g. "Founder".
 *   company string  ''        Company name (joined to role with a comma).
 *   rating  int     0         1–5 star rating; 0 = no rating shown. Out of
 *                             range is clamped to 0..5 + salt_log()ged. The
 *                             rating renders as one role="img" group with an
 *                             aria-label; the star icons are decorative.
 *   media   string  ''        PRE-RENDERED HTML slot (e.g. an x-image render
 *                             of the person's photo). Printed raw — the
 *                             producer escapes at print.
 *   classes string  ''        Extra utility classes appended to the class list.
 *
 * Empty quote renders nothing (logged) — never a hollow card.
 * Render via salt_component( 'card-testimonial', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = salt_require_args( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $args is WordPress's load_template() template variable.
	$args ?? array(),
	array(
		'quote'   => array(
			'type'     => 'string',
			'required' => true,
		),
		'author'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'role'    => array(
			'type'    => 'string',
			'default' => '',
		),
		'company' => array(
			'type'    => 'string',
			'default' => '',
		),
		'rating'  => array(
			'type'    => 'int',
			'default' => 0,
		),
		'media'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
	),
	'card-testimonial'
);

if ( '' === trim( $args['quote'] ) ) {
	salt_log( 'x-card-testimonial: empty quote — rendered nothing.' );
	return;
}

$salt_rating = (int) $args['rating'];
if ( $salt_rating < 0 || $salt_rating > 5 ) {
	salt_log( sprintf( 'x-card-testimonial: rating %d out of range — clamped to 0..5.', $salt_rating ) );
	$salt_rating = max( 0, min( 5, $salt_rating ) );
}

$salt_attribution_line = implode(
	', ',
	array_filter(
		array( trim( $args['role'] ), trim( $args['company'] ) ),
		static fn( string $part ): bool => '' !== $part
	)
);

$salt_has_attribution = '' !== trim( $args['author'] ) || '' !== $salt_attribution_line || '' !== $args['media'];

$salt_rating_label = sprintf(
	/* translators: 1: given star rating. 2: maximum star rating. */
	__( 'Rated %1$d out of %2$d stars', 'salt' ),
	$salt_rating,
	5
);

$salt_classes = salt_classnames(
	array(
		'x-card-testimonial flex h-full flex-col gap-4 rounded-lg border border-border bg-surface p-6 text-ink',
		$args['classes'],
	)
);
?>
<figure class="<?php echo esc_attr( $salt_classes ); ?>">
	<?php if ( $salt_rating >= 1 ) : ?>
	<div class="x-card-testimonial-rating flex gap-1 text-brand" role="img" aria-label="<?php echo esc_attr( $salt_rating_label ); ?>">
		<?php
		// Always 5 stars: {rating} filled + the remainder muted outlines, so
		// a 3/5 is visually distinct from a 5/5 (not just fewer glyphs).
		for ( $salt_star = 1; $salt_star <= 5; $salt_star++ ) {
			if ( $salt_star <= $salt_rating ) {
				salt_component(
					'icon',
					array(
						'name' => 'star-filled',
						'size' => 'sm',
					)
				);
				continue;
			}
			echo '<span class="x-card-testimonial-star-empty text-border">';
			salt_component(
				'icon',
				array(
					'name' => 'star',
					'size' => 'sm',
				)
			);
			echo '</span>';
		}
		?>
	</div>
	<?php endif; ?>
	<blockquote class="x-card-testimonial-quote whitespace-pre-line text-ink"><?php echo esc_html( $args['quote'] ); ?></blockquote>
	<?php if ( $salt_has_attribution ) : ?>
	<figcaption class="x-card-testimonial-attribution mt-auto flex items-center gap-3">
		<?php if ( '' !== $args['media'] ) : ?>
		<div class="x-card-testimonial-media shrink-0">
			<?php echo $args['media']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered slot HTML; the producing component escapes at print. ?>
		</div>
		<?php endif; ?>
		<div class="x-card-testimonial-author">
			<?php if ( '' !== trim( $args['author'] ) ) : ?>
			<span class="block font-semibold"><?php echo esc_html( $args['author'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $salt_attribution_line ) : ?>
			<span class="block text-sm text-ink-muted"><?php echo esc_html( $salt_attribution_line ); ?></span>
			<?php endif; ?>
		</div>
	</figcaption>
	<?php endif; ?>
</figure>
