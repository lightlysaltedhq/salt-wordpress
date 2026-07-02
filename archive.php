<?php
/**
 * Generic archive (spec §6): native loop framed by the document H1
 * (A11Y01 — native-loop routes always emit an H1).
 *
 * @package Salt
 */

get_header();

salt_the_document_h1( get_the_archive_title() );

if ( have_posts() ) {
	echo '<div class="mx-auto w-full max-w-5xl px-4 py-14 sm:px-6 lg:px-8">';
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content/card', get_post_type() );
	}
	the_posts_pagination();
	echo '</div>';
} else {
	get_template_part( 'template-parts/content/none' );
}

get_footer();
