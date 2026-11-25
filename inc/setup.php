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

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'salt' ),
			'footer'  => __( 'Footer navigation', 'salt' ),
		)
	);
}
add_action( 'after_setup_theme', 'salt_setup' );

/**
 * Emit the per-site brand token overrides in <head> (§13, Principle 8).
 *
 * Derived at render from the three admin-set brand colours by the SAME
 * math (inc/colors.php) that produced the compiled neutral defaults —
 * so an unconfigured site simply keeps the defaults (FAIL03). The admin-set
 * design dials follow (salt_dials_css()); a dial left unset, or on its
 * default, prints nothing. Runs late (priority 20) so the override follows
 * the enqueued stylesheet.
 *
 * @return void
 */
function salt_output_brand_css(): void {
	if ( ! salt_acf_active() ) {
		return; // Compiled neutral defaults apply (DEP01 safe fallback).
	}

	$brand = array(
		'primary'   => (string) ( salt_option( 'brand_primary' ) ?? '' ),
		'secondary' => (string) ( salt_option( 'brand_secondary' ) ?? '' ),
		'accent'    => (string) ( salt_option( 'brand_accent' ) ?? '' ),
	);
	$dials = array();
	foreach ( salt_design_dials() as $id => $dial ) {
		$dials[ $id ] = (string) ( salt_option( $dial['field'] ) ?? '' );
	}

	// No colour set: the compiled colour defaults stand, and only the dials print.
	$css = '' === implode( '', $brand ) ? salt_dials_css( $dials ) : salt_brand_css( $brand + $dials );
	if ( '' === $css ) {
		return; // Nothing configured — compiled defaults are already correct.
	}

	printf(
		'<style id="salt-brand-vars" nonce="%s">%s</style>' . "\n",
		esc_attr( salt_csp_nonce() ),
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated exclusively from validated hex values and the dial table's own values (choices outside it are dropped) by salt_brand_css().
	);
}
add_action( 'wp_head', 'salt_output_brand_css', 20 );

/**
 * Build the pre-paint preferences init script (PURE — unit-tested).
 *
 * Reads localStorage keys and sets <html> data-* attributes before paint:
 * salt-theme (dark), salt-motion, salt-contrast, salt-grayscale, salt-scale.
 * Only validated enum branches are interpolated — no user-typed strings —
 * which is what keeps the phpcs escape justification honest.
 *
 * @param array{dark: bool, prefs: bool, motion_default_reduce: bool} $flags Feature flags.
 * @return string '' when nothing is enabled.
 */
function salt_theme_init_js( array $flags ): string {
	$dark   = ! empty( $flags['dark'] );
	$prefs  = ! empty( $flags['prefs'] );
	$reduce = ! empty( $flags['motion_default_reduce'] );

	if ( ! $dark && ! $prefs && ! $reduce ) {
		return '';
	}

	$js = 'try{var d=document.documentElement,g=function(k){return localStorage.getItem(k);};';

	if ( $dark ) {
		$js .= 'var t=g("salt-theme");'
			. 'if(t==="dark"||(!t&&window.matchMedia("(prefers-color-scheme: dark)").matches))'
			. '{d.setAttribute("data-theme","dark");}';
	}

	if ( $prefs || $reduce ) {
		$js .= 'var m=g("salt-motion");'
			. 'if(m==="reduce"' . ( $reduce ? '||!m' : '' ) . '){d.setAttribute("data-motion","reduce");}';
	}

	if ( $prefs ) {
		$js .= 'var c=g("salt-contrast");'
			. 'if(c==="high"||(!c&&window.matchMedia("(prefers-contrast: more)").matches))'
			. '{d.setAttribute("data-contrast","high");}'
			. 'if(g("salt-grayscale")==="1"){d.setAttribute("data-grayscale","");}'
			. 'var s=g("salt-scale");'
			. 'if(s==="-1"||s==="1"||s==="2"){d.setAttribute("data-scale",s);}';
	}

	return $js . '}catch(e){}';
}

