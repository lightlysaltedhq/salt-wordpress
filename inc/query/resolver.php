<?php
/**
 * Principle-7 query resolver (spec §10a).
 *
 * Every CPT-backed section resolves its post list through one funnel:
 * salt_resolve_query( $controls, $cpt ). Resolution order is
 * manual → query controls → per-CPT defaults, so editors can hand-pick,
 * filter by taxonomy, or accept the frozen defaults (AMBIG07) without any
 * section-specific query code.
 *
 * ZERO-RESULT CONTRACT (COMPL02): salt_resolve_query() ALWAYS returns
 * WP_Post[] — possibly an empty array() — and NEVER null or false.
 * Sections receiving array() render their empty state (default: skip).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'salt_query_default_args' ) ) {
	/**
	 * Per-CPT sane default query args (frozen contract, AMBIG07).
	 *
	 * The 'service' row reproduces the legacy services_overview hardcoded
	 * query exactly — menu_order ASC, all published services (DATA04 parity).
	 * Pluggable (ARCH07): a child theme may redefine this to change the
	 * defaults fleet-wide; see docs/contracts/OVERRIDES.md.
	 *
	 * @param string $cpt Post type.
	 * @return array{orderby:string,order:string,count:int} Defaults. count -1 = all.
	 */
	function salt_query_default_args( string $cpt ): array {
		$defaults = array(
			'service'     => array(
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'count'   => -1,
			),
			'faq'         => array(
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'count'   => -1,
			),
			'location'    => array(
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'count'   => -1,
			),
			'testimonial' => array(
				'orderby' => 'date',
				'order'   => 'DESC',
				'count'   => 6,
			),
			'work'        => array(
				'orderby' => 'date',
				'order'   => 'DESC',
				'count'   => 6,
			),
			'post'        => array(
				'orderby' => 'date',
				'order'   => 'DESC',
				'count'   => 3,
			),
		);

		if ( isset( $defaults[ $cpt ] ) ) {
			return $defaults[ $cpt ];
		}

		salt_log(
			sprintf(
				'salt_query_default_args: no frozen defaults for post type "%s"; falling back to date DESC, 6.',
				$cpt
			)
		);

		return array(
			'orderby' => 'date',
			'order'   => 'DESC',
			'count'   => 6,
		);
	}
}

/**
 * The taxonomy a CPT's query controls filter by, or null when the CPT has
 * no filterable taxonomy (testimonial; team, which has no topics: SC-027).
 * Final — not pluggable.
 *
 * @param string $cpt Post type.
 * @return string|null Taxonomy slug or null.
 */
function salt_query_taxonomy( string $cpt ): ?string {
	return match ( $cpt ) {
		'service'     => 'service_category',
		'work'        => 'work_type',
		'faq'         => 'faq_category',
		'post'        => 'category',
		'location'    => 'location_area',
		default       => null,
	};
}

