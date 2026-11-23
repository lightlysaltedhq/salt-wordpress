<?php
/**
 * The contract's views (salt-contract rc.8 markup/post.json, service.json,
 * case-study.json, archive.json, search.json and not-found.json): the labels
 * they name, the site data they read (the home page, search, the case-study
 * layout), the search route, the pagination window and their cards.
 *
 * The view templates (single-post.php, single-service.php, single-work.php,
 * archive.php, search.php, 404.php) draw; these resolve.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each view's own labels (the contract's `labels.<name>`), in the contract
 * fixtures' English: translatable theme strings, as the founder ruled for
 * the collection labels. salt_contract_labels( $view ) merges them over the
 * shared ones, so the `salt_contract_labels` seam gives any of them. {n},
 * {q}, {kind} and {name} are replaced by salt_view_label(), never appended.
 *
 * @param string $view post|service|case-study|archive|search|not-found, or '' for none.
 * @return array<string,string|array<string,string>>
 */
function salt_view_label_defaults( string $view ): array {
	$pagination = array(
		'nav'      => __( 'Pagination', 'salt' ),
		/* translators: A page link's accessible name; {n} is the page number, replaced where it stands. Keep {n}: nothing is appended. */
		'page'     => __( 'Page {n}', 'salt' ),
		'previous' => __( 'Previous', 'salt' ),
		'next'     => __( 'Next', 'salt' ),
	);
	$search     = array(
		'landmark' => __( 'Search the site', 'salt' ),
		'field'    => __( 'Search', 'salt' ),
		'submit'   => __( 'Search', 'salt' ),
	);

	switch ( $view ) {
		case 'post':
			return array(
				'breadcrumb' => __( 'Breadcrumb', 'salt' ),
				/* translators: A post's byline; {name} is the author's name, or the link to their archive, replaced where it stands. Keep {name}: nothing is appended. */
				'byline'     => __( 'By {name}', 'salt' ),
				/* translators: A post's reading time; {n} is the number of minutes, replaced where it stands. */
				'readTime'   => __( '{n} min read', 'salt' ),
				'tags'       => __( 'Tags', 'salt' ),
				'adjacent'   => __( 'More posts', 'salt' ),
				'newer'      => __( 'Newer', 'salt' ),
				'older'      => __( 'Older', 'salt' ),
				'group'      => __( 'Share this post', 'salt' ),
				'copy'       => __( 'Copy link', 'salt' ),
				'author'     => __( 'About the author', 'salt' ),
				'writtenBy'  => __( 'Written by', 'salt' ),
				'services'   => array(
					'x'         => __( 'X', 'salt' ),
					'facebook'  => __( 'Facebook', 'salt' ),
					'linkedin'  => __( 'LinkedIn', 'salt' ),
					'whatsapp'  => __( 'WhatsApp', 'salt' ),
					'bluesky'   => __( 'Bluesky', 'salt' ),
					'reddit'    => __( 'Reddit', 'salt' ),
					'pinterest' => __( 'Pinterest', 'salt' ),
					'email'     => __( 'Email', 'salt' ),
				),
			);
		case 'service':
			return array(
				'benefits'        => __( 'What you get', 'salt' ),
				'price'           => __( 'From', 'salt' ),
				'relatedServices' => __( 'Related services', 'salt' ),
			);
		case 'case-study':
			return array(
				'back'      => __( 'All projects', 'salt' ),
				'results'   => __( 'The project in numbers', 'salt' ),
				'challenge' => __( 'The challenge', 'salt' ),
				'solution'  => __( 'What we did', 'salt' ),
				'gallery'   => __( 'Gallery', 'salt' ),
				'liveSite'  => __( 'Visit the site', 'salt' ),
				'services'  => __( 'Services delivered', 'salt' ),
				/* translators: The accessible name of the case study's aside, which lists the project's facts. */
				'details'   => __( 'Project details', 'salt' ),
				/* translators: The label of the case study's client row in its project details. */
				'client'    => __( 'Client', 'salt' ),
				/* translators: The label of the case study's sector row in its project details. */
				'sector'    => __( 'Sector', 'salt' ),
			);
		case 'archive':
			return $pagination + array(
				'empty'  => __( 'There are no posts to show yet.', 'salt' ),
				'social' => __( 'Social profiles', 'salt' ),
			);
		case 'search':
			return array(
				'search'    => __( 'Search', 'salt' ),
				/* translators: The search results heading; {n} is the number of results and {q} the query, each replaced where it stands. */
				'results'   => __( '{n} results for “{q}”', 'salt' ),
				'more'      => __( 'More pages match than are listed here. Try a more specific search.', 'salt' ),
				/* translators: The empty search; {q} is the query, replaced where it stands. */
				'noResults' => __( 'Nothing matched “{q}”. Try a different word.', 'salt' ),
				/* translators: A search result's kind; {kind} is the post type's translated singular name, such as Post or Page, replaced where it stands. */
				'kind'      => __( '{kind}', 'salt' ),
				'nav'       => __( 'Search results pages', 'salt' ),
			) + $pagination + $search;
		case 'not-found':
			return array(
				'notFound' => __( 'Page not found', 'salt' ),
				'message'  => __( 'The page you were looking for has moved or never existed.', 'salt' ),
				'home'     => __( 'Back to the home page', 'salt' ),
			) + $search;
	}
	return array();
}

