<?php
/**
 * Work archive-loop card (spec §6) — thin delegate to x-card-work.
 *
 * Loaded by the native archive/search loops via
 * get_template_part( 'template-parts/content/card', get_post_type() ).
 * Surfaces the client + summary fields the Work section cards already show.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_work_id = (int) get_the_ID();

$salt_client  = get_field( 'client', $salt_work_id );
$salt_summary = get_field( 'summary', $salt_work_id );

salt_component(
	'card-work',
	array(
		'title'    => (string) get_the_title(),
		'url'      => (string) get_the_permalink( $salt_work_id ),
		'client'   => is_string( $salt_client ) ? $salt_client : '',
		'summary'  => is_string( $salt_summary ) ? $salt_summary : '',
		'image_id' => (int) get_post_thumbnail_id( $salt_work_id ),
		'level'    => 2,
	)
);
