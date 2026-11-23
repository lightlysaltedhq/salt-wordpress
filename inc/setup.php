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
		'primary'   => (string) ( salt_option( 'brand_primary' ) ?? '' ),
		'secondary' => (string) ( salt_option( 'brand_secondary' ) ?? '' ),
		'accent'    => (string) ( salt_option( 'brand_accent' ) ?? '' ),
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
 * Theme CSS variables from options: uploaded fonts (@font-face + role
 * overrides) and header vars (logo height, sticky scroll offset), in ONE
 * nonce-stamped <style> (CSP blocks inline style attributes — SEC06).
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
	$css = '';
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
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Builders emit only validated same-origin URLs and numeric tokens.
	);
}
add_action( 'wp_head', 'salt_output_theme_vars_css', 20 );
