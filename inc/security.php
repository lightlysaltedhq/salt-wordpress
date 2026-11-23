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
