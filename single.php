<?php
/**
 * Single post (spec §6) — fixed layout: title H1 + content.
 *
 * @package Salt
 */

get_header();

while ( have_posts() ) {
	the_post();
	salt_the_document_h1( get_the_title() );
	echo '<div class="mx-auto w-full max-w-3xl px-4 py-14 sm:px-6 lg:px-8">';
	the_content();
	echo '</div>';
}

get_footer();
