<?php
/**
 * Theme setup: supports, brand token emission, dark-mode init.
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

/**
 * Emit the per-site brand token overrides in <head> (§13, Principle 8).
 *
 * Derived at render from the three admin-set brand colours by the SAME
 * math (inc/colors.php) that produced the compiled neutral defaults —
 * so an unconfigured site simply keeps the defaults (FAIL03). Runs late
 * (priority 20) so the override follows the enqueued stylesheet.
 *
 * @return void
 */
function salt_output_brand_css(): void {
	if ( ! salt_acf_active() ) {
		return; // Compiled neutral defaults apply (DEP01 safe fallback).
	}

	$brand = array(
		'primary'   => (string) ( get_field( 'brand_primary', 'option' ) ?? '' ),
		'secondary' => (string) ( get_field( 'brand_secondary', 'option' ) ?? '' ),
		'accent'    => (string) ( get_field( 'brand_accent', 'option' ) ?? '' ),
	);

	if ( '' === implode( '', $brand ) ) {
		return; // Nothing configured — compiled defaults are already correct.
	}

	printf(
		'<style id="salt-brand-vars" nonce="%s">%s</style>' . "\n",
		esc_attr( salt_csp_nonce() ),
		salt_brand_css( $brand ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated exclusively from validated hex values by salt_brand_css().
	);
}
add_action( 'wp_head', 'salt_output_brand_css', 20 );

/**
 * Inline dark-mode/preferences init (§13a, §14) — one of the two fixed,
 * nonce-stamped inline scripts the CSP allows (SEC06).
 *
 * Runs before paint (blocking, tiny) to avoid a flash of the wrong theme:
 * explicit visitor choice in localStorage wins; otherwise the OS
 * preference applies. Only emitted when the site's dark-mode module is on.
 *
 * @return void
 */
function salt_output_theme_init_script(): void {
	if ( ! salt_module_enabled( 'dark_mode' ) || ! salt_acf_active() || ! get_field( 'dark_mode', 'option' ) ) {
		return;
	}

	$script = 'try{var t=localStorage.getItem("salt-theme");'
		. 'if(t==="dark"||(!t&&window.matchMedia("(prefers-color-scheme: dark)").matches))'
		. '{document.documentElement.setAttribute("data-theme","dark");}}catch(e){}';

	printf(
		'<script nonce="%s">%s</script>' . "\n",
		esc_attr( salt_csp_nonce() ),
		$script // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed literal script, no user input; nonce-stamped for CSP.
	);
}
add_action( 'wp_head', 'salt_output_theme_init_script', 0 );
