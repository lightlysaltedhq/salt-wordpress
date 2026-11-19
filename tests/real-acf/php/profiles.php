<?php
/**
 * Native author routes, source filtering and the public-copy-only editor on a disposable site.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

/** Request over the local server, deliberately without following redirects. */
function salt_profile_http( string $url, array $post = array(), string $cookies = '' ): array {
	$http = array(
		'ignore_errors'   => true,
		'follow_location' => 0,
		'timeout'         => 30,
		'header'          => 'Cookie: ' . $cookies . "\r\n",
	);
	if ( $post ) {
		$http['method']  = 'POST';
		$http['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$http['content'] = http_build_query( $post );
	}
	$stream  = fopen( $url, 'rb', false, stream_context_create( array( 'http' => $http ) ) );
	$body    = stream_get_contents( $stream );
	$headers = stream_get_meta_data( $stream )['wrapper_data'];
	fclose( $stream );
	preg_match( '~^HTTP/\S+ (\d+)~', $headers[0], $match );
	return array( (int) $match[1], $body, $headers );
}

/** Establish a real WordPress session, including the session token used by nonces. */
function salt_profile_session( int $id ): string {
	$expiry = time() + HOUR_IN_SECONDS;
	$token  = WP_Session_Tokens::get_instance( $id )->create( $expiry );
	wp_set_current_user( $id );
	$logged                      = wp_generate_auth_cookie( $id, $expiry, 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged;
	return LOGGED_IN_COOKIE . '=' . $logged . '; ' . AUTH_COOKIE . '=' . wp_generate_auth_cookie( $id, $expiry, 'auth', $token );
}

$wp_rewrite->set_permalink_structure( '/journal/%postname%/' );
update_option( 'blog_public', 1 );
// The post types registered before the permalink change; let the next request build their rules.
delete_option( 'rewrite_rules' );
$case = salt_harness_fixture( 'post/full' );
$post = get_page_by_path( salt_harness_slug( $case['document']['href'] ), OBJECT, 'post' );
$id   = (int) $post->post_author;
$user = get_userdata( $id );
$url  = salt_team_profile_url( $id );
$old  = home_url( '/journal/author/' . $user->user_nicename . '/' );
update_field( 'field_salt_collection_team_public_email', 'hello@public.example.test', 'user_' . $id );
update_field( 'field_salt_collection_team_phone', '0117 496 0130', 'user_' . $id );
update_field(
	'field_salt_collection_team_socials',
	array(
		array(
			'name' => 'Instagram',
			'url'  => 'https://www.instagram.com/priya.grows',
		),
	),
	'user_' . $id
);
list( $status, $body ) = salt_profile_http( $url );
salt_harness_result( str_contains( $body, 'href="tel:01174960130"' ) && str_contains( $body, 'href="https://www.instagram.com/priya.grows"' ), 'public profile draws optional phone and social links' );
salt_harness_result( 200 === $status && str_contains( $body, 'rel="canonical" href="' . $url . '"' ) && str_contains( $body, 'mailto:hello@public.example.test' ) && str_contains( $body, esc_html( $post->post_title ) ) && ! str_contains( $body, $user->user_login ) && ! str_contains( $body, $user->user_email ), 'direct canonical profile with public contact and native posts; no login or account email', (string) $status );
foreach ( array( $old, home_url( '/?author=' . $id ), home_url( '/journal/author/priya-shah/' ) ) as $legacy ) {
	list( $status, , $headers ) = salt_profile_http( $legacy );
	salt_harness_result( 301 === $status && in_array( 'Location: ' . $url, $headers, true ), 'legacy author URL returns direct 301 to canonical', (string) $status );
}
$other  = salt_harness_team_member(
	array(
		'name' => 'Profile collision',
		'href' => '/team/priya-shah/',
	),
	array()
);
$second = salt_team_profile_url( $other );
salt_harness_result( home_url( '/team/priya-shah-2/' ) === $second, 'duplicate public slugs get distinct addresses' );
$private               = salt_harness_team_member( array( 'name' => 'Private writer' ), array() );
$private_post          = wp_insert_post(
	array(
		'post_title'  => 'Private writer credit',
		'post_status' => 'publish',
		'post_author' => $private,
	)
);
list( $status, $body ) = salt_profile_http( get_permalink( $private_post ) );
salt_harness_result( 200 === $status && str_contains( $body, '>Private writer</span>' ) && ! str_contains( $body, 'x-author-box' ), 'unticked native author is credited as plain text only' );
foreach ( array( '/team/' . get_user_meta( $private, 'slug', true ) . '/', '/journal/author/' . get_userdata( $private )->user_nicename . '/', '/?author=' . $private, '/team/not-a-profile/', '/team/' ) as $path ) {
	list( $status ) = salt_profile_http( home_url( $path ) );
	salt_harness_result( 404 === $status, 'private, unknown and unconfigured collection addresses are direct 404s', (string) $status );
}
list( $status, $body ) = salt_profile_http( home_url( '/wp-sitemap-users-1.xml' ) );
salt_harness_result( 200 === $status && str_contains( $body, $url ) && str_contains( $body, $second ) && ! str_contains( $body, '/author/' ) && ! str_contains( $body, $user->user_login ) && ! str_contains( $body, get_userdata( $private )->user_nicename ), 'core user sitemap contains canonical public profiles including members with no posts', (string) $status );

$page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'People',
		'post_status' => 'publish',
	)
);
update_field(
	'sections',
	array(
		array(
			'acf_fc_layout' => 'collection-showcase',
			'source'        => 'team',
			'layout'        => 'index',
			'heading'       => 'Team directory',
			'viewAll'       => true,
			'query'         => array(
				'mode'  => 'manual',
				'items' => array( $other, $private, $id ),
			),
		),
	),
	$page
);
list( $status, $body ) = salt_profile_http( get_permalink( $page ) );
salt_harness_result( 200 === $status && str_contains( $body, $second ) && str_contains( $body, $url ) && strpos( $body, $second ) < strpos( $body, $url ) && ! str_contains( $body, 'Private writer' ) && ! str_contains( $body, 'View all' ), 'team Index preserves ordered public IDs, skips private users, has no view-all' );

