<?php
/**
 * Plugin Name: Salt harness: a page of core blocks
 *
 * HARNESS ONLY. Never shipped. tests/real-acf/run.sh copies this file into
 * the throwaway site's wp-content/mu-plugins. Salt draws no block content
 * on its own post types (pages, posts, services and case studies are ACF
 * layouts), but a type a plugin adds is drawn by single.php through
 * the_content, so core's block styles reach the page. While
 * tests/real-acf/php/inline-styles-pages.php has it switched on (the
 * salt_harness_blocks option), this registers such a type, so the
 * inline-styles browser check has block content to draw.
 *
 * @package Salt\Tests\RealAcf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the harness's block-content type while the check has it on.
 *
 * @return void
 */
function salt_harness_register_blocks(): void {
	if ( ! get_option( 'salt_harness_blocks' ) ) {
		return;
	}
	register_post_type(
		'salt_harness_blocks',
		array(
			'label'   => 'Harness blocks',
			'public'  => true,
			'rewrite' => array( 'slug' => 'harness-blocks' ),
		)
	);
}
add_action( 'init', 'salt_harness_register_blocks' );