/**
 * Inline preferences init (§13a, §14) — one of the two fixed, nonce-stamped
 * inline scripts the CSP allows (SEC06). Runs before paint (blocking, tiny)
 * to avoid a flash of the wrong theme/prefs.
 *
 * @return void
 */
function salt_output_theme_init_script(): void {
	if ( ! salt_acf_active() ) {
		return;
	}

	$script = salt_theme_init_js(
		array(
			'dark'                  => salt_module_enabled( 'dark_mode' ) && (bool) salt_option( 'dark_mode' ),
			'prefs'                 => salt_module_enabled( 'display_preferences' ) && (bool) salt_option( 'display_preferences_enabled' ),
			'motion_default_reduce' => (bool) salt_option( 'reduce_motion' ),
		)
	);

	if ( '' === $script ) {
		return;
	}

	printf(
		'<script nonce="%s">%s</script>' . "\n",
		esc_attr( salt_csp_nonce() ),
		$script // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from validated enum branches only (salt_theme_init_js); nonce-stamped for CSP.
	);
}
add_action( 'wp_head', 'salt_output_theme_init_script', 0 );

/**
 * WordPress core's global styles (the theme.json presets, element and
 * layout styles), for the theme's nonce'd <style>. Core prints them through
 * wp_enqueue_global_styles() (wp-includes/script-loader.php) as an inline
 * <style id="global-styles-inline-css"> with no nonce, which the CSP refuses,
 * so that is unhooked below and salt_output_theme_vars_css() prints the same
 * stylesheet instead. Core, for a classic theme loading block styles on
 * demand, leaves out each block's own nodes and adds them beside the
 * block's style; unhooked, that never happens, so the whole stylesheet is
 * printed. Core prints it before the theme's stylesheets; after them it
 * changes no computed style on the representative pages (measured in
 * Chromium), since its rules are either custom properties, zero-specificity
 * :where() rules or block classes the theme's stylesheets do not style.
 *
 * @return string
 */
function salt_global_styles_css(): string {
	if ( ! function_exists( 'wp_get_global_stylesheet' ) ) {
		return '';
	}
	$css = (string) wp_get_global_stylesheet();
	// Core's own printer refuses text that would close the element early.
	return false === stripos( $css, '</style' ) ? $css : '';
}
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );

/**
 * Keep core's global-styles handle registered, empty ('wp_enqueue_scripts').
 * Core's block custom CSS (wp-block-custom-css) and block style variations
 * (block-style-variation-styles) depend on it, and WordPress drops a style
 * whose dependency is missing. With no source and no inline CSS it prints
 * nothing.
 *
 * @return void
 */
function salt_register_global_styles_handle(): void {
	wp_register_style( 'global-styles', false, array(), null );
}
add_action( 'wp_enqueue_scripts', 'salt_register_global_styles_handle' );

/**
 * Theme CSS variables from options: uploaded fonts (@font-face + role
 * overrides) and header vars (logo height, sticky scroll offset), after
 * core's global styles (salt_global_styles_css()), in ONE nonce-stamped
 * <style> (CSP blocks inline style attributes and un-nonced styles — SEC06).
 *
 * The builders live with their features (inc/services/fonts.php,
 * site-header wiring) and are optional at load time — this emitter
 * composes whatever is registered.
 *
 * @return void
 */
function salt_output_theme_vars_css(): void {
	// Each builder degrades without ACF: the fonts give none, the header
	// still gives the 48px logo default.
	$css = salt_global_styles_css();
	if ( function_exists( 'salt_fonts_runtime_css' ) ) {
		$css .= salt_fonts_runtime_css();
	}
	if ( function_exists( 'salt_header_runtime_css' ) ) {
		$css .= salt_header_runtime_css();
	}

	if ( '' === $css ) {
		return;
	}

	printf(
		'<style id="salt-theme-vars" nonce="%s">%s</style>' . "\n",
		esc_attr( salt_csp_nonce() ),
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Builders emit only validated same-origin URLs and numeric tokens; core's global stylesheet is checked as core's own printer checks it.
	);
}
add_action( 'wp_head', 'salt_output_theme_vars_css', 20 );
