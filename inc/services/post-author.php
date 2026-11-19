<?php
/**
 * Public team profiles on native users; authorship never depends on publication.
 *
 * @package Salt
 */

defined( 'ABSPATH' ) || exit;

/** Public URL, or the empty string for a private person. */
function salt_team_profile_url( int $id ): string {
	if ( ! salt_module_enabled( 'team_member' ) || ! get_user_meta( $id, 'showOnWebsite', true ) ) {
		return '';
	}
	$slug = (string) get_user_meta( $id, 'slug', true );
	return '' === $slug ? '' : home_url( '/team/' . $slug . '/' );
}

/** Atomically reserve a slug independently of credentials or subsequent name changes. */
function salt_team_reserve_slug( string $value, int $id ): string {
	global $wpdb;
	$base = sanitize_title( $value );
	if ( '' === $base ) {
		$saved = (string) get_user_meta( $id, 'slug', true );
		$base  = '' !== $saved ? $saved : 'member-' . wp_generate_uuid4();
	}
	for ( $suffix = 1; ; ++$suffix ) {
		$slug = 1 === $suffix ? $base : $base . '-' . $suffix;
		$key  = 'salt_profile_slug_' . hash( 'sha256', $slug );
		if ( (int) get_option( $key ) === $id ) {
			return $slug;
		}
		// add_option() upserts on collision. An insert-only unique key cannot be stolen by a concurrent save.
		$won = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $wpdb->options (option_name,option_value,autoload) VALUES (%s,%d,'off')", $key, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic reservation.
		if ( false === $won ) {
			return (string) get_user_meta( $id, 'slug', true );
		}
		if ( $won ) {
			wp_cache_delete( 'notoptions', 'options' );
			return $slug;
		}
	}
}

add_filter(
	'acf/update_value/key=field_salt_collection_team_slug',
	static function ( $value, $object_id ) {
		return preg_match( '/^user_(\d+)$/D', (string) $object_id, $match ) ? salt_team_reserve_slug( (string) $value, (int) $match[1] ) : $value;
	},
	10,
	2
);

add_filter(
	'acf/update_value/key=field_salt_collection_team_show_on_website',
	static function ( $value, $object_id ) {
		if ( $value && preg_match( '/^user_(\d+)$/D', (string) $object_id, $match ) && ! get_user_meta( (int) $match[1], 'slug', true ) ) {
			update_field( 'field_salt_collection_team_slug', '', $object_id );
		}
		return $value;
	},
	10,
	2
);

/** Only the public profile fields are read, and only when published. */
function salt_team_person( WP_User $user ): array {
	$url = salt_team_profile_url( $user->ID );
	$get = static fn( $field ) => '' !== $url && function_exists( 'get_field' ) ? get_field( $field, 'user_' . $user->ID ) : null;
	return array(
		'id'       => $user->ID,
		'name'     => $user->display_name,
		'url'      => $url,
		'photo'    => (int) $get( 'photo' ),
		'role'     => (string) $get( 'role' ),
		'bio'      => (string) $get( 'bio' ),
		'email'    => (string) $get( 'publicEmail' ),
		'phone'    => (string) $get( 'phone' ),
		'profiles' => salt_collection_profiles( $get( 'socials' ), $user->ID ),
	);
}

/** A post's native author, independent of the team tick. */
function salt_post_credited_author( int $post_id ): ?array {
	$user = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
	return $user instanceof WP_User ? salt_team_person( $user ) : null;
}

/** The public user whose profile is being rendered. */
function salt_archive_team_member(): ?WP_User {
	$id = (int) get_query_var( 'salt_profile_id' );
	return $id && salt_team_profile_url( $id ) ? get_userdata( $id ) : null;
}

/** Resolve a path, not a caller-supplied public query var; no permalink flush needed on update. */
function salt_team_author_request( array $vars ): array {
	if ( is_admin() || isset( $vars['rest_route'] ) ) {
		return $vars;
	}
	$base    = wp_parse_url( home_url( '/team/' ), PHP_URL_PATH );
	$path    = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH );
	$profile = preg_match( '~^' . preg_quote( $base, '~' ) . '([^/]+)(?:/page/([1-9][0-9]*))?/?$~D', (string) $path, $match );
	if ( ! $profile && ! isset( $vars['author_name'] ) && ! isset( $vars['author'] ) ) {
		return $vars;
	}
	if ( $profile ) {
		$slug = sanitize_title( $match[1] );
		$id   = (int) get_option( 'salt_profile_slug_' . hash( 'sha256', $slug ) );
		if ( get_user_meta( $id, 'slug', true ) !== $slug ) {
			$id = 0;
		}
	} elseif ( is_string( $vars['author_name'] ?? null ) ) {
		$user = get_user_by( 'slug', (string) $vars['author_name'] );
		$id   = $user ? $user->ID : (int) get_option( 'salt_profile_slug_' . hash( 'sha256', sanitize_title( $vars['author_name'] ) ) );
	} else {
		$id = is_scalar( $vars['author'] ?? null ) ? (int) $vars['author'] : 0;
	}
	if ( ! $id || ! is_user_member_of_blog( $id ) || ! salt_team_profile_url( $id ) ) {
		return array(
			'error'                => '404',
			'salt_private_profile' => true,
		);
	}
	return array(
		'salt_profile_id' => $id,
		'author'          => $id,
		'post_type'       => 'post',
		'post_status'     => 'publish',
		'paged'           => (int) ( $match[2] ?? 0 ),
	);
}
add_filter( 'request', 'salt_team_author_request' );

/** Keep WordPress's native author loop but never its credential-derived URL. */
add_action(
	'template_redirect',
	static function () {
		if ( get_query_var( 'salt_private_profile' ) ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
			return;
		}
		$user = salt_archive_team_member();
		if ( ! $user ) {
			return;
		}
		$url = salt_team_profile_url( $user->ID );
		if ( is_paged() ) {
			$url .= 'page/' . (int) get_query_var( 'paged' ) . '/';
		}
		remove_action( 'template_redirect', 'redirect_canonical' );
		if ( wp_parse_url( $url, PHP_URL_PATH ) !== wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ) ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
		add_action(
			'wp_head',
			static function () use ( $url ) {
				echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
			}
		);
	},
	1
);

add_filter( 'author_link', static fn( $link, $id ) => salt_team_profile_url( (int) $id ), 10, 2 );
add_filter(
	'body_class',
	static function ( $classes ) {
		return salt_archive_team_member() ? array_filter( $classes, static fn( $name ) => ! str_starts_with( $name, 'author-' ) ) : $classes;
	}
);
add_filter(
	'wp_sitemaps_users_query_args',
	static function ( $args ) {
		unset( $args['has_published_posts'] );
		$args['meta_key']   = 'showOnWebsite';
		$args['meta_value'] = '1';
		if ( ! salt_module_enabled( 'team_member' ) ) {
			$args['include'] = array( 0 );
		}
		return $args;
	}
);
