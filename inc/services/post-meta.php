<?php
/**
 * Post meta services: reading time, share links, primary category
 * (blog single build-out — spec §6 extension).
 *
 * Pure string/array builders live here so they unit-test without WP;
 * the WP-context wrappers stay thin.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estimated reading time in whole minutes (PURE).
 *
 * Words are counted on tag-stripped content at 200 wpm (the common
 * editorial baseline); anything non-empty reads as at least 1 minute.
 *
 * @param string $content  Raw post content (may contain HTML).
 * @param int    $wpm      Words per minute (defaults 200; <= 0 resets to 200).
 * @return int Minutes (0 for empty content).
 */
function salt_reading_time_minutes( string $content, int $wpm = 200 ): int {
	if ( $wpm <= 0 ) {
		salt_log( sprintf( 'salt_reading_time_minutes(): non-positive wpm %d — using 200.', $wpm ) );
		$wpm = 200;
	}

	$text = trim( wp_strip_all_tags( $content ) );
	if ( '' === $text ) {
		return 0;
	}

	$split = preg_split( '/\s+/', $text );
	$words = count( is_array( $split ) ? $split : array() );

	return max( 1, (int) ceil( $words / $wpm ) );
}

/**
 * The share-service registry (PURE) — key => label + URL template + icon.
 *
 * Templates use {url} and {title} placeholders (already-encoded values are
 * substituted by salt_share_links()). Icons are bundled asset names
 * (assets/src/icons/{icon}.svg).
 *
 * @return array<string,array{label:string,template:string,icon:string}>
 */
function salt_share_services(): array {
	return array(
		'x'         => array(
			'label'    => __( 'Share on X', 'salt' ),
			'template' => 'https://x.com/intent/post?url={url}&text={title}',
			'icon'     => 'x',
		),
		'facebook'  => array(
			'label'    => __( 'Share on Facebook', 'salt' ),
			'template' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
			'icon'     => 'facebook',
		),
		'linkedin'  => array(
			'label'    => __( 'Share on LinkedIn', 'salt' ),
			'template' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
			'icon'     => 'linkedin',
		),
		'whatsapp'  => array(
			'label'    => __( 'Share on WhatsApp', 'salt' ),
			'template' => 'https://wa.me/?text={title}%20{url}',
			'icon'     => 'whatsapp',
		),
		'bluesky'   => array(
			'label'    => __( 'Share on Bluesky', 'salt' ),
			'template' => 'https://bsky.app/intent/compose?text={title}%20{url}',
			'icon'     => 'bluesky',
		),
		'reddit'    => array(
			'label'    => __( 'Share on Reddit', 'salt' ),
			'template' => 'https://www.reddit.com/submit?url={url}&title={title}',
			'icon'     => 'reddit',
		),
		'pinterest' => array(
			'label'    => __( 'Share on Pinterest', 'salt' ),
			'template' => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
			'icon'     => 'pinterest',
		),
		'email'     => array(
			'label'    => __( 'Share by email', 'salt' ),
			'template' => 'mailto:?subject={title}&body={url}',
			'icon'     => 'mail',
		),
	);
}

/**
 * The default share suite rendered when a caller passes no explicit list.
 *
 * @return string[] Service keys, in render order.
 */
function salt_share_default_services(): array {
	return array( 'x', 'facebook', 'linkedin', 'whatsapp', 'bluesky', 'reddit', 'email' );
}

/**
 * Resolve share links for a URL + title (PURE).
 *
 * Unknown service keys are skipped + salt_log()ged — never a dead button.
 *
 * @param string   $url      Absolute URL to share.
 * @param string   $title    Title/text accompanying the share.
 * @param string[] $services Service keys; empty = salt_share_default_services().
 * @return array<int,array{key:string,label:string,url:string,icon:string}>
 */
function salt_share_links( string $url, string $title, array $services = array() ): array {
	if ( '' === trim( $url ) ) {
		salt_log( 'salt_share_links(): empty url — no links built.' );
		return array();
	}

	$registry = salt_share_services();
	$keys     = array() === $services ? salt_share_default_services() : $services;
	$links    = array();

	foreach ( $keys as $key ) {
		if ( ! is_string( $key ) || ! isset( $registry[ $key ] ) ) {
			salt_log( sprintf( 'salt_share_links(): unknown share service "%s" — skipped.', is_string( $key ) ? $key : gettype( $key ) ) );
			continue;
		}

		$service = $registry[ $key ];
		$links[] = array(
			'key'   => $key,
			'label' => $service['label'],
			'url'   => strtr(
				$service['template'],
				array(
					'{url}'   => rawurlencode( $url ),
					'{title}' => rawurlencode( $title ),
				)
			),
			'icon'  => $service['icon'],
		);
	}

	return $links;
}

/**
 * The post's primary category term.
 *
 * First assigned category wins, except the default "Uncategorized" term,
 * which only wins when it is the ONLY category.
 *
 * @param int $post_id Post ID (0 = current post).
 * @return WP_Term|null
 */
function salt_post_primary_category( int $post_id = 0 ): ?WP_Term {
	$terms = get_the_category( $post_id );
	if ( ! is_array( $terms ) || array() === $terms ) {
		return null;
	}

	$default_id = (int) get_option( 'default_category' );
	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term && $term->term_id !== $default_id ) {
			return $term;
		}
	}

	return $terms[0] instanceof WP_Term ? $terms[0] : null;
}
