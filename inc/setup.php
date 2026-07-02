<?php
/**
 * Theme setup: supports, menus, editor constraints, module registration.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register core theme supports.
 *
 * @return void
 */
function salt_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	load_theme_textdomain( 'salt', SALT_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'salt_setup' );
