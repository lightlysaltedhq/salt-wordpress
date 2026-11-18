<?php
/**
 * Plugin Name: Salt harness: offline site
 *
 * HARNESS ONLY. Never shipped. tests/real-acf/run.sh copies this file into
 * the throwaway site's wp-content/mu-plugins before installing it; unlike
 * the emitted-sections mu-plugin, it stays when the theme registers the
 * groups itself.
 *
 * The site blocks outbound HTTP (WP_HTTP_BLOCK_EXTERNAL), so wp-admin's
 * update checks would each log a warning. These answer them as already done,
 * with nothing to update, so they never reach the network.
 *
 * @package Salt\Tests\RealAcf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An update transient that says the check just ran and found nothing.
 *
 * @return object
 */
function salt_harness_no_updates(): object {
	return (object) array(
		'last_checked'    => time(),
		'version_checked' => get_bloginfo( 'version' ),
		'updates'         => array(),
		'response'        => array(),
		'translations'    => array(),
		'checked'         => array(),
	);
}
add_filter( 'pre_site_transient_update_core', 'salt_harness_no_updates' );
add_filter( 'pre_site_transient_update_plugins', 'salt_harness_no_updates' );
add_filter( 'pre_site_transient_update_themes', 'salt_harness_no_updates' );
