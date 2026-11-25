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
	echo '<div class="salt-single">';
	the_content();
	echo '</div>';
}

get_footer();