if ( ! function_exists( 'salt_resolve_query' ) ) {
	/**
	 * Resolve section query controls to a list of posts (Principle 7).
	 *
	 * Resolution order: manual → query controls → salt_query_default_args().
	 *
	 * Controls shape (frozen contract, all keys optional — missing keys are
	 * treated as defaults):
	 *
	 *     [
	 *         'source'         => 'default'|'taxonomy'|'manual',
	 *         'manual'         => int[],  // hand-picked post IDs, in order.
	 *         'taxonomy_terms' => int[],  // term IDs, matched via term_id IN.
	 *         'orderby'        => string, // '' = section default.
	 *         'order'          => string, // '' = section default.
	 *         'count'          => int,    // 0 = section default; -1 = all.
	 *     ]
	 *
	 * ZERO-RESULT CONTRACT (COMPL02): always returns WP_Post[] — possibly
	 * empty array() — NEVER null/false. Sections receiving array() render
	 * their empty state (default: skip). count -1 = all.
	 *
	 * Hand-picked IDs that resolve to no published post are logged via
	 * salt_log() (DATA05 broken-relationship warning) and skipped; the found
	 * posts are still returned in the editor's hand-picked order.
	 *
	 * Pluggable (ARCH07 headline child-override case): a child theme may
	 * redefine the entire resolution strategy; see docs/contracts/OVERRIDES.md.
	 *
	 * @param array<string,mixed> $controls Query controls (shape above).
	 * @param string              $cpt      Post type.
	 * @return WP_Post[] Resolved posts; possibly empty, never null/false.
	 */
	function salt_resolve_query( array $controls, string $cpt ): array {
		$defaults = salt_query_default_args( $cpt );
		$source   = isset( $controls['source'] ) ? (string) $controls['source'] : 'default';
		$manual   = array_values( array_filter( array_map( 'intval', (array) ( $controls['manual'] ?? array() ) ) ) );

		// 1. Manual hand-pick wins outright.
		if ( 'manual' === $source && array() !== $manual ) {
			$posts = get_posts(
				array(
					'post_type'   => $cpt,
					'post_status' => 'publish',
					'post__in'    => $manual,
					'orderby'     => 'post__in',
					'numberposts' => -1,
				)
			);

			$found = array_map( static fn( WP_Post $p ): int => (int) $p->ID, $posts );
			foreach ( $manual as $requested_id ) {
				if ( ! in_array( $requested_id, $found, true ) ) {
					salt_log(
						sprintf(
							'salt_resolve_query: hand-picked %s post ID %d is missing or unpublished; skipped (broken relationship).',
							$cpt,
							$requested_id
						)
					);
				}
			}

			$manual_count = isset( $controls['count'] ) ? (int) $controls['count'] : 0;
			if ( $manual_count > 0 ) {
				$posts = array_slice( $posts, 0, $manual_count );
			}

			return array_values( $posts );
		}

		$orderby = isset( $controls['orderby'] ) && '' !== (string) $controls['orderby']
			? (string) $controls['orderby']
			: $defaults['orderby'];
		$order   = isset( $controls['order'] ) && '' !== (string) $controls['order']
			? strtoupper( (string) $controls['order'] )
			: $defaults['order'];
		$count   = isset( $controls['count'] ) && 0 !== (int) $controls['count']
			? (int) $controls['count']
			: $defaults['count'];

		$args = array(
			'post_type'   => $cpt,
			'post_status' => 'publish',
			'orderby'     => $orderby,
			'order'       => $order,
			'numberposts' => $count,
		);

		// 2. Taxonomy filter (single term_id IN clause) when terms are set
		// and the CPT has a filterable taxonomy.
		$taxonomy = salt_query_taxonomy( $cpt );
		$terms    = array_values( array_filter( array_map( 'intval', (array) ( $controls['taxonomy_terms'] ?? array() ) ) ) );

		if ( array() !== $terms && null !== $taxonomy ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Principle-7 funnel: taxonomy filtering is the feature.
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $terms,
					'operator' => 'IN',
				),
			);
		}

		// 3. Defaults (with any control overrides applied above).
		return get_posts( $args );
	}
}

/**
 * Read the contract's collection-query group of the CURRENT Flexible
 * Content row (contract field type `collection-query`: mode, categories,
 * items, order, count) into a salt_resolve_query() controls array — the
 * one reader every section built on the contract's query uses (faq and
 * locations today).
 *
 *   mode automatic   => the source's defaults (salt_query_default_args());
 *   mode by-category => the categories (term IDs) as a taxonomy filter;
 *   mode manual      => the items, in the order picked; order and count
 *                       are hidden for it, so they are not read;
 *   order default|newest|title => the source's order | date DESC | title ASC;
 *   count 0 => the source's default; 24 is checked on write, not on read.
 *
 * Unknown values fall back to automatic / default, logged.
 *
 * @param string $field The query group's name (the contract's is `query`).
 * @return array<string,mixed>
 */
function salt_query_controls_from_query( string $field = 'query' ): array {
	$query = function_exists( 'get_sub_field' ) ? get_sub_field( $field ) : null;
	$query = is_array( $query ) ? $query : array();

	$mode = is_string( $query['mode'] ?? null ) && '' !== $query['mode'] ? $query['mode'] : 'automatic';
	if ( ! in_array( $mode, array( 'automatic', 'by-category', 'manual' ), true ) ) {
		salt_log( sprintf( 'collection query: unknown mode "%s" — using automatic.', $mode ) );
		$mode = 'automatic';
	}

	$ids = static fn( $values ): array => array_values(
		array_filter(
			array_map(
				static fn( $item ): int => $item instanceof WP_Post ? (int) $item->ID : ( is_object( $item ) && isset( $item->term_id ) ? (int) $item->term_id : (int) $item ),
				is_array( $values ) ? $values : array()
			)
		)
	);

	if ( 'manual' === $mode ) {
		return array(
			'source' => 'manual',
			'manual' => $ids( $query['items'] ?? null ),
		);
	}

	$order = is_string( $query['order'] ?? null ) && '' !== $query['order'] ? $query['order'] : 'default';
	$by    = array(
		'default' => array( '', '' ),
		'newest'  => array( 'date', 'DESC' ),
		'title'   => array( 'title', 'ASC' ),
	);
	if ( ! isset( $by[ $order ] ) ) {
		salt_log( sprintf( 'collection query: unknown order "%s" — using default.', $order ) );
		$order = 'default';
	}

	return array(
		'source'         => 'by-category' === $mode ? 'taxonomy' : 'default',
		'taxonomy_terms' => 'by-category' === $mode ? $ids( $query['categories'] ?? null ) : array(),
		'orderby'        => $by[ $order ][0],
		'order'          => $by[ $order ][1],
		'count'          => (int) ( $query['count'] ?? 0 ),
	);
}
