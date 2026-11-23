<?php
/**
 * Security baseline: CSP with per-request nonce for the fixed set of
 * inline head/init scripts, plus hardening headers (spec §16b; review SEC06).
 *
 * Front-end only — wp-admin runs its own inline scripts and is excluded.
 * salt controls exactly which inline scripts exist (dark-mode/preferences
 * init + brand token vars), each stamped with salt_csp_nonce(). Sites that
 * add plugins with inline scripts adjust via the 'salt_csp_policy' filter
 * (priority band 20+).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The per-request CSP nonce (stable within a request).
 *
 * @return string
 */
function salt_csp_nonce(): string {
	static $nonce = null;

	if ( null === $nonce ) {
		$nonce = rtrim( strtr( base64_encode( random_bytes( 18 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard CSP nonce encoding, not obfuscation.
	}

	return $nonce;
}

/**
 * The security headers salt ships (SEC06).
 *
 * Pure function → unit-testable; filter 'salt_csp_policy' adjusts the CSP
 * only. data: images are allowed for inline SVG favicons/placeholders;
 * everything else is same-origin. No third party appears here BY DESIGN
 * (PRIV01: the consent stub is fail-closed and nothing external loads).
 *
 * @return array<string,string> Header name => value.
 */
function salt_security_headers(): array {
	$nonce = salt_csp_nonce();

	// The head's scriptless header style is printed without a nonce, so its
	// markup is the contract's (salt_output_header_noscript()): its hash allows it.
	$styles = "'self' 'nonce-{$nonce}'";
	if ( function_exists( 'salt_header_noscript_css' ) ) {
		$styles .= " 'sha256-" . base64_encode( hash( 'sha256', salt_header_noscript_css(), true ) ) . "'"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A CSP hash source is base64 by definition.
	}

	$csp = implode(
		'; ',
		array(
			"default-src 'self'",
			"script-src 'self' 'nonce-{$nonce}'",
			"style-src {$styles}",
			"img-src 'self' data:",
			"font-src 'self'",
			"connect-src 'self'",
			"object-src 'none'",
			"base-uri 'self'",
			"form-action 'self'",
			"frame-ancestors 'self'",
		)
	);

	return array(
		'Content-Security-Policy' => (string) apply_filters( 'salt_csp_policy', $csp ),
		'X-Content-Type-Options'  => 'nosniff',
		'Referrer-Policy'         => 'strict-origin-when-cross-origin',
		'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
	);
}

/**
 * Send the headers on front-end requests.
 *
 * @return void
 */
function salt_send_security_headers(): void {
	if ( is_admin() || headers_sent() ) {
		return;
	}

	foreach ( salt_security_headers() as $name => $value ) {
		header( $name . ': ' . $value );
	}
}
add_action( 'send_headers', 'salt_send_security_headers' );

/**
 * Remove WP core's inline emoji script — it has no nonce (would violate
 * the CSP) and is dead weight (§7 carbon).
 *
 * @return void
 */
function salt_disable_emojis(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'salt_disable_emojis' );

/**
 * Stamp the CSP nonce on the WP Consent API's settings script
 * ('wp_inline_script_attributes'). The plugin hands its settings
 * (window.consent_api, the consent type) to its script through
 * wp_localize_script(), which WordPress prints as an inline script with the
 * id wp-consent-api-js-extra and no nonce, so the CSP would refuse it and
 * wp_has_consent() would throw. Only that id is stamped: any other inline
 * script stays refused unless it carries the nonce itself.
 *
 * @param array<string,string|bool> $attributes The inline script's attributes.
 * @return array<string,string|bool>
 */
function salt_consent_api_script_nonce( array $attributes ): array {
	if ( 'wp-consent-api-js-extra' === ( $attributes['id'] ?? '' ) ) {
		$attributes['nonce'] = salt_csp_nonce();
	}
	return $attributes;
}
add_filter( 'wp_inline_script_attributes', 'salt_consent_api_script_nonce' );

/**
 * Stamp the CSP nonce on WordPress core's speculation rules
 * ('wp_inline_script_attributes'). Core prints them (wp_print_speculation_rules())
 * as an inline <script type="speculationrules"> with no id and no nonce,
 * so the CSP refused them and the prefetch they ask for never happened.
 * Matched by that type alone: any other inline script stays refused
 * unless it carries the nonce itself.
 *
 * @param array<string,string|bool> $attributes The inline script's attributes.
 * @return array<string,string|bool>
 */
function salt_speculation_rules_script_nonce( array $attributes ): array {
	if ( 'speculationrules' === ( $attributes['type'] ?? '' ) ) {
		$attributes['nonce'] = salt_csp_nonce();
	}
	return $attributes;
}
add_filter( 'wp_inline_script_attributes', 'salt_speculation_rules_script_nonce' );

/**
 * Link core's stylesheets instead of inlining them ('styles_inline_size_limit').
 * wp_maybe_inline_styles() (wp-includes/script-loader.php) prints any
 * enqueued style that has a file path as an inline <style> until the page's
 * inlined total reaches this limit (40,000 bytes by default). That is how
 * wp-block-library, classic-theme-styles and the on-demand block styles
 * arrived as inline styles with no nonce, which the CSP refuses (SEC06).
 * At 0 nothing is inlined, so each one is a <link> that 'self' allows, and
 * the on-demand placeholder core adds to wp-block-library's inline style is
 * all that style holds, so wp_hoist_late_printed_styles() removes it.
 *
 * @return int
 */
function salt_styles_inline_size_limit(): int {
	return 0;
}
add_filter( 'styles_inline_size_limit', 'salt_styles_inline_size_limit' );

/**
 * Dequeue core's sizes="auto" image fix ('wp_enqueue_scripts').
 * wp_enqueue_img_auto_sizes_contain_css_fix() (wp-includes/media.php,
 * wp_head 0) registers wp-img-auto-sizes-contain as an inline-only style,
 * which prints as a <style> with no nonce that the CSP refuses. Its one rule
 * is in the theme's stylesheet instead (assets/src/css/app.css), so the
 * images keep core's intrinsic size.
 *
 * @return void
 */
function salt_dequeue_img_auto_sizes_contain(): void {
	wp_dequeue_style( 'wp-img-auto-sizes-contain' );
}
add_action( 'wp_enqueue_scripts', 'salt_dequeue_img_auto_sizes_contain' );

/**
 * The style handles whose inline CSS core builds per page and can only
 * print inline, so the theme stamps their <style> with the CSP nonce
 * (salt_stamp_core_inline_styles()): the admin bar's offset and print rule
 * (wp-includes/admin-bar.php), and the block-support classes, duotone
 * filters, style variations and custom CSS of the blocks on the page
 * (wp-includes/block-supports/). WP_Styles offers no filter for an inline
 * style's attributes, and the block styles are printed at wp_footer and
 * moved into the head by core's output buffer.
 *
 * @return string[]
 */
function salt_core_inline_style_handles(): array {
	return array(
		'admin-bar',
		'core-block-supports',
		'core-block-supports-duotone',
		'block-style-variation-styles',
		'wp-block-custom-css',
	);
}

/**
 * The CSS WordPress is about to print for those handles in this request
 * ('print_styles_array', as WP_Styles starts each batch). Each one is kept
 * exactly as WP_Styles::do_item() writes it inside its <style>, keyed by
 * that element's id, for salt_stamp_core_inline_styles() to match.
 *
 * @param string[]|mixed $to_do The handles WP_Styles is about to print.
 * @return string[]|mixed
 */
function salt_record_core_inline_styles( $to_do ) {
	if ( ! is_array( $to_do ) || ! function_exists( 'wp_styles' ) ) {
		return $to_do;
	}
	foreach ( array_intersect( salt_core_inline_style_handles(), $to_do ) as $handle ) {
		$css = wp_styles()->print_inline_style( $handle, false );
		if ( is_string( $css ) && '' !== $css ) {
			$GLOBALS['salt_core_inline_styles'][ $handle . '-inline-css' ][] = "\n{$css}\n";
		}
	}
	return $to_do;
}
add_filter( 'print_styles_array', 'salt_record_core_inline_styles', PHP_INT_MAX );

/**
 * Stamp the CSP nonce on core's own inline styles in the <head>
 * ('wp_template_enhancement_output_buffer', after core moves its late
 * styles into the head at priority 10). A <style> is stamped only when its
 * id is one salt_record_core_inline_styles() recorded and its text is,
 * exactly, CSS WordPress produced for that handle in this request; each
 * recorded CSS stamps once. Anything else stays refused: a <style> with
 * another id or other CSS, one in the <body>, or every one of them when the
 * record is missing.
 *
 * @param string $buffer The page's HTML.
 * @return string
 */
function salt_stamp_core_inline_styles( string $buffer ): string {
	$recorded = $GLOBALS['salt_core_inline_styles'] ?? array();
	if ( ! $recorded || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $buffer;
	}

	$processor = new WP_HTML_Tag_Processor( $buffer );
	while ( $recorded && $processor->next_tag() ) {
		if ( 'BODY' === $processor->get_tag() ) {
			break;
		}
		$id = $processor->get_attribute( 'id' );
		if ( 'STYLE' !== $processor->get_tag() || ! is_string( $id ) || ! isset( $recorded[ $id ] ) || null !== $processor->get_attribute( 'nonce' ) ) {
			continue;
		}
		// The text as the tag processor reads it back: newlines normalised, NUL replaced.
		$text = $processor->get_modifiable_text();
		foreach ( $recorded[ $id ] as $key => $css ) {
			if ( str_replace( array( "\r\n", "\r", "\x00" ), array( "\n", "\n", "\u{FFFD}" ), $css ) === $text ) {
				$processor->set_attribute( 'nonce', salt_csp_nonce() );
				unset( $recorded[ $id ][ $key ] );
				break;
			}
		}
		if ( ! $recorded[ $id ] ) {
			unset( $recorded[ $id ] );
		}
	}

	return $processor->get_updated_html();
}
add_filter( 'wp_template_enhancement_output_buffer', 'salt_stamp_core_inline_styles', 20 );

/**
 * Print the Customizer's Additional CSS with the CSP nonce (wp_head 101),
 * in place of core's wp_custom_css_cb(), which prints the same
 * <style id="wp-custom-css"> without one, so the CSP refused it. Same
 * source (wp_get_custom_css()), same place, same check that the CSS cannot
 * close the element early.
 *
 * @return void
 */
function salt_custom_css_cb(): void {
	$styles = wp_get_custom_css();
	if ( ! $styles && ! is_customize_preview() ) {
		return;
	}
	$processor = new WP_HTML_Tag_Processor( '<style></style>' );
	$processor->next_tag();
	$processor->set_attribute( 'id', 'wp-custom-css' );
	$processor->set_attribute( 'nonce', salt_csp_nonce() );
	$processor->set_modifiable_text( "\n{$styles}\n" );
	echo $processor->get_updated_html() . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by WP_HTML_Tag_Processor, which refuses text that would close the element, as core's wp_custom_css_cb() does.
}

/**
 * Swap core's Additional CSS printer for salt_custom_css_cb() at its
 * priority ('wp_head', priority 0), so a site that unhooked core's keeps it
 * off.
 *
 * @return void
 */
function salt_swap_custom_css_cb(): void {
	$priority = has_action( 'wp_head', 'wp_custom_css_cb' );
	if ( false !== $priority ) {
		remove_action( 'wp_head', 'wp_custom_css_cb', $priority );
		add_action( 'wp_head', 'salt_custom_css_cb', $priority );
	}
}
add_action( 'wp_head', 'salt_swap_custom_css_cb', 0 );
