<?php
/**
 * Shared helpers.
 *
 * Pluggable salt_* helpers live here (each wrapped in function_exists so a
 * child theme — loaded first — can replace it; see docs/contracts/OVERRIDES.md).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'salt_log' ) ) {
	/**
	 * Log a salt diagnostic (PLUGGABLE — ARCH07).
	 *
	 * The fail-loud channel behind the args contract, the section fallback
	 * and the resolver warnings: in production these must reach error_log,
	 * never vanish (ARCH05/FAIL01).
	 *
	 * @param string $message Log line.
	 * @return void
	 */
	function salt_log( string $message ): void {
		error_log( 'salt: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate production logging channel (ARCH05).
	}
}

/**
 * Whether debug behaviour (throwing assertions) is active.
 *
 * Filterable so the test suite — and nothing else — can exercise the
 * production path; production sites simply follow WP_DEBUG.
 *
 * @return bool
 */
function salt_is_debug(): bool {
	return (bool) apply_filters( 'salt_is_debug', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Read a theme OPTION field, DEP01-safe.
 *
 * The single accessor every runtime option read goes through. When ACF is
 * absent its get_field() does not exist; rather than let ~25 call sites
 * fatal (or shadow ACF's own get_field() and collide on activation), this
 * returns null so each consumer degrades to its documented default. The
 * theme then renders the safe fallback + the DEP01 admin notice.
 *
 * @param string $selector Option field name.
 * @return mixed The stored value, or null when ACF is unavailable.
 */
function salt_option( string $selector ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}
	return get_field( $selector, 'option' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- ACF read (guarded above); the ONE place get_field is called for options.
}

/**
 * A collection item's `summary`: on WordPress it is stored in the post's
 * own excerpt (salt-contract collections, platforms.WordPress.native). The
 * stored excerpt only — never one WordPress makes up from the body.
 *
 * @param WP_Post|int $post The post or its ID.
 * @return string Trimmed; '' when there is none.
 */
function salt_post_summary( $post ): string {
	$post = $post instanceof WP_Post ? $post : get_post( (int) $post );
	return $post instanceof WP_Post ? trim( (string) $post->post_excerpt ) : '';
}

/**
 * The share of five stars a rating fills, as the contract's card writes it
 * in data-fill (markup/card.json, rating; section#no-inline-style): the
 * rating as a percentage of 5 rounded to the nearest step of 10, halves
 * up, held to 0-100, so 4.5 is 90 and 4.24 is 80.
 *
 * @param float $rating Rating, 0..5.
 * @return int 0..100 in steps of 10.
 */
function salt_rating_fill( float $rating ): int {
	return (int) min( 100, max( 0, 10 * floor( $rating * 2 + 0.5 ) ) );
}

/**
 * A same-site URL as the contract's markup draws it: root-relative.
 *
 * The contract's fixtures write links to the site itself without scheme or
 * host ("/journal/"), so a URL on the home URL's host (any scheme, or
 * protocol-relative) keeps only its path, query and fragment. Everything
 * else (another host, mailto:, tel:, an anchor or an already relative URL)
 * is returned unchanged. Use it for links in contract markup only; feeds,
 * canonical URLs, schema and e-mail keep absolute URLs.
 *
 * @param string $url URL to draw.
 * @return string
 */
function salt_contract_url( string $url ): string {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! isset( $parts['host'] ) ) {
		return $url;
	}
	if ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return $url;
	}

	$home = wp_parse_url( home_url( '/' ) );
	if ( ! is_array( $home ) || ! isset( $home['host'] ) ) {
		return $url;
	}
	if ( strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) ) {
		return $url;
	}

	$relative = '/' . ltrim( $parts['path'] ?? '', '/' );
	if ( isset( $parts['query'] ) ) {
		$relative .= '?' . $parts['query'];
	}
	if ( isset( $parts['fragment'] ) ) {
		$relative .= '#' . $parts['fragment'];
	}
	return $relative;
}

/**
 * Buttons from salt_contract_buttons() as the contract's button view props
 * (view-props/_shared.json button: label, href, style, newTab), the shape
 * the hero, call-to-action and media-text views and
 * components/contract-button.php take. A same-site address is drawn
 * root-relative (salt_contract_url()), as the contract's fixtures write it.
 *
 * @param array<int, array{label: string, url: string, style: string, target: string}> $buttons salt_contract_buttons().
 * @return array<int, array{label: string, href: string, style: string, newTab: bool}>
 */
function salt_contract_button_props( array $buttons ): array {
	return array_map(
		static fn( array $button ): array => array(
			'label'  => $button['label'],
			'href'   => salt_contract_url( $button['url'] ),
			'style'  => $button['style'],
			'newTab' => '_blank' === $button['target'],
		),
		$buttons
	);
}
