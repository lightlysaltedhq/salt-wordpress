<?php
/**
 * Builder-composed page template (spec §6): the Flexible Content stack.
 *
 * @package Salt
 */

get_header();

while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/flexible-content' );
}

get_footer();
