<?php
/**
 * Service archive-loop card (spec §6) — thin delegate to x-card-service.
 *
 * Loaded by the native archive/search loops via
 * get_template_part( 'template-parts/content/card', get_post_type() ).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_service_id = (int) get_the_ID();

$salt_icon = get_field( 'icon', $salt_service_id );

salt_component(
	'card-service',
	array(
		'title'    => (string) get_the_title(),
		'url'      => (string) get_the_permalink( $salt_service_id ),
		'summary'  => salt_post_summary( $salt_service_id ),
		'icon'     => is_string( $salt_icon ) ? $salt_icon : '',
		'image_id' => (int) get_post_thumbnail_id( $salt_service_id ),
		'level'    => 2,
	)
);
