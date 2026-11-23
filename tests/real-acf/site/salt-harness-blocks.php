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

/**
 * Decoys for the inline-styles check, with ?salt_harness_decoy on the block
 * page. Each is a <style> without the nonce that the theme must leave that
 * way, so the browser refuses it:
 *   - in the head, before core's own, one reusing the id of the block
 *     page's core-block-supports style with other CSS;
 *   - in the head, one with an unrelated id;
 *   - in the body, one reusing that core id again.
 *
 * @return bool Whether the decoys are on for this request.
 */
function salt_harness_blocks_decoys_on(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A harness switch, read only.
	return (bool) get_option( 'salt_harness_blocks' ) && isset( $_GET['salt_harness_decoy'] );
}

/**
 * The head decoys (wp_head 1, before core prints its styles).
 *
 * @return void
 */
function salt_harness_blocks_head_decoys(): void {
	if ( salt_harness_blocks_decoys_on() ) {
		echo '<style id="core-block-supports-inline-css">.salt-header{outline:4px solid red}</style>';
		echo '<style id="salt-harness-unrelated">.salt-footer{outline:4px solid red}</style>';
	}
}
add_action( 'wp_head', 'salt_harness_blocks_head_decoys', 1 );

/**
 * The body decoy.
 *
 * @return void
 */
function salt_harness_blocks_decoy(): void {
	if ( salt_harness_blocks_decoys_on() ) {
		echo '<style id="core-block-supports-inline-css">.salt-header{outline:4px solid red}</style>';
	}
}
add_action( 'wp_body_open', 'salt_harness_blocks_decoy' );
