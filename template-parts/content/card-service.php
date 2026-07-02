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

$salt_summary = get_field( 'summary', $salt_service_id );
$salt_icon    = get_field( 'icon', $salt_service_id );

salt_component(
	'card-service',
	array(
		'title'   => (string) get_the_title(),
		'url'     => (string) get_the_permalink( $salt_service_id ),
		'summary' => is_string( $salt_summary ) ? $salt_summary : '',
		'icon'    => is_string( $salt_icon ) ? $salt_icon : '',
		'level'   => 2,
	)
);
