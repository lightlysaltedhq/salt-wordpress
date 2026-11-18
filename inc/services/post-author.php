<?php
/**
 * The credited author: a team member, not a WordPress user (salt-contract
 * rc.7 contract/collections/posts.json `author`, a relationship to team;
 * the founder's rulings of 18/11/2026). The WordPress user who owns a post
 * keeps WordPress's editing rights and is never drawn.
 *
 * The author archive is built by team member. It keeps WordPress's own
 * author address (/<front>/author/<slug>/, or ?author_name=<slug>), so
 * routing stays native, but the slug is a team member's, and the archive
 * lists the published posts whose `author` is that team member. An author
 * address that names no published team member, and a user's (?author=<id>),
 * is not found.
 *
 * Provisional, until the contract says (the open questions of ID-3952):
 * a team member has an archive when it is published; the post's author box
 * and the archive's profile are drawn when the member has a role, a bio or a
 * photo; the archive's strapline is the member's role and its biography the
 * member's bio.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The query variable that marks a team member's archive (set by
 * salt_team_author_request(), never read from the address).
 */
const SALT_TEAM_AUTHOR_VAR = 'salt_team_member';

/**
 * A post's credited author, or null when it credits none.
 *
 * The `author` field's team member, unless it is missing, binned or not a
 * team member. A draft or private member is still credited, with no
 * archive.
 *
 * @param int $post_id The post.
 * @return array{id:int,name:string,role:string,bio:string,photo:int,url:string}|null
 */
function salt_post_credited_author( int $post_id ): ?array {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}
	$member = get_post( (int) get_field( 'field_salt_collection_posts_author', $post_id, false ) );
	if ( ! $member instanceof WP_Post || 'team_member' !== $member->post_type || in_array( $member->post_status, array( 'trash', 'auto-draft' ), true ) ) {
		return null;
	}
	$name = trim( (string) $member->post_title );
	if ( '' === $name ) {
		return null;
	}
	return array(
		'id'    => (int) $member->ID,
		'name'  => $name,
		'role'  => trim( (string) get_field( 'field_salt_collection_team_role', $member->ID ) ),
		'bio'   => trim( (string) get_field( 'field_salt_collection_team_bio', $member->ID ) ),
		'photo' => (int) get_post_thumbnail_id( $member ),
		'url'   => salt_team_member_archive_url( $member ),
	);
}

/**
 * Whether a person has a profile to draw: a role, a bio or a photo (the
 * post's author box and the author archive's profile).
 *
 * @param array{role:string,bio:string,photo:int} $person The person.
 * @return bool
 */
function salt_person_has_profile( array $person ): bool {
	return '' !== $person['role'] || '' !== $person['bio'] || $person['photo'] > 0;
}

/**
 * A team member's archive address, or '' when it has none (it is not
 * published).
 *
 * @param WP_Post $member The team member.
 * @return string
 */
function salt_team_member_archive_url( WP_Post $member ): string {
	global $wp_rewrite;
	if ( 'team_member' !== $member->post_type || 'publish' !== $member->post_status || '' === $member->post_name ) {
		return '';
	}
	$link = $wp_rewrite instanceof WP_Rewrite ? (string) $wp_rewrite->get_author_permastruct() : '';
	if ( '' === $link ) {
		return add_query_arg( 'author_name', $member->post_name, home_url( '/' ) );
	}
	return home_url( user_trailingslashit( str_replace( '%author%', $member->post_name, $link ) ) );
}

/**
 * The published team member with a slug, or null.
 *
 * @param string $slug The slug.
 * @return WP_Post|null
 */
function salt_team_member_by_slug( string $slug ): ?WP_Post {
	if ( '' === $slug || ! post_type_exists( 'team_member' ) ) {
		return null;
	}
	$found = get_posts(
		array(
			'post_type'   => 'team_member',
			'post_status' => 'publish',
			'name'        => $slug,
			'numberposts' => 1,
		)
	);
	return isset( $found[0] ) && $found[0] instanceof WP_Post ? $found[0] : null;
}

/**
 * Turn an author request into a team member's archive ('request'): the
 * slug names a published team member, or the request is not found.
 *
 * @param array<string,mixed> $query_vars The request's query variables.
 * @return array<string,mixed>
 */
function salt_team_author_request( array $query_vars ): array {
	unset( $query_vars[ SALT_TEAM_AUTHOR_VAR ] );
	if ( is_admin() || ( empty( $query_vars['author_name'] ) && empty( $query_vars['author'] ) ) ) {
		return $query_vars;
	}
	$member = empty( $query_vars['author_name'] ) ? null : salt_team_member_by_slug( sanitize_title_for_query( (string) $query_vars['author_name'] ) );
	unset( $query_vars['author_name'], $query_vars['author'] );
	if ( null === $member ) {
		$query_vars['error'] = '404';
		return $query_vars;
	}
	$query_vars[ SALT_TEAM_AUTHOR_VAR ] = (int) $member->ID;
	return $query_vars;
}
add_filter( 'request', 'salt_team_author_request' );

/**
 * A team member's archive is an archive of the posts crediting them
 * ('parse_query').
 *
 * @param WP_Query $query The query.
 * @return void
 */
function salt_team_author_parse_query( $query ): void {
	$member = (int) $query->get( SALT_TEAM_AUTHOR_VAR );
	if ( $member <= 0 ) {
		return;
	}
	$query->is_archive = true;
	$query->is_home    = false;
	$query->set( 'post_type', 'post' );
	$query->set(
		'meta_query',
		array(
			array(
				'key'   => 'author',
				'value' => (string) $member,
			),
		)
	);
}
add_action( 'parse_query', 'salt_team_author_parse_query' );

/**
 * A team member's archive with no posts is still found, on its first page
 * ('pre_handle_404'), as WordPress keeps a user's.
 *
 * @param bool     $preempt Whether to skip WordPress's 404 handling.
 * @param WP_Query $query   The main query.
 * @return bool
 */
function salt_team_author_keep_empty_archive( $preempt, $query ): bool {
	if ( $query instanceof WP_Query && (int) $query->get( SALT_TEAM_AUTHOR_VAR ) > 0 && ! $query->is_404() && ! $query->is_paged() ) {
		return true;
	}
	return (bool) $preempt;
}
add_filter( 'pre_handle_404', 'salt_team_author_keep_empty_archive', 10, 2 );

/**
 * The team member whose archive this is, or null.
 *
 * @return WP_Post|null
 */
function salt_archive_team_member(): ?WP_Post {
	global $wp_query;
	if ( ! $wp_query instanceof WP_Query ) {
		return null;
	}
	$member = get_post( (int) $wp_query->get( SALT_TEAM_AUTHOR_VAR ) );
	return $member instanceof WP_Post && 'team_member' === $member->post_type ? $member : null;
}

/**
 * The team member's name as the archive's document title
 * ('document_title_parts').
 *
 * @param array<string,string> $parts The title's parts.
 * @return array<string,string>
 */
function salt_team_author_document_title( array $parts ): array {
	$member = salt_archive_team_member();
	if ( null !== $member ) {
		$parts['title'] = (string) $member->post_title;
	}
	return $parts;
}
add_filter( 'document_title_parts', 'salt_team_author_document_title' );
