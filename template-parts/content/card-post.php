<?php
/**
 * Blog archive-loop card (spec §6) — thin delegate to x-card-post.
 *
 * Loaded by the native archive/search loops via
 * get_template_part( 'template-parts/content/card', get_post_type() ).
 * Mirrors the Blog Teaser section's card data (thumbnail, date, excerpt).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_post_id = (int) get_the_ID();

salt_component(
	'card-post',
	array(
		'title'    => (string) get_the_title(),
		'url'      => (string) get_the_permalink( $salt_post_id ),
		'date'     => salt_post_date_text( $salt_post_id ),
		'datetime' => (string) get_the_date( 'c', $salt_post_id ),
		'excerpt'  => (string) get_the_excerpt( $salt_post_id ),
		'image_id' => (int) get_post_thumbnail_id( $salt_post_id ),
		'level'    => 2,
	)
);
