<?php
/**
 * Demo seeder — cross-reference resolver.
 *
 * The content map (bin/demo/data.php) expresses cross-links declaratively
 * with "ref:" tokens (e.g. 'ref:service/web-design', 'ref:page/about',
 * 'ref:image/hero-home') instead of live post IDs, because those IDs don't
 * exist until the seeder has created the objects. After creation the runner
 * records ref => ID in a map and calls salt_demo_resolve_refs() to replace
 * every token, recursively, before writing relationship / section / menu
 * values. An unresolved token is dropped (and logged) rather than written
 * as a bad ID.
 *
 * Dev/ops tooling — never loaded by the theme at runtime.
 *
 * @package Salt\Demo
 */

declare(strict_types=1);

/**
 * The per-run ref => post/term/attachment ID registry.
 *
 * @param string   $op    'get'|'set'|'reset'.
 * @param string   $ref   Ref token (on set/get).
 * @param int|null $id    ID (on set).
 * @return array<string,int>|int|null
 */
function salt_demo_refs( string $op = 'get', string $ref = '', ?int $id = null ) {
	static $map = array();

	switch ( $op ) {
		case 'reset':
			$map = array();
			return $map;
		case 'set':
			$map[ $ref ] = (int) $id;
			return $map;
		case 'lookup':
			return $map[ $ref ] ?? null;
		default:
			return $map;
	}
}

/**
 * Register a ref => ID mapping.
 *
 * @param string $ref Ref token, e.g. 'service/web-design'.
 * @param int    $id  Created object ID.
 * @return void
 */
function salt_demo_ref_set( string $ref, int $id ): void {
	salt_demo_refs( 'set', $ref, $id );
}

/**
 * Whether a scalar is an ID "ref:" token.
 *
 * @param mixed $value Value.
 * @return bool
 */
function salt_demo_is_ref( $value ): bool {
	return is_string( $value ) && str_starts_with( $value, 'ref:' );
}

/**
 * Whether a scalar is a URL "url:" token.
 *
 * @param mixed $value Value.
 * @return bool
 */
function salt_demo_is_url_ref( $value ): bool {
	return is_string( $value ) && str_starts_with( $value, 'url:' );
}

/**
 * Resolve a "url:" token to a live URL.
 *
 * url:page/{slug}      => get_permalink( ref:page/{slug} )
 * url:{cpt}/{slug}     => get_permalink( ref:{cpt}/{slug} )
 * url:archive/{cpt}    => get_post_type_archive_link( {cpt} )
 *
 * @param string $token 'url:...' token.
 * @return string Resolved URL, or '' when unresolvable.
 */
function salt_demo_resolve_url( string $token ): string {
	$path = substr( $token, 4 );

	if ( str_starts_with( $path, 'archive/' ) ) {
		$cpt = substr( $path, strlen( 'archive/' ) );
		$url = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( $cpt ) : '';
		return is_string( $url ) ? $url : '';
	}

	$id = salt_demo_refs( 'lookup', $path );
	if ( null === $id || ! function_exists( 'get_permalink' ) ) {
		salt_demo_log( sprintf( 'unresolved url ref "%s" — empty URL.', $path ) );
		return '';
	}
	$url = get_permalink( $id );
	return is_string( $url ) ? $url : '';
}

/**
 * Recursively replace every ref/url token in a value.
 *
 * ID tokens ("ref:") resolve to an int ID (or null when unknown); within a
 * list an unresolved ID token is removed so a relationship/manual/gallery
 * array never contains a stray 0. URL tokens ("url:") resolve to a string
 * URL. Non-token values pass through unchanged.
 *
 * @param mixed $value Value possibly containing tokens.
 * @return mixed
 */
function salt_demo_resolve_refs( $value ) {
	if ( salt_demo_is_ref( $value ) ) {
		$ref = substr( $value, 4 );
		$id  = salt_demo_refs( 'lookup', $ref );
		if ( null === $id ) {
			salt_demo_log( sprintf( 'unresolved ref "%s" — dropped.', $ref ) );
		}
		return $id;
	}

	if ( salt_demo_is_url_ref( $value ) ) {
		return salt_demo_resolve_url( $value );
	}

	if ( ! is_array( $value ) ) {
		return $value;
	}

	$is_list = array_is_list( $value );
	$out     = array();

	foreach ( $value as $key => $item ) {
		$resolved = salt_demo_resolve_refs( $item );

		// In a list of ID refs (relationship/manual/gallery), drop unresolved
		// entries so we never write a null/0 ID.
		if ( $is_list && salt_demo_is_ref( $item ) && null === $resolved ) {
			continue;
		}

		$out[ $key ] = $resolved;
	}

	return $out;
}

/**
 * Seeder logger — echoes to stdout under wp eval-file, and to error_log too.
 *
 * @param string $message Message.
 * @return void
 */
function salt_demo_log( string $message ): void {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( 'salt-demo: ' . $message );
	} else {
		echo 'salt-demo: ' . $message . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI tooling.
	}
}
