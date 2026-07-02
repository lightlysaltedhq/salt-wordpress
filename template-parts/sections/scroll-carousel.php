<?php
/**
 * Section: Scroll Carousel (spec §10 #18) — inline repeater on a CSS
 * scroll-snap track that is FULLY functional without JS (FAIL02).
 *
 * Fields: heading, eyebrow, card_type (generic|testimonial), cards [image,
 * title, text, role]. Generic cards are simple divs (title at $level + 1);
 * testimonial cards are blockquote-styled figures. States (COMPL02): 0
 * valid cards => renders NOTHING (skip before the wrapper); 1 card => one
 * static card (no track, no data-salt-carousel — no JS trigger); N => the
 * snap track. Cards with neither title nor text are skipped and logged.
 *
 * assets/src/js/sections/scroll-carousel.js keys off [data-salt-carousel]
 * and injects prev/next buttons as a progressive enhancement — none exist
 * in this markup, so the no-JS base ships no dead buttons. No schema.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_sub = static function ( string $field ): string {
	if ( ! function_exists( 'get_sub_field' ) ) {
		return '';
	}
	$value = get_sub_field( $field );
	return is_string( $value ) ? $value : '';
};

$salt_heading = $salt_sub( 'heading' );
$salt_eyebrow = $salt_sub( 'eyebrow' );

$salt_card_type = $salt_sub( 'card_type' );
if ( ! in_array( $salt_card_type, array( 'generic', 'testimonial' ), true ) ) {
	$salt_card_type = 'generic';
}

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'cards' ) : array();
if ( ! is_array( $salt_rows ) ) {
	$salt_rows = array();
}

// Normalise repeater rows, skipping malformed items (logged — COMPL02).
$salt_items = array();
foreach ( $salt_rows as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'scroll-carousel section: card %d is not a row array — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_title = is_string( $salt_row['title'] ?? null ) ? trim( $salt_row['title'] ) : '';
	$salt_text  = is_string( $salt_row['text'] ?? null ) ? trim( $salt_row['text'] ) : '';
	if ( '' === $salt_title && '' === $salt_text ) {
		salt_log( sprintf( 'scroll-carousel section: card %d has no title or text — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_image_raw = $salt_row['image'] ?? 0;
	if ( is_array( $salt_image_raw ) ) {
		$salt_image_raw = $salt_image_raw['ID'] ?? ( $salt_image_raw['id'] ?? 0 );
	}

	$salt_items[] = array(
		'image_id' => is_numeric( $salt_image_raw ) ? (int) $salt_image_raw : 0,
		'title'    => $salt_title,
		'text'     => $salt_text,
		'role'     => is_string( $salt_row['role'] ?? null ) ? trim( $salt_row['role'] ) : '',
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'scroll-carousel', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_eyebrow, $salt_card_type, $salt_items ): void {
		if ( '' !== trim( $salt_eyebrow ) ) {
			salt_component(
				'eyebrow',
				array(
					'text'    => $salt_eyebrow,
					'classes' => 'mb-2',
				)
			);
		}
		if ( '' !== trim( $salt_heading ) ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-6',
				)
			);
		}

		// Card titles step one level below the section heading (A11Y01).
		$card_level = min( 6, $level + 1 );

		/**
		 * Render one card (escaping happens here / inside the components).
		 *
		 * @param array{image_id: int, title: string, text: string, role: string} $item Card fields.
		 * @return string
		 */
		$render_card = static function ( array $item ) use ( $salt_card_type, $card_level ): string {
			$image_html = '';
			if ( $item['image_id'] > 0 ) {
				$image_html = salt_get_component(
					'image',
					array(
						'image_id'      => $item['image_id'],
						'alt'           => '',
						'sizes_context' => 'card',
						'classes'       => 'testimonial' === $salt_card_type ? 'size-14 rounded-full object-cover' : 'rounded-md',
					)
				);
			}

			if ( 'testimonial' === $salt_card_type ) {
				// Blockquote-styled card: quote + attribution, never a heading.
				$caption = '<span class="x-scroll-carousel-name font-semibold text-ink">' . esc_html( $item['title'] ) . '</span>';
				if ( '' !== $item['role'] ) {
					$caption .= '<span class="x-scroll-carousel-role block text-ink-muted">' . esc_html( $item['role'] ) . '</span>';
				}

				return '<figure class="x-scroll-carousel-card flex h-full flex-col gap-4 rounded-lg border border-border bg-surface p-6">'
					. $image_html
					. '<blockquote class="x-scroll-carousel-quote text-ink"><p>' . esc_html( $item['text'] ) . '</p></blockquote>'
					. '<figcaption class="x-scroll-carousel-caption mt-auto text-sm">' . $caption . '</figcaption>'
					. '</figure>';
			}

			$title_html = '';
			if ( '' !== $item['title'] ) {
				$title_html = salt_get_component(
					'heading',
					array(
						'text'    => $item['title'],
						'level'   => $card_level,
						'size'    => 'sm',
						'classes' => 'x-scroll-carousel-title',
					)
				);
			}

			$text_html = '' !== $item['text']
				? '<p class="x-scroll-carousel-text text-ink-muted">' . esc_html( $item['text'] ) . '</p>'
				: '';

			return '<div class="x-scroll-carousel-card flex h-full flex-col gap-3 rounded-lg border border-border bg-surface p-6">'
				. $image_html . $title_html . $text_html
				. '</div>';
		};

		if ( 1 === count( $salt_items ) ) {
			// 1-state: one static card — no track, no JS trigger (COMPL02/FAIL02).
			printf(
				'<div class="x-scroll-carousel-single max-w-2xl">%s</div>',
				$render_card( $salt_items[0] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered card HTML; escapes at the point each value is printed.
			);
			return;
		}

		// No-JS base (FAIL02): a keyboard-focusable CSS scroll-snap track.
		// The JS module keys off data-salt-carousel and injects its prev/next
		// buttons — none exist in this markup.
		$track_items = '';
		foreach ( $salt_items as $item ) {
			$track_items .= '<div class="x-scroll-carousel-item w-5/6 shrink-0 snap-start sm:w-2/5 lg:w-1/3">' . $render_card( $item ) . '</div>';
		}
		printf(
			'<div class="x-scroll-carousel-track flex snap-x snap-mandatory gap-6 overflow-x-auto pb-4" data-salt-carousel role="region" tabindex="0" aria-label="%1$s" data-salt-prev-label="%2$s" data-salt-next-label="%3$s">%4$s</div>',
			'' !== trim( $salt_heading ) ? esc_attr( $salt_heading ) : esc_attr__( 'Carousel', 'salt' ),
			esc_attr__( 'Previous cards', 'salt' ),
			esc_attr__( 'Next cards', 'salt' ),
			$track_items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered card HTML; escapes at the point each value is printed.
		);
	}
);
