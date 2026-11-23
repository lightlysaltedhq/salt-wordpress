<?php
/**
 * Search, as the contract draws it (salt-contract rc.8 markup/search.json):
 * the h1, the search form keeping the query (SC-019), then either the empty
 * sentence or a results section named by its heading (search__results-heading,
 * SC-012) with the 'more' line when more match than are listed, the results
 * (title link, kind and, when the result has its own excerpt, that note)
 * and the pagination. Without a query, the h1 and the form alone.
 *
 * The form sends q to the theme's /search/ route (salt_search_request());
 * WordPress's own /?s= search draws the same.
 *
 * @package Salt
 */

get_header();

global $wp_query;

$salt_labels = salt_contract_labels( 'search' );
$salt_query  = trim( (string) get_search_query( false ) );
$salt_search = salt_contract_search() ?? array(
	'path'      => salt_contract_url( home_url( '/search/' ) ),
	'maxLength' => 100,
);

salt_the_document_h1( (string) $salt_labels['search'] );
salt_component(
	'search-form',
	array(
		'search' => $salt_search,
		'query'  => '' !== $salt_query ? $salt_query : null,
		'labels' => $salt_labels,
	)
);

if ( '' !== $salt_query ) {
	if ( ! have_posts() ) {
		echo '<p>' . esc_html( salt_view_label( (string) $salt_labels['noResults'], array( 'q' => $salt_query ) ) ) . '</p>';
	} else {
		$salt_found = (int) $wp_query->found_posts;
		echo '<section aria-labelledby="search__results-heading">';
		printf(
			'<h2 id="search__results-heading">%s</h2>',
			esc_html(
				salt_view_label(
					(string) $salt_labels['results'],
					array(
						'n' => number_format_i18n( $salt_found ),
						'q' => $salt_query,
					)
				)
			)
		);
		if ( $salt_found > (int) $wp_query->post_count ) {
			echo '<p>' . esc_html( (string) $salt_labels['more'] ) . '</p>';
		}
		echo '<ul class="salt-search__results" role="list">';
		while ( have_posts() ) {
			the_post();
			$salt_type = get_post_type_object( (string) get_post_type() );
			printf(
				'<li><a href="%1$s">%2$s</a><p>%3$s</p>',
				esc_url( salt_contract_url( (string) get_permalink() ) ),
				esc_html( salt_view_title( get_the_ID() ) ),
				esc_html( salt_view_label( (string) $salt_labels['kind'], array( 'kind' => null !== $salt_type ? (string) $salt_type->labels->singular_name : '' ) ) )
			);
			if ( has_excerpt() ) {
				echo '<p>' . esc_html( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', get_the_ID() ) ) ) . '</p>';
			}
			echo '</li>';
		}
		echo '</ul>';
		$salt_pages = salt_view_pages();
		salt_component(
			'pagination',
			$salt_pages + array(
				'href'   => static fn( int $page ): string => salt_search_page_url( $salt_query, $page ),
				'labels' => $salt_labels,
			)
		);
		echo '</section>';
	}
}

get_footer();
