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
 *   rating  float   0         1–5 star rating in half stars (salt-contract
 *                             rc.7 testimonials.json rating, step 0.5); 0 =
 *                             no rating shown. Out of range is clamped to
 *                             0..5 + salt_log()ged. The rating renders as
 *                             one role="img" group with an aria-label. The
 *                             stars are drawn as the contract's card draws
 *                             them (markup/card.json, rating): five empty
 *                             stars with five filled stars clipped to
 *                             --salt-rating-fill, the rating as a
 *                             percentage of 5, so 4.5 fills 90%. All of
 *                             it is decorative.
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
			'type'    => 'float',
			'default' => 0.0,
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

$salt_rating = (float) $args['rating'];
if ( $salt_rating < 0 || $salt_rating > 5 ) {
	salt_log( sprintf( 'x-card-testimonial: rating %s out of range — clamped to 0..5.', (string) $salt_rating ) );
	$salt_rating = max( 0.0, min( 5.0, $salt_rating ) );
}

$salt_attribution_line = implode(
	', ',
	array_filter(
		array( trim( $args['role'] ), trim( $args['company'] ) ),
		static fn( string $part ): bool => '' !== $part
	)
);

$salt_has_attribution = '' !== trim( $args['author'] ) || '' !== $salt_attribution_line || '' !== $args['media'];

// PENDING the founder's ruling: the contract's label is "{n} of {N}"
// (markup/card.json) and does not say how a half value is written. It is
// written "4.5" for now (PHP's own float string, the same in every locale),
// not with the locale's decimal separator.
$salt_rating_label = sprintf(
	/* translators: 1: given star rating, such as 4 or 4.5. 2: maximum star rating. */
	__( 'Rated %1$s out of %2$d stars', 'salt' ),
	(string) $salt_rating,
	5
);
$salt_rating_fill = salt_rating_fill( $salt_rating );

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
		<span class="x-card-testimonial-stars relative inline-flex gap-1 text-border" aria-hidden="true" style="<?php echo esc_attr( '--salt-rating-fill: ' . $salt_rating_fill ); ?>">
			<?php
			// Five empty stars, with five filled stars clipped to the
			// rating's share of 5 laid over them (contract card: rating).
			for ( $salt_star = 1; $salt_star <= 5; $salt_star++ ) {
				salt_component(
					'icon',
					array(
						'name' => 'star',
						'size' => 'sm',
					)
				);
			}
			?>
			<span class="x-card-testimonial-stars-fill absolute inset-y-0 left-0 flex w-(--salt-rating-fill) gap-1 overflow-hidden text-brand">
				<?php
				for ( $salt_star = 1; $salt_star <= 5; $salt_star++ ) {
					salt_component(
						'icon',
						array(
							'name' => 'star-filled',
							'size' => 'sm',
						)
					);
				}
				?>
			</span>
		</span>
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
