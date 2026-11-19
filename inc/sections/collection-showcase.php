<?php
/**
 * Resolver: Collection showcase (salt-contract collection-showcase, rc.7;
 * EP-72 M3 unit 8). One section in place of the former services, work,
 * team, testimonials (grid) and blog_teaser layouts. The view is
 * template-parts/sections/collection-showcase.php.
 *
 * Fields (contract/fields/collection-showcase.json, generated): eyebrow,
 * heading, intro, source, layout (grid|list|featured|accordion|index),
 * columns, query (the collection query), showIcons, showRatings, showTags,
 * groupByCategory, viewAll, viewAllLabel, and the shared settings.
 *
 * Zero state (markup zeroState): the source is not installed, index on a
 * source whose items have no page, or no card survives => null.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current collection-showcase row to its view props.
 *
 * Props (contract view-props/collection-showcase.json): eyebrow, heading,
 * intro (null when blank), source, layout, columns, cards (the card type of
 * view-props/_shared.json, with icons, ratings and tags left out where the
 * section switches them off), groups (an index grouped by category: one
 * untitled group of the entries with no topic first, then one per topic;
 * null otherwise), results (the featured case study's, for the lead card;
 * empty otherwise) and viewAll (a link, or null). Beside them, what the
 * contract's markup reads of the site: labels and arrow.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null
 */
function salt_resolve_section_collection_showcase( array $context ): ?array {
	$sub    = static function ( string $field ) {
		return function_exists( 'get_sub_field' ) ? get_sub_field( $field ) : null;
	};
	$text   = static fn( $value ): ?string => is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;
	$choice = static fn( $value, array $allowed, string $fallback ): string => in_array( $value, $allowed, true ) ? (string) $value : $fallback;

	$source = (string) $sub( 'source' );
	if ( ! salt_collection_source_installed( $source ) ) {
		if ( '' !== $source ) {
			salt_log( sprintf( 'collection-showcase: source "%s" is not installed — section skipped.', $source ) );
		}
		return null;
	}

	$layout  = $choice( $sub( 'layout' ), array( 'grid', 'list', 'featured', 'accordion', 'index' ), 'grid' );
	$columns = $choice( (string) $sub( 'columns' ), array( '2', '3', '4' ), '3' );
	$linked  = salt_collection_sources()[ $source ]['linked'];
	if ( 'index' === $layout && ! $linked ) {
		salt_log( sprintf( 'collection-showcase: the index layout needs items with pages of their own; %s has none — section skipped.', $source ) );
		return null;
	}

	$settings = salt_section_contract_args( 'collection-showcase', $context );
	$band     = $settings['container'];
	$flag     = static fn( string $field, bool $fallback ): bool => null === $sub( $field ) ? $fallback : (bool) $sub( $field );

	$cards = array();
	foreach ( salt_collection_query( $source ) as $i => $post ) {
		// The image slot (contract/image-sizes.json placements): a list or a
		// featured lead card is the content slot, any other grid card the card
		// slot at the showcase's columns; a quote's photo is a thumb, and an
		// accordion or an index draws none.
		$lead  = 'list' === $layout || ( 'featured' === $layout && array() === $cards );
		$sizes = in_array( $layout, array( 'accordion', 'index' ), true ) ? '' : ( 'testimonials' === $source ? salt_image_slot_sizes( 'thumb' ) : ( $lead ? salt_image_slot_sizes( 'content', $band ) : salt_image_slot_sizes( 'card', $band, $columns ) ) );
		$card  = salt_collection_card(
			$post,
			$source,
			array(
				'icons'   => $flag( 'showIcons', true ),
				'ratings' => $flag( 'showRatings', true ),
				'tags'    => in_array( $layout, array( 'grid', 'list', 'featured' ), true ) && $flag( 'showTags', false ),
				'sizes'   => $sizes,
			)
		);
		if ( null !== $card ) {
			$card['post'] = $post;
			$cards[]      = $card;
		}
		unset( $i );
	}
	if ( array() === $cards ) {
		return null; // 0-state: nothing to show (COMPL02).
	}

	// An index grouped by category: the source's topics, each holding the
	// entries filed under it, after one untitled group of the entries with
	// none (markup: the loose index list comes first).
	$groups   = null;
	$taxonomy = salt_collection_sources()[ $source ]['taxonomy'];
	if ( 'index' === $layout && null !== $taxonomy && (bool) $sub( 'groupByCategory' ) ) {
		$groups = salt_collection_index_groups( $cards, $taxonomy );
	}

	// The featured case study's results, for the lead card (view-props).
	$results = array();
	if ( 'featured' === $layout && 'case-studies' === $source ) {
		foreach ( (array) get_field( 'results', $cards[0]['post']->ID ) as $row ) {
			$stat = is_array( $row ) ? $text( $row['stat'] ?? null ) : null;
			if ( null === $stat ) {
				continue;
			}
			$results[] = array(
				'stat'  => $stat,
				'label' => (string) $text( $row['label'] ?? null ),
			);
		}
	}

	$view_all = null;
	if ( $flag( 'viewAll', true ) ) {
		$href = salt_collection_index_url( $source );
		if ( null !== $href ) {
			$view_all = array(
				'href'   => $href,
				'label'  => $text( $sub( 'viewAllLabel' ) ) ?? salt_contract_labels()['viewAll'] ?? '',
				'newTab' => false,
			);
		}
	}

	salt_collection_schema( $source, $cards );
	$cards = array_map(
		static function ( array $card ): array {
			unset( $card['post'] );
			return $card;
		},
		$cards
	);
	if ( null !== $groups ) {
		foreach ( $groups as $g => $group ) {
			foreach ( $group['cards'] as $c => $card ) {
				unset( $groups[ $g ]['cards'][ $c ]['post'] );
			}
		}
	}

	$heading  = $text( $sub( 'heading' ) );
	$priority = salt_section_plan_priority( $context );
	$wrapper  = array_merge(
		$settings,
		array(
			'has_heading'         => null !== $heading,
			'background_priority' => $priority && null !== $settings['background_image'],
		)
	);

	return array(
		'section' => $wrapper,
		'props'   => array(
			'eyebrow' => $text( $sub( 'eyebrow' ) ),
			'heading' => $heading,
			'intro'   => $text( $sub( 'intro' ) ),
			'source'  => $source,
			'layout'  => $layout,
			'columns' => $columns,
			'cards'   => $cards,
			'groups'  => $groups,
			'results' => $results,
			'viewAll' => $view_all,
			'labels'  => salt_contract_labels(),
			'arrow'   => salt_contract_arrow(),
		),
		// A renderable background takes the priority image over the block's own.
		'plan'    => array( 'priorityMedia' => $priority && null === $settings['background_image'] ),
	);
}