/**
 * A label with its named placeholders replaced where they stand: {n}, {q},
 * {kind} and {name} (README "Labels", SC-031). Nothing is appended: a label
 * without a placeholder, and an unknown placeholder, are drawn as written.
 *
 * @param string               $label The label.
 * @param array<string,string> $vars  n, q, kind, name.
 * @return string
 */
function salt_view_label( string $label, array $vars = array() ): string {
	$pairs = array();
	foreach ( $vars as $name => $value ) {
		$pairs[ '{' . $name . '}' ] = (string) $value;
	}
	return strtr( $label, $pairs );
}

/**
 * The site's home page (the contract's `site.home`), or null when it has
 * none: the static front page when the site shows one and it is published,
 * the posts the home lists when it shows its posts there, and none when it
 * is set to show a static page and none is chosen or published.
 *
 * @return array{href: string, title: string}|null
 */
function salt_contract_home(): ?array {
	if ( 'page' === get_option( 'show_on_front' ) ) {
		$front = (int) get_option( 'page_on_front' );
		if ( $front <= 0 || 'publish' !== get_post_status( $front ) ) {
			return null;
		}
		return array(
			'href'  => salt_contract_url( (string) get_permalink( $front ) ),
			'title' => (string) get_the_title( $front ),
		);
	}
	return array(
		'href'  => salt_contract_url( home_url( '/' ) ),
		'title' => (string) get_bloginfo( 'name' ),
	);
}

/**
 * The site's search (the contract's `site.search`), or null when search is
 * not offered: the form's action, the theme's /search/ route
 * (salt_search_request()), and the query's maximum length. The
 * `salt_contract_search` filter is the seam: null turns search off.
 *
 * @return array{path: string, maxLength: int}|null
 */
function salt_contract_search(): ?array {
	$search = apply_filters(
		'salt_contract_search',
		array(
			'path'      => salt_contract_url( home_url( '/search/' ) ),
			'maxLength' => 100,
		)
	);
	if ( ! is_array( $search ) || '' === trim( (string) ( $search['path'] ?? '' ) ) ) {
		return null;
	}
	return array(
		'path'      => (string) $search['path'],
		'maxLength' => max( 1, (int) ( $search['maxLength'] ?? 100 ) ),
	);
}

/**
 * Route the contract's search form ('request'): /search/?q=<query> and
 * /search/?q=<query>&page=<n> are WordPress's search for the query, page n.
 * The contract's input is named q (markup/search-form.json); routing stays
 * WordPress's own (SC-003), so this only maps it onto s and paged, and
 * WordPress's own /?s= search still answers as before.
 *
 * Only when nothing else owns /search/: a page, post or attachment at that
 * path, a post type archive, a term or an author WordPress resolved it to
 * is served as it was. q is handed over exactly as s arrives from a form
 * ($_GET included), so WP_Query cleans it as it cleans s, and the page is a
 * search (is_search()) with core's noindex robots. Read from the path: no
 * rewrite rules are added and none need flushing.
 *
 * @param array<string,mixed> $vars The parsed query vars.
 * @return array<string,mixed>
 */
