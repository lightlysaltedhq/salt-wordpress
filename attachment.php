<?php
/**
 * Attachment policy (spec §6, review COMPL03 — decided, no OR):
 * attachment URLs 301-redirect to the parent post; orphaned attachments
 * are a 404. Attachment pages are thin content and never render.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_parent_id = (int) wp_get_post_parent_id( get_the_ID() );

if ( $salt_parent_id > 0 ) {
	$salt_parent_url = get_permalink( $salt_parent_id );
	if ( is_string( $salt_parent_url ) && '' !== $salt_parent_url ) {
		wp_safe_redirect( $salt_parent_url, 301 );
		exit;
	}
}

global $wp_query;
$wp_query->set_404();
status_header( 404 );
require __DIR__ . '/404.php';
