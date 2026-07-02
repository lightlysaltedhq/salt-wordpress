<?php
/**
 * Search results (spec §6) — native loop with explicit empty state and a
 * guaranteed H1 (A11Y01).
 *
 * @package Salt
 */

get_header();

salt_the_document_h1(
	sprintf(
		/* translators: %s: the search query. */
		__( 'Search results for “%s”', 'salt' ),
		get_search_query()
	)
);

echo '<div class="mx-auto w-full max-w-5xl px-4 py-14 sm:px-6 lg:px-8">';
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content/card', get_post_type() );
	}
	the_posts_pagination();
} else {
	get_template_part( 'template-parts/content/none' );
}
echo '</div>';

get_footer();