function salt_search_request( array $vars ): array {
	if ( is_admin() || isset( $vars['rest_route'] ) || isset( $vars['s'] ) ) {
		return $vars;
	}
	$base = (string) wp_parse_url( home_url( '/search/' ), PHP_URL_PATH );
	$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() sanitises the path.
	if ( untrailingslashit( $path ) !== untrailingslashit( $base ) ) {
		return $vars;
	}
	foreach ( array( 'post_type', 'category_name', 'cat', 'tag', 'taxonomy', 'term', 'author', 'author_name', 'feed' ) as $owner ) {
		if ( ! empty( $vars[ $owner ] ) ) {
			return $vars;
		}
	}
	foreach ( array( 'pagename', 'name', 'attachment' ) as $owner ) {
		if ( ! empty( $vars[ $owner ] ) && null !== get_page_by_path( (string) $vars[ $owner ], OBJECT, array_values( get_post_types( array( 'public' => true ) ) ) ) ) {
			return $vars;
		}
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- A public search form, as WordPress's own ?s= is; WP_Query cleans s.
	$query = isset( $_GET['q'] ) && is_string( $_GET['q'] ) ? $_GET['q'] : '';
	$page  = isset( $_GET['page'] ) && is_scalar( $_GET['page'] ) ? absint( $_GET['page'] ) : 0;
	// As a ?s= form submission: WP_Query reads $_GET['s'] to know the query is not URL-encoded.
	$_GET['s'] = $query;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
	return array(
		's'     => $query,
		'paged' => $page > 1 ? $page : 0,
	);
}
add_filter( 'request', 'salt_search_request' );

/**
 * A search results page's address on the theme's /search/ route.
 *
 * @param string $query The query.
 * @param int    $page  The page, from 1.
 * @return string
 */
function salt_search_page_url( string $query, int $page ): string {
	$search = salt_contract_search();
	$path   = null !== $search ? $search['path'] : salt_contract_url( home_url( '/search/' ) );
	$args   = array( 'q' => rawurlencode( $query ) );
	if ( $page > 1 ) {
		$args['page'] = $page;
	}
	return add_query_arg( $args, $path );
}

/**
 * The pagination window (markup/pagination.json): the first page, the last,
 * and the current page with one neighbour each side; a run left out is one
 * gap, and a gap of exactly one page is drawn as that page (PURE).
 *
 * @param int $current The current page, from 1.
 * @param int $total   How many pages.
 * @return array<int,int|null> Page numbers in order, null for a gap.
 */
function salt_pagination_window( int $current, int $total ): array {
	if ( $total < 2 ) {
		return array();
	}
	$current = max( 1, min( $total, $current ) );
	$pages   = array_unique( array( 1, $current - 1, $current, $current + 1, $total ) );
	$pages   = array_values( array_filter( $pages, static fn( int $n ): bool => $n >= 1 && $n <= $total ) );
	sort( $pages );
	$window = array();
	$last   = 0;
	foreach ( $pages as $page ) {
		if ( 2 === $page - $last ) {
			$window[] = $last + 1;
		} elseif ( $page - $last > 2 ) {
			$window[] = null;
		}
		$window[] = $page;
		$last     = $page;
	}
	return $window;
}

/**
 * The main query's pagination: its current page and how many there are.
 *
 * @return array{current: int, total: int}
 */
function salt_view_pages(): array {
	global $wp_query;
	return array(
		'current' => max( 1, (int) get_query_var( 'paged' ) ),
		'total'   => $wp_query instanceof WP_Query ? (int) $wp_query->max_num_pages : 1,
	);
}

/**
 * A post as the contract's card for a view, with its link drawn
 * root-relative (salt_contract_url()).
 *
 * @param WP_Post $post   The item.
 * @param string  $source Contract source id (posts, services).
 * @param string  $sizes  The image slot's sizes.
 * @return array<string,mixed>|null
 */
function salt_contract_view_card( WP_Post $post, string $source, string $sizes ): ?array {
	$card = salt_collection_card( $post, $source, array( 'sizes' => $sizes ) );
	if ( null !== $card && null !== $card['href'] ) {
		$card['href'] = salt_contract_url( (string) $card['href'] );
	}
	return $card;
}

/**
 * A post's title as the contract's views draw it: the stored title as
 * written, without WordPress's typographic filters (no curly quotes), and
 * without markup.
 *
 * @param int|WP_Post $post The post.
 * @return string
 */
function salt_view_title( $post ): string {
	return trim( wp_strip_all_tags( (string) get_post_field( 'post_title', $post, 'raw' ) ) );
}

/**
 * A post's share links as the post view draws them (markup/share-links.json):
 * the visible heading, each service's intent for the address shared, in
 * order (salt_share_links()), and whether the copy control is offered. The
 * `salt_share_settings` filter decides the address, heading, services and
 * copy control; no services and no copy control draws no share links.
 *
 * @param int $post_id The post.
 * @return array{heading: string|null, services: array<int,array{service: string, href: string}>, copy: bool}
 */
function salt_post_share( int $post_id ): array {
	$settings = apply_filters(
		'salt_share_settings',
		array(
			'url'      => (string) get_permalink( $post_id ),
			'heading'  => __( 'Share', 'salt' ),
			'services' => salt_share_default_services(),
			'copy'     => true,
		),
		$post_id
	);
	$keys     = array_values( (array) ( $settings['services'] ?? array() ) );
	$services = array();
	foreach ( array() !== $keys ? salt_share_links( (string) ( $settings['url'] ?? '' ), salt_view_title( $post_id ), $keys ) : array() as $link ) {
		$services[] = array(
			'service' => $link['key'],
			'href'    => $link['url'],
		);
	}
	return array(
		'heading'  => isset( $settings['heading'] ) ? (string) $settings['heading'] : null,
		'services' => $services,
		'copy'     => ! empty( $settings['copy'] ),
	);
}

/**
 * The section tones (contract/fields/_section-settings.json `tone`).
 *
 * @return string[]
 */
function salt_section_tones(): array {
	return array( 'surface', 'surface-alt', 'brand-tint', 'surface-inverse' );
}

/**
 * The case-study view's layout, which a site sets once for all its case
 * studies (the contract's `site.caseStudy`) on Site Settings → Case Studies
 * (inc/fields/options-case-studies.php): the header plain or a toned band,
 * and the band's tone and dark tone (the tone itself when unset).
 *
 * @return array{header: string, headerTone: string, headerToneDark: string}
 */
function salt_case_study_settings(): array {
	$tones = salt_section_tones();
	$tone  = in_array( salt_option( 'case_study_header_tone' ), $tones, true ) ? (string) salt_option( 'case_study_header_tone' ) : 'surface';
	$dark  = in_array( salt_option( 'case_study_header_tone_dark' ), $tones, true ) ? (string) salt_option( 'case_study_header_tone_dark' ) : $tone;
	return array(
		'header'         => 'band' === salt_option( 'case_study_header' ) ? 'band' : 'plain',
		'headerTone'     => $tone,
		'headerToneDark' => $dark,
	);
}

/**
 * Hand the share script its status labels (labels.copied and
 * labels.copyFailed, which the contract's copy control announces) as
 * window.saltShare, when the page enqueued it ('wp_enqueue_scripts', after
 * salt_enqueue_section_assets()).
 *
 * @return void
 */
function salt_share_script_labels(): void {
	if ( ! wp_script_is( 'salt-section-share', 'enqueued' ) ) {
		return;
	}
	wp_add_inline_script(
		'salt-section-share',
		'window.saltShare = ' . wp_json_encode(
			array(
				'labels' => array(
					'copied'     => __( 'Link copied', 'salt' ),
					'copyFailed' => __( 'Could not copy the link', 'salt' ),
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'salt_share_script_labels', 21 );
