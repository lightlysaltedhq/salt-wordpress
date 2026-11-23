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
 * Join a class list, dropping empties.
 *
 * @param array<int,string> $class_names Class name fragments.
 * @return string
 */
function salt_classnames( array $class_names ): string {
	return implode( ' ', array_filter( array_map( 'trim', $class_names ), static fn( string $c ): bool => '' !== $c ) );
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
 * in --salt-rating-fill (markup/card.json, rating): the rating as a
 * percentage of 5, so 4.5 is "90%" and 4 is "80%".
 *
 * @param float $rating Rating, 0..5.
 * @return string CSS percentage.
 */
function salt_rating_fill( float $rating ): string {
	return (string) round( $rating / 5 * 100, 2 ) . '%';
}

/**
 * Buttons from salt_contract_buttons() as the contract's button view props
 * (view-props/_shared.json button: label, href, style, newTab), the shape
 * the hero, call-to-action and media-text views and
 * components/contract-button.php take.
 *
 * @param array<int, array{label: string, url: string, style: string, target: string}> $buttons salt_contract_buttons().
 * @return array<int, array{label: string, href: string, style: string, newTab: bool}>
 */
function salt_contract_button_props( array $buttons ): array {
	return array_map(
		static fn( array $button ): array => array(
			'label'  => $button['label'],
			'href'   => $button['url'],
			'style'  => $button['style'],
			'newTab' => '_blank' === $button['target'],
		),
		$buttons
	);
}
