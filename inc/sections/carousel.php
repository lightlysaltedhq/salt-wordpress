<?php
/**
 * Resolver: Carousel (salt-contract carousel, rc.7; EP-72 M3 unit 8). One
 * section in place of the former scroll_carousel layout and the
 * testimonials layout's slider. The view is template-parts/sections/carousel.php.
 *
 * Fields (contract/fields/carousel.json, generated): eyebrow, heading,
 * intro, label, source (inline or a collection), cardStyle and cards (the
 * inline cards), query (the collection query), showTags, and the shared
 * settings.
 *
 * Zero state (markup zeroState): the source is not installed, or no card
 * survives => null.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current carousel row to its view props.
 *
 * Props (contract view-props/carousel.json): eyebrow, heading, intro,
 * label, named (heading or label set), source, cardStyle (quote for
 * testimonials, otherwise the field's for inline cards and generic for a
 * collection) and cards. Beside them, what the contract's markup reads of
 * the site: labels and arrow.
 *
 * The cards' image slot (contract/image-sizes.json placements): a slide in
 * the track is the unbanded card, the unnamed list the banded card at three
 * columns, a lone card the banded content slot; a quotation's photo is a
 * thumb.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null
 */
function salt_resolve_section_carousel( array $context ): ?array {
	$sub  = static function ( string $field ) {
		return function_exists( 'get_sub_field' ) ? get_sub_field( $field ) : null;
	};
	$text = static fn( $value ): ?string => is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;

	$source = is_string( $sub( 'source' ) ) && '' !== $sub( 'source' ) ? (string) $sub( 'source' ) : 'inline';
	if ( 'inline' !== $source && ! salt_collection_source_installed( $source ) ) {
		salt_log( sprintf( 'carousel: source "%s" is not installed — section skipped.', $source ) );
		return null;
	}

	$settings = salt_section_contract_args( 'carousel', $context );
	$band     = $settings['container'];
	$heading  = $text( $sub( 'heading' ) );
	$label    = $text( $sub( 'label' ) );
	$named    = null !== $heading || null !== $label;

	$style = 'generic';
	if ( 'testimonials' === $source ) {
		$style = 'quote';
	} elseif ( 'inline' === $source && 'quote' === $sub( 'cardStyle' ) ) {
		$style = 'quote';
	}

	// The cards first without images: the slot depends on how many survive.
	$cards = array();
	$posts = array();
	if ( 'inline' === $source ) {
		foreach ( (array) $sub( 'cards' ) as $i => $row ) {
			$row   = is_array( $row ) ? $row : array();
			$title = $text( $row['title'] ?? null );
			$words = $text( $row['text'] ?? null );
			if ( null === $title ) {
				salt_log( sprintf( 'carousel: inline card %d has no title — skipped.', (int) $i ) );
				continue;
			}
			if ( 'quote' === $style && null === $words ) {
				salt_log( sprintf( 'carousel: quote card %d has no words — skipped.', (int) $i ) );
				continue;
			}
			$cards[] = array(
				'id'        => (string) $i,
				'title'     => $title,
				'href'      => null,
				'image'     => (int) ( $row['image'] ?? 0 ),
				'icon'      => null,
				'meta'      => 'quote' === $style ? $text( $row['role'] ?? null ) : null,
				'text'      => $words,
				'date'      => null,
				'rating'    => null,
				'email'     => null,
				'phone'     => null,
				'phoneHref' => null,
				'profiles'  => array(),
				'tags'      => array(),
			);
		}
	} else {
		foreach ( salt_collection_query( $source ) as $post ) {
			$card = salt_collection_card(
				$post,
				$source,
				array(
					'icons'   => true,
					'ratings' => true,
					'tags'    => (bool) $sub( 'showTags' ),
					'sizes'   => '',
				)
			);
			if ( null !== $card ) {
				$card['image'] = $post instanceof WP_User ? (int) get_user_meta( $post->ID, 'photo', true ) : (int) get_post_thumbnail_id( $post );
				$cards[]       = $card;
				$posts[]       = $post;
			}
		}
	}
	if ( array() === $cards ) {
		return null; // 0-state: nothing to show (COMPL02).
	}

	if ( 'quote' === $style ) {
		$sizes = salt_image_slot_sizes( 'thumb' );
	} elseif ( 1 === count( $cards ) ) {
		$sizes = salt_image_slot_sizes( 'content', $band );
	} elseif ( $named ) {
		$sizes = salt_image_slot_sizes( 'card' );
	} else {
		$sizes = salt_image_slot_sizes( 'card', $band, '3' );
	}
	foreach ( $cards as $c => $card ) {
		$cards[ $c ]['image'] = $card['image'] > 0 ? salt_image_contract( $card['image'], $sizes ) : null;
	}

	if ( array() !== $posts ) {
		salt_collection_schema( $source, $cards );
	}

	$priority = salt_section_plan_priority( $context );

	return array(
		'section' => array_merge(
			$settings,
			array(
				'has_heading'         => null !== $heading,
				'background_priority' => $priority && null !== $settings['background_image'],
			)
		),
		'props'   => array(
			'eyebrow'   => $text( $sub( 'eyebrow' ) ),
			'heading'   => $heading,
			'intro'     => $text( $sub( 'intro' ) ),
			'label'     => $label,
			'named'     => $named,
			'source'    => $source,
			'cardStyle' => $style,
			'cards'     => $cards,
			'labels'    => salt_contract_labels(),
			'arrow'     => salt_contract_arrow(),
		),
		// A renderable background takes the priority image over the cards':
		// the first card's image, never a quotation's photo.
		'plan'    => array( 'priorityMedia' => $priority && null === $settings['background_image'] && 'quote' !== $style && is_array( $cards[0]['image'] ?? null ) ),
	);
}