/**
 * An index's groups (view-props card-group): one untitled group (id and
 * title null) of the entries filed under no topic, first, when there are
 * any; then one group per topic, each holding every entry filed under it.
 *
 * PENDING the founder's ruling on two points the contract leaves open
 * ("one group per term, in term order"): the topics are taken in the order
 * they first appear among the entries (the collection's stored order), and
 * an entry filed under two topics is listed under both.
 *
 * @param array<int,array<string,mixed>> $cards    Cards, each with its `post`.
 * @param string                         $taxonomy The source's taxonomy.
 * @return array<int,array{id: string|null, title: string|null, cards: array<int,array<string,mixed>>}>
 */
function salt_collection_index_groups( array $cards, string $taxonomy ): array {
	$loose  = array();
	$groups = array();
	foreach ( $cards as $card ) {
		$tags = salt_collection_tags( $card['post'], $taxonomy );
		if ( array() === $tags ) {
			$loose[] = $card;
			continue;
		}
		foreach ( $tags as $tag ) {
			$groups[ $tag['id'] ]          ??= array(
				'id'    => $tag['id'],
				'title' => $tag['title'],
				'cards' => array(),
			);
			$groups[ $tag['id'] ]['cards'][] = $card;
		}
	}
	$groups = array_values( $groups );
	if ( array() !== $loose ) {
		array_unshift(
			$groups,
			array(
				'id'    => null,
				'title' => null,
				'cards' => $loose,
			)
		);
	}
	return $groups;
}
