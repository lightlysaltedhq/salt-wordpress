<?php
/**
 * Plugin Name: Salt harness: the view case's seams
 *
 * HARNESS ONLY. Never shipped. tests/real-acf/run.sh copies this file into
 * the throwaway site's wp-content/mu-plugins. It does nothing unless
 * tests/real-acf/php/view.php has stored a view case in the
 * `salt_harness_case` option (its site, document and route), which it does
 * for the contract's view cases only, as it seeds each one.
 *
 * Then it feeds the theme's seams the case's data, as
 * tests/contract-adapter/adapter.php does for a section case
 * (salt_adapter_seed_site()), so a view is proved against the contract
 * whatever the live sources become:
 *  - the site's language from context.locale (WordPress refuses a WPLANG
 *    whose language pack is not installed), so dates display in it
 *    (section#display-forms);
 *  - the labels (site.labels, merged over the theme's), the arrow
 *    (site.arrow, none when the case has none), the collection indexes
 *    (site.collectionIndexes, by the contract's singular keys) and search
 *    (site.search, off when the case has none);
 *  - the share settings (document.share): the address shared, which the
 *    intents encode, the services in the case's order and the copy button;
 *    none when the case shares nothing;
 *  - a post's reading time (document.readingMinutes), which the theme
 *    counts by its own rule;
 *  - each upload's address from its media record's {width} template
 *    (_salt_harness_url on the attachment), as the adapter's upload URL
 *    filter does;
 *  - the route's size: a case lists one page of cards or results, so the
 *    main query's found_posts is raised to what its route says
 *    (document.count, or route.pagination.total pages).
 *
 * @package Salt\Tests\RealAcf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The view case view.php stored, or null.
 *
 * @return array<string,mixed>|null
 */
function salt_harness_case(): ?array {
	$case = get_option( 'salt_harness_case' );
	return is_array( $case ) ? $case : null;
}

if ( null !== salt_harness_case() ) {
	$salt_harness_site  = (array) ( salt_harness_case()['site'] ?? array() );
	$salt_harness_doc   = (array) ( salt_harness_case()['document'] ?? array() );
	$salt_harness_route = (array) ( salt_harness_case()['route'] ?? array() );

	$salt_harness_locale = (string) ( salt_harness_case()['context']['locale'] ?? '' );
	if ( '' !== $salt_harness_locale ) {
		add_filter( 'locale', static fn() => str_replace( '-', '_', $salt_harness_locale ) );
	}
	add_filter( 'salt_contract_labels', static fn( $labels ) => array_merge( (array) $labels, (array) ( $salt_harness_site['labels'] ?? array() ) ) );
	add_filter( 'salt_contract_arrow', static fn() => $salt_harness_site['arrow'] ?? null );
	add_filter( 'salt_contract_search', static fn() => $salt_harness_site['search'] ?? null );
	add_filter(
		'salt_collection_index_url',
		static function ( $url, string $source ) use ( $salt_harness_site ) {
			$keys = array(
				'posts'        => 'post',
				'services'     => 'service',
				'case-studies' => 'case-study',
			);
			return $salt_harness_site['collectionIndexes'][ $keys[ $source ] ?? $source ]['href'] ?? null;
		},
		10,
		2
	);

	add_filter(
		'salt_share_settings',
		static function ( array $share ) use ( $salt_harness_doc ): array {
			$given = (array) ( $salt_harness_doc['share'] ?? array() );
			$url   = '';
			foreach ( (array) ( $given['services'] ?? array() ) as $service ) {
				parse_str( (string) wp_parse_url( (string) $service['href'], PHP_URL_QUERY ), $query );
				$url = (string) ( $query['url'] ?? $query['u'] ?? $query['body'] ?? $url );
			}
			return array(
				'url'      => '' !== $url ? $url : $share['url'],
				'heading'  => $given['heading'] ?? null,
				'services' => array_column( (array) ( $given['services'] ?? array() ), 'service' ),
				'copy'     => ! empty( $given['copy'] ),
			);
		}
	);

	if ( isset( $salt_harness_doc['readingMinutes'] ) ) {
		add_filter( 'salt_post_reading_minutes', static fn() => (int) $salt_harness_doc['readingMinutes'] );
	}

	// A service the case gives no public page (seed.php's _salt_harness_no_page).
	add_filter(
		'post_type_link',
		static fn( $link, $post ) => $post instanceof WP_Post && '1' === get_post_meta( $post->ID, '_salt_harness_no_page', true ) ? '' : $link,
		10,
		2
	);

	add_filter(
		'wp_get_attachment_image_src',
		static function ( $image, $id ) {
			$template = get_post_meta( (int) $id, '_salt_harness_url', true );
			if ( is_array( $image ) && is_string( $template ) && '' !== $template ) {
				$image[0] = str_replace( '{width}', (string) (int) $image[1], $template );
			}
			return $image;
		},
		10,
		2
	);

	add_filter(
		'found_posts',
		static function ( $found, $query ) use ( $salt_harness_doc, $salt_harness_route ) {
			if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
				return $found;
			}
			if ( isset( $salt_harness_doc['count'] ) ) {
				return max( (int) $found, (int) $salt_harness_doc['count'] );
			}
			$total = (int) ( $salt_harness_route['pagination']['total'] ?? 0 );
			$per   = max( 1, (int) $query->get( 'posts_per_page' ) );
			return $total > 1 ? max( (int) $found, ( $total - 1 ) * $per + count( (array) $query->posts ) ) : $found;
		},
		10,
		2
	);
}
