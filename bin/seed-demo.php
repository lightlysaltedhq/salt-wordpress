<?php
/**
 * Demo-content seeder for salt — run on a WordPress install via:
 *
 *   wp eval-file bin/seed-demo.php
 *   SALT_DEMO_PURGE=1 wp eval-file bin/seed-demo.php   # remove demo content only
 *
 * Populates a fictional digital-agency site: every CPT, taxonomy terms,
 * sideloaded placeholder media, seven builder pages whose sections
 * cross-link to CPTs and pages, primary/footer menus, brand/company
 * options, and reading settings. Idempotent — a default run purges the
 * previous demo content (everything tagged _salt_demo) and reseeds, so
 * re-running converges to the same state with no duplication.
 *
 * Dev/ops tooling only — NEVER loaded by the theme at runtime (content is
 * DB-resident, DATA03). Requires ACF Pro active (uses update_field()).
 *
 * NOTE: no `declare(strict_types=1)` here — `wp eval-file` runs this entry
 * file through eval(), where a declare must (and cannot) be the first
 * statement. The required files (demo/*.php) are separate compilation units
 * and keep their strict_types.
 *
 * @package Salt\Demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file bin/seed-demo.php\n" );
	return;
}

require_once __DIR__ . '/demo/refs.php';
require_once __DIR__ . '/demo/images.php';

$salt_demo = require __DIR__ . '/demo/data.php';

if ( ! function_exists( 'update_field' ) ) {
	salt_demo_log( 'ACF Pro is not active — cannot seed. Activate ACF Pro and retry.' );
	return;
}

$salt_demo_post_types = array_merge( array_keys( $salt_demo['cpts'] ), array( 'page', 'post', 'attachment' ) );

salt_demo_refs( 'reset' );
salt_demo_purge( $salt_demo_post_types, $salt_demo['menus'] );

if ( getenv( 'SALT_DEMO_PURGE' ) ) {
	update_option( 'show_on_front', 'posts' );
	set_theme_mod( 'nav_menu_locations', array() );
	delete_option( 'salt_demo_seeded' );
	salt_demo_log( 'purged demo content. Done.' );
	return;
}

// 1. Modules on (explicit, though they default enabled).
foreach ( $salt_demo['modules'] as $module ) {
	update_field( 'module_' . $module, 1, 'option' );
}

// 2. Taxonomy terms (get-or-create — naturally idempotent).
foreach ( $salt_demo['terms'] as $taxonomy => $terms ) {
	foreach ( $terms as $term ) {
		$existing = get_term_by( 'slug', $term['slug'], $taxonomy );
		if ( $existing instanceof WP_Term ) {
			$term_id = (int) $existing->term_id;
		} else {
			$created = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
			$term_id = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
		}
		if ( $term_id > 0 ) {
			salt_demo_ref_set( 'term/' . $taxonomy . '/' . $term['slug'], $term_id );
		}
	}
}

// 3. Images (deterministic sideload → ref:image/*).
salt_demo_seed_images( $salt_demo['images'] );

// 4. CPT posts (create bare; stash deferred field/term/thumbnail data).
// Everything is authored by the demo author (first administrator) — under
// `wp eval-file` there is no current user, and post_author 0 would break
// the single-post byline/author box.
$salt_demo_author   = salt_demo_author_id();
$salt_demo_deferred = array();

foreach ( $salt_demo['cpts'] as $cpt => $items ) {
	foreach ( $items as $item ) {
		$postarr = array(
			'post_type'    => $cpt,
			'post_status'  => 'publish',
			'post_title'   => $item['title'],
			'post_name'    => $item['slug'],
			'post_content' => $item['content'] ?? '',
			'post_excerpt' => $item['excerpt'] ?? '',
			'menu_order'   => (int) ( $item['menu_order'] ?? 0 ),
			'post_author'  => $salt_demo_author,
		);
		// Optional fixed publish date (blog posts backdate so the single's
		// "Updated" line — modified > published — is demonstrable).
		if ( ! empty( $item['date'] ) ) {
			$postarr['post_date'] = (string) $item['date'];
		}
		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			salt_demo_log( sprintf( 'failed to create %s "%s": %s', $cpt, $item['title'], $post_id->get_error_message() ) );
			continue;
		}
		update_post_meta( $post_id, '_salt_demo', 1 );
		salt_demo_ref_set( $cpt . '/' . $item['slug'], (int) $post_id );

		$salt_demo_deferred[] = array(
			'id'        => (int) $post_id,
			'thumbnail' => $item['thumbnail'] ?? null,
			'terms'     => $item['terms'] ?? array(),
			'fields'    => $item['fields'] ?? array(),
		);
	}
}

// 5. Pages (create bare; stash sections + reading-settings roles).
foreach ( $salt_demo['pages'] as $slug => $page ) {
	$page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $page['title'],
			'post_name'   => $slug,
		),
		true
	);
	if ( is_wp_error( $page_id ) ) {
		salt_demo_log( sprintf( 'failed to create page "%s": %s', $slug, $page_id->get_error_message() ) );
		continue;
	}
	update_post_meta( $page_id, '_salt_demo', 1 );
	salt_demo_ref_set( 'page/' . $slug, (int) $page_id );

	if ( ! empty( $page['front_page'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $page_id );
	}
	if ( ! empty( $page['posts_page'] ) ) {
		update_option( 'page_for_posts', (int) $page_id );
	}

	$salt_demo_deferred[] = array(
		'id'       => (int) $page_id,
		'sections' => $page['sections'] ?? array(),
	);
}

// 6. Resolve pass — every ref/url token now has a target.
foreach ( $salt_demo_deferred as $deferred ) {
	$id = $deferred['id'];

	if ( ! empty( $deferred['thumbnail'] ) ) {
		$thumb = salt_demo_resolve_refs( $deferred['thumbnail'] );
		if ( is_int( $thumb ) && $thumb > 0 ) {
			set_post_thumbnail( $id, $thumb );
		}
	}

	foreach ( $deferred['terms'] ?? array() as $taxonomy => $term_refs ) {
		$term_ids = array_values( array_filter( array_map( 'salt_demo_resolve_refs', $term_refs ), 'is_int' ) );
		if ( array() !== $term_ids ) {
			wp_set_object_terms( $id, $term_ids, $taxonomy );
		}
	}

	foreach ( $deferred['fields'] ?? array() as $field_name => $value ) {
		update_field( $field_name, salt_demo_resolve_refs( $value ), $id );
	}

	if ( isset( $deferred['sections'] ) ) {
		update_field( 'sections', salt_demo_resolve_refs( $deferred['sections'] ), $id );
	}
}

// 7. Options (resolve image/url refs now that pages + media exist).
foreach ( $salt_demo['options'] as $name => $value ) {
	update_field( $name, salt_demo_resolve_refs( $value ), 'option' );
}

// 7.5 Blog author profile — display name + bio (WP user) and the ACF
// profile_photo user field feed the single-post byline/author box.
if ( isset( $salt_demo['author'] ) && $salt_demo_author > 0 ) {
	wp_update_user(
		array(
			'ID'           => $salt_demo_author,
			'display_name' => (string) ( $salt_demo['author']['display_name'] ?? '' ),
			'description'  => (string) ( $salt_demo['author']['description'] ?? '' ),
		)
	);
	$salt_demo_photo = salt_demo_resolve_refs( $salt_demo['author']['photo'] ?? null );
	if ( is_int( $salt_demo_photo ) && $salt_demo_photo > 0 ) {
		update_field( 'profile_photo', $salt_demo_photo, 'user_' . $salt_demo_author );
	}
	salt_demo_log( sprintf( 'author profile set on user %d.', $salt_demo_author ) );
}

// 7.6 Permalinks: /blog/%postname%/ (fleet best practice — category-less
// post slugs under a /blog/ prefix; CPTs are unaffected, with_front false).
update_option( 'permalink_structure', '/blog/%postname%/' );

// 8. Menus + location assignment.
$salt_demo_locations = array();
foreach ( $salt_demo['menus'] as $location => $items ) {
	$menu_name = 'Demo ' . ucfirst( $location );
	$existing  = wp_get_nav_menu_object( $menu_name );
	if ( $existing ) {
		wp_delete_nav_menu( $existing->term_id );
	}
	$menu_id = wp_create_nav_menu( $menu_name );
	if ( is_wp_error( $menu_id ) ) {
		continue;
	}

	foreach ( $items as $item ) {
		if ( isset( $item['archive'] ) ) {
			$url = salt_demo_resolve_url( 'url:archive/' . $item['archive'] );
		} else {
			$url = salt_demo_resolve_url( str_replace( 'ref:', 'url:', (string) $item['ref'] ) );
		}
		if ( '' === $url ) {
			continue;
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => $item['title'],
				'menu-item-url'    => $url,
				'menu-item-status' => 'publish',
			)
		);
	}
	$salt_demo_locations[ $location ] = $menu_id;
}
set_theme_mod( 'nav_menu_locations', $salt_demo_locations );

update_option( 'salt_demo_seeded', gmdate( 'c' ) );
flush_rewrite_rules( false );

salt_demo_log( sprintf( 'seeded: %d refs registered. Visit the site.', count( salt_demo_refs() ) ) );

/**
 * Delete every object tagged _salt_demo and the demo menus.
 *
 * @param string[]                                       $post_types Post types to sweep.
 * @param array<string,array<int,array<string,mixed>>>   $menus      Menu spec (for names).
 * @return void
 */
function salt_demo_purge( array $post_types, array $menus ): void {
	$posts = get_posts(
		array(
			'post_type'        => $post_types,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'meta_key'         => '_salt_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off CLI seeder.
			'meta_value'       => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'suppress_filters' => false,
		)
	);
	foreach ( $posts as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	foreach ( array_keys( $menus ) as $location ) {
		$menu = wp_get_nav_menu_object( 'Demo ' . ucfirst( $location ) );
		if ( $menu ) {
			wp_delete_nav_menu( $menu->term_id );
		}
	}

	if ( array() !== $posts ) {
		salt_demo_log( sprintf( 'purged %d prior demo objects.', count( $posts ) ) );
	}
}

/**
 * The demo author: the first administrator by ID.
 *
 * @return int User ID (0 when no administrator exists).
 */
function salt_demo_author_id(): int {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
			'fields'  => 'ID',
		)
	);

	return array() !== $admins ? (int) $admins[0] : 0;
}
