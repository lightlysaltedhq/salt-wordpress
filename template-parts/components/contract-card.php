<?php
/**
 * One card, as the contract draws it (salt-contract markup/card.json, rc.7):
 * the generic style (div.salt-card) for services, case studies, posts, team
 * and inline cards, the quote style (figure.salt-card.salt-showcase__quote)
 * for testimonials and quote cards. Drawn by the collection-showcase and
 * carousel views (EP-72 M3 unit 8). A view: it draws its args and nothing
 * else.
 *
 * ARGS:
 *   card     array    A card (view-props/_shared.json card).
 *   style    string   generic|quote|panel (an accordion's panel).
 *   quote    bool     A panel in the quote style (testimonials).
 *   level    int      The title's heading level (body base + 1).
 *   priority bool     Its image is the page's priority image.
 *   results  array    The lead case study's results ({stat, label}), or none.
 *   labels   array    labels.<name> (salt_contract_labels()).
 *   arrow    string|null The site's arrow, or null for none.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args  = $args ?? array();
$salt_card  = (array) ( $salt_args['card'] ?? array() );
$salt_level = max( 2, min( 6, (int) ( $salt_args['level'] ?? 3 ) ) );
$salt_label = static fn( string $name ): string => (string) ( $salt_args['labels'][ $name ] ?? '' );
$salt_image = static function ( array $image, bool $priority, array $extra = array() ): string {
	return salt_image_contract_html( $image, $priority, $extra );
};

$salt_style = (string) ( $salt_args['style'] ?? 'generic' );
$salt_quote = 'quote' === $salt_style || ( 'panel' === $salt_style && ! empty( $salt_args['quote'] ) );

// An accordion panel (markup/collection-showcase.json, accordion): no image
// and no heading. A testimonial's is the quote figure with its caption's
// meta line; any other entry's is its meta, date, text and a link to its page.
if ( 'panel' === $salt_style && ! $salt_quote ) {
	echo '<div class="salt-showcase__panel">';
	if ( null !== ( $salt_card['meta'] ?? null ) ) {
		echo '<p class="salt-showcase__meta">' . esc_html( (string) $salt_card['meta'] ) . '</p>';
	}
	if ( null !== ( $salt_card['date'] ?? null ) ) {
		echo '<p class="salt-showcase__meta"><time datetime="' . esc_attr( (string) $salt_card['date'] ) . '">' . esc_html( salt_contract_date_text( (string) $salt_card['date'] ) ) . '</time></p>';
	}
	if ( null !== ( $salt_card['text'] ?? null ) ) {
		echo '<p class="salt-showcase__text">' . esc_html( (string) $salt_card['text'] ) . '</p>';
	}
	if ( null !== ( $salt_card['href'] ?? null ) ) {
		echo '<p><a class="salt-showcase__link" href="' . esc_url( (string) $salt_card['href'] ) . '">' . esc_html( (string) $salt_card['title'] ) . '</a></p>';
	}
	echo '</div>';
	return;
}

if ( $salt_quote ) {
	echo 'panel' === $salt_style ? '<figure class="salt-showcase__panel salt-showcase__quote">' : '<figure class="salt-card salt-showcase__quote">';
	if ( null !== ( $salt_card['rating'] ?? null ) ) {
		$salt_rating = (float) $salt_card['rating'];
		printf(
			'<p class="salt-showcase__rating" role="img" aria-label="%1$s"><span class="salt-showcase__stars" aria-hidden="true" style="%2$s">☆☆☆☆☆<span class="salt-showcase__stars-fill">★★★★★</span></span></p>',
			esc_attr( salt_contract_n_of( $salt_label( 'rating' ), $salt_rating, 5 ) ),
			esc_attr( '--salt-rating-fill: ' . salt_rating_fill( $salt_rating ) )
		);
	}
	echo '<blockquote class="salt-showcase__text"><p>' . esc_html( (string) $salt_card['text'] ) . '</p></blockquote>';
	echo '<figcaption class="salt-showcase__caption">';
	if ( 'panel' === $salt_style ) {
		if ( null !== ( $salt_card['meta'] ?? null ) ) {
			echo '<p class="salt-showcase__meta">' . esc_html( (string) $salt_card['meta'] ) . '</p>';
		}
		echo '</figcaption></figure>';
		return;
	}
	if ( is_array( $salt_card['image'] ?? null ) ) {
		// A quotation's photo is decorative and never the priority image.
		echo '<div class="salt-showcase__media">' . $salt_image( $salt_card['image'], false, array( 'alt' => '' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
	}
	printf( '<h%1$d>%2$s</h%1$d>', (int) $salt_level, esc_html( (string) $salt_card['title'] ) );
	if ( null !== ( $salt_card['meta'] ?? null ) ) {
		echo '<p class="salt-showcase__meta">' . esc_html( (string) $salt_card['meta'] ) . '</p>';
	}
	echo '</figcaption></figure>';
	return;
}

echo '<div class="salt-card">';
if ( is_array( $salt_card['image'] ?? null ) ) {
	echo '<div class="salt-showcase__media">' . $salt_image( $salt_card['image'], ! empty( $salt_args['priority'] ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
} elseif ( null !== ( $salt_card['icon'] ?? null ) ) {
	echo salt_contract_icon( (string) $salt_card['icon'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-bundled artwork, attributes escaped.
}

$salt_title = esc_html( (string) $salt_card['title'] );
if ( null !== ( $salt_card['href'] ?? null ) ) {
	$salt_arrow = null !== ( $salt_args['arrow'] ?? null ) ? '<span class="salt-arrow" aria-hidden="true">&#x2060;' . esc_html( (string) $salt_args['arrow'] ) . '</span>' : '';
	$salt_title = '<a class="salt-card__link" href="' . esc_url( (string) $salt_card['href'] ) . '">' . $salt_title . $salt_arrow . '</a>';
}
printf( '<h%1$d>%2$s</h%1$d>', (int) $salt_level, $salt_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

if ( null !== ( $salt_card['meta'] ?? null ) ) {
	echo '<p class="salt-showcase__meta">' . esc_html( (string) $salt_card['meta'] ) . '</p>';
}
if ( null !== ( $salt_card['date'] ?? null ) ) {
	echo '<p class="salt-showcase__meta"><time datetime="' . esc_attr( (string) $salt_card['date'] ) . '">' . esc_html( salt_contract_date_text( (string) $salt_card['date'] ) ) . '</time></p>';
}
if ( null !== ( $salt_card['text'] ?? null ) ) {
	echo '<p class="salt-showcase__text">' . esc_html( (string) $salt_card['text'] ) . '</p>';
}

if ( ! empty( $salt_card['email'] ) || ! empty( $salt_card['phoneHref'] ) || ! empty( $salt_card['profiles'] ) ) {
	salt_component( 'person-links', $salt_card + array( 'social_label' => $salt_label( 'social' ) ) );
}

$salt_results = (array) ( $salt_args['results'] ?? array() );
if ( array() !== $salt_results ) {
	printf(
		'<ul class="salt-grid salt-showcase__results" role="list" aria-label="%1$s"%2$s>',
		esc_attr( $salt_label( 'results' ) ),
		count( $salt_results ) <= 2 ? ' data-columns="2"' : ''
	);
	foreach ( $salt_results as $salt_result ) {
		echo '<li class="salt-stat"><p class="salt-stat__value">' . esc_html( (string) $salt_result['stat'] ) . '</p>';
		if ( '' !== (string) $salt_result['label'] ) {
			echo '<p class="salt-stat__label">' . esc_html( (string) $salt_result['label'] ) . '</p>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

if ( array() !== (array) ( $salt_card['tags'] ?? array() ) ) {
	echo '<ul class="salt-showcase__tags" role="list">';
	foreach ( (array) $salt_card['tags'] as $salt_tag ) {
		echo '<li class="salt-showcase__tag">' . esc_html( (string) $salt_tag['title'] ) . '</li>';
	}
	echo '</ul>';
}
echo '</div>';
