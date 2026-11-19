<?php
/**
 * Native team user fixtures, plus the shared CPT/taxonomy and image shims.
 *
 * Fixtures:
 * - Registered CPTs land in $GLOBALS['salt_test_post_types'][ $slug ].
 * - Registered taxonomies land in $GLOBALS['salt_test_taxonomies'][ $slug ]
 *   as array{ object_type: string[], args: array } (the shared fleet shape —
 *   interchangeable with tests/shims/faq.php et al).
 * - get_post_thumbnail_id() reads the WP_Post fixture's salt_thumbnail_id
 *   dynamic property (0 when unset).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'salt_test_cpt_registry_reset' ) ) {
	/**
	 * Reset the CPT/taxonomy capture registries between tests.
	 *
	 * @return void
	 */
	function salt_test_cpt_registry_reset(): void {
		$GLOBALS['salt_test_post_types'] = array();
		$GLOBALS['salt_test_taxonomies'] = array();
	}
}
salt_test_cpt_registry_reset();

/** Native users in collection fixtures, never WP_Post fixtures. */
class WP_User {
	public function __construct( public int $ID, public string $display_name ) {}
}

function get_user_meta( int $id, string $name, bool $single ) {
	$value = $GLOBALS['salt_test_fields'][ 'user_' . $id ][ $name ] ?? null;
	return $single ? ( $value ?? '' ) : ( null === $value ? array() : array( $value ) );
}

function get_users( array $args ): array {
	$users = array_values( array_filter( $GLOBALS['salt_test_users'] ?? array(), static fn( $user ) => ( ! isset( $args['meta_key'] ) || (string) get_user_meta( $user->ID, $args['meta_key'], true ) === $args['meta_value'] ) && ( ! isset( $args['include'] ) || in_array( $user->ID, $args['include'], true ) ) ) );
	usort(
		$users,
		static fn( $a, $b ) => match ( $args['orderby'] ) {
		'include' => array_search( $a->ID, $args['include'], true ) <=> array_search( $b->ID, $args['include'], true ),
		'display_name' => strcasecmp( $a->display_name, $b->display_name ),
		default => $a->ID <=> $b->ID,
		}
	);
	if ( 'DESC' === $args['order'] ) {
		$users = array_reverse( $users );
	}
	return $args['number'] > 0 ? array_slice( $users, 0, $args['number'] ) : $users;
}

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * register_post_type capture shim.
	 *
	 * @param string              $post_type Post type slug.
	 * @param array<string,mixed> $args      Registration args.
	 * @return void
	 */
	function register_post_type( string $post_type, array $args = array() ): void {
		$GLOBALS['salt_test_post_types'][ $post_type ] = $args;
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	/**
	 * register_taxonomy capture shim.
	 *
	 * @param string              $taxonomy    Taxonomy slug.
	 * @param string|string[]     $object_type Object type(s).
	 * @param array<string,mixed> $args        Registration args.
	 * @return void
	 */
	function register_taxonomy( string $taxonomy, $object_type, array $args = array() ): void {
		$GLOBALS['salt_test_taxonomies'][ $taxonomy ] = array(
			'object_type' => (array) $object_type,
			'args'        => $args,
		);
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	/**
	 * get_post_thumbnail_id shim — reads the fixture's salt_thumbnail_id.
	 *
	 * @param WP_Post|int|null $post Post fixture or ID.
	 * @return int Attachment ID, 0 when none.
	 */
	function get_post_thumbnail_id( $post = null ): int {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post instanceof WP_Post ) {
			return 0;
		}
		return (int) ( $post->salt_thumbnail_id ?? 0 );
	}
}