$cards = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_title'  => 'Meet the team',
		'post_status' => 'publish',
	)
);
update_field(
	'sections',
	array(
		array(
			'acf_fc_layout' => 'collection-showcase',
			'heading'       => 'Our gardeners',
			'source'        => 'team',
			'layout'        => 'grid',
		),
		array(
			'acf_fc_layout' => 'carousel',
			'heading'       => 'Meet our people',
			'source'        => 'team',
		),
	),
	$cards
);
list( $status, $body ) = salt_profile_http( get_permalink( $cards ) );
salt_harness_result( 200 === $status && 2 === substr_count( $body, 'href="' . $url . '"' ) && 2 === substr_count( $body, 'alt="' . $case['media']['priya']['alt'] . '"' ) && ! str_contains( $body, 'Private writer' ), 'automatic team grid and carousel both link to the profile and use its photo' );

$editor                = wp_insert_user(
	array(
		'user_login' => 'harness-profile-editor',
		'user_pass'  => getenv( 'SALT_HARNESS_EDITOR_PASS' ),
		'role'       => 'editor',
	)
);
$cookies               = salt_profile_session( $editor );
$admin                 = admin_url( 'tools.php?page=salt-team&member=' . $id );
list( $status, $body ) = salt_profile_http( admin_url( 'post.php?action=edit&post=' . $post->ID ), array(), $cookies );
preg_match( '~<select[^>]+name=[\'"]post_author_override[\'"].*?</select>~s', $body, $author_control );
salt_harness_result( 200 === $status && str_contains( $author_control[0] ?? '', $user->display_name ) && str_contains( $author_control[0] ?? '', 'Private writer' ), 'native editorial Author control includes both ticked and unticked subscribers' );
list( $status, $body ) = salt_profile_http( admin_url( 'post.php?action=edit&post=' . $page ), array(), $cookies );
preg_match_all( '~<select\b[^>]*name="[^"]*field_salt_collection_showcase_query_items[^"]*"[^>]*>(.*?)</select>~s', $body, $items_controls );
$labels = trim( wp_strip_all_tags( implode( ' ', $items_controls[1] ) ) );
salt_harness_result( 200 === $status && str_contains( $labels, 'Profile collision' ) && str_contains( $labels, $user->display_name ) && ! str_contains( $labels, 'Private writer' ), 'saved manual user selections reopen with their public labels', sprintf( '%d selects, %s', count( $items_controls[1] ), $labels ) );
list( $status, $body ) = salt_profile_http( $admin, array(), $cookies );
salt_harness_result( 200 === $status && str_contains( $body, 'field_salt_collection_team_slug' ) && ! str_contains( $body, 'name="user_email"' ) && ! str_contains( $body, $user->user_email ), 'editor can open public fields without any account form', (string) $status );
$save           = array(
	'action'       => 'salt_profile',
	'member'       => $id,
	'display_name' => 'Priya Shah (editorial)',
	'user_email'   => 'not-an-account-edit@example.test', // Ignored, not an editorial attribute.
	'role'         => 'author',
	'_wpnonce'     => wp_create_nonce( 'salt_profile_' . $id ),
	'acf'          => array( 'field_salt_collection_team_role' => 'Lead gardener' ),
);
list( $status ) = salt_profile_http( admin_url( 'admin-post.php' ), array_merge( $save, array( '_wpnonce' => '' ) ), $cookies );
salt_harness_result( 403 === $status, 'editor save requires the target-specific nonce' );
list( $status ) = salt_profile_http( admin_url( 'admin-post.php' ), $save, $cookies );
clean_user_cache( $id );
$after = get_userdata( $id );
salt_harness_result( 302 === $status && $after->display_name === $save['display_name'] && $after->user_pass === $user->user_pass && $after->user_email === $user->user_email && array( 'subscriber' ) === $after->roles && 'Lead gardener' === get_user_meta( $id, 'role', true ) && salt_team_profile_url( $id ) === $url, 'editor save changes public copy, not slug, credentials or privileges', (string) $status );
salt_harness_result( ! current_user_can( 'edit_user', $id ) && ! current_user_can( 'promote_users' ) && ! current_user_can( 'create_users' ), 'profile editor has no account-management capabilities' );
$key                   = 'field_salt_collection_showcase_query_items';
$ajax                  = array(
	'action'      => 'acf/fields/select/query',
	'field_key'   => $key,
	'salt_source' => 'team',
	's'           => '',
	'paged'       => 1,
	'nonce'       => wp_create_nonce( 'acf_field_select_' . $key ),
);
list( $status, $body ) = salt_profile_http( admin_url( 'admin-ajax.php' ), $ajax, $cookies );
$results               = json_decode( $body, true );
$ids                   = array_map( 'intval', array_column( $results['results'] ?? array(), 'id' ) );
salt_harness_result( 200 === $status && in_array( $id, $ids, true ) && in_array( $other, $ids, true ) && ! in_array( $private, $ids, true ) && ! str_contains( $body, $user->user_email ), 'real ACF select AJAX lists ticked users only', sprintf( 'HTTP %d, %d choices', $status, count( $ids ) ) );
$ajax['salt_source'] = 'posts';
$ajax['s']           = 'Private writer credit';
list( , $body )      = salt_profile_http( admin_url( 'admin-ajax.php' ), $ajax, $cookies );
salt_harness_result( array( $private_post ) === array_map( 'intval', array_column( json_decode( $body, true )['results'] ?? array(), 'id' ) ), 'same ordered picker switches to published posts' );
$toggle            = $save;
$toggle['acf']     = array( 'field_salt_collection_team_show_on_website' => '0' );
list( $saved )     = salt_profile_http( admin_url( 'admin-post.php' ), $toggle, $cookies );
list( $canonical ) = salt_profile_http( $url );
list( $legacy )    = salt_profile_http( $old );
list( , $byline )  = salt_profile_http( get_permalink( $post ) );
salt_harness_result( 302 === $saved && 404 === $canonical && 404 === $legacy && str_contains( $byline, esc_html( $after->display_name ) ) && ! str_contains( $byline, $url ), 'unticking immediately removes both routes, not native post credit' );
$toggle['acf']['field_salt_collection_team_show_on_website'] = '1';
list( $saved )     = salt_profile_http( admin_url( 'admin-post.php' ), $toggle, $cookies );
list( $canonical ) = salt_profile_http( $url );
clean_user_cache( $id );
salt_harness_result( 302 === $saved && 200 === $canonical && array( 'subscriber' ) === get_userdata( $id )->roles, 'reticking restores the same public URL without changing the account role' );
$cookies          = salt_profile_session( $id );
$save['_wpnonce'] = wp_create_nonce( 'salt_profile_' . $id );
list( $status )   = salt_profile_http( admin_url( 'admin-post.php' ), $save, $cookies );
salt_harness_result( 403 === $status, 'even with a valid nonce the published subscriber cannot use the editorial endpoint' );
file_put_contents(
	getenv( 'SALT_HARNESS_STATE' ) . '.profiles',
	wp_json_encode(
		array(
			'profile' => $url,
			'index'   => get_permalink( $page ),
			'cards'   => get_permalink( $cards ),
			'byline'  => get_permalink( $private_post ),
			'post'    => get_permalink( $post ),
			'editor'  => $admin,
		)
	)
);
salt_harness_exit();
