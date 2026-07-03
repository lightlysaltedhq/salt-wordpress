<?php
/**
 * Core filters: Gutenberg off, sitemap/robots ownership (COMPL01),
 * comments policy (COMPL08).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable Gutenberg for all post types (spec §3 — classic editor only).
 *
 * @return bool
 */
function salt_disable_block_editor(): bool {
	return false;
}
add_filter( 'use_block_editor_for_post_type', 'salt_disable_block_editor', 100 );

/**
 * Sitemap ownership (COMPL01): WordPress core's wp-sitemap.xml is the
 * single source of truth. No SEO plugin is bundled. Public CPTs registered
 * by salt modules are included automatically by core; this filter exists
 * as the documented, parent-owned exclusion point (display-only CPTs are
 * registered public=false and never appear).
 *
 * @param array<string,object> $post_types Sitemap-eligible post types.
 * @return array<string,object>
 */
function salt_sitemap_post_types( array $post_types ): array {
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'salt_sitemap_post_types' );

/**
 * robots.txt ownership (COMPL01): virtual via this filter (composes with
 * core's wp-admin disallow), appending the sitemap location. No physical
 * robots.txt ships.
 *
 * @param string $output Current robots.txt body.
 * @return string
 */
function salt_robots_txt( string $output ): string {
	$output .= "\nSitemap: " . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";
	return $output;
}
add_filter( 'robots_txt', 'salt_robots_txt', 20 );

/**
 * Comments policy (COMPL08): closed by default fleet-wide. A site that
 * genuinely needs comments re-opens via this filter at priority 20+ from
 * the child theme.
 *
 * @return bool
 */
function salt_comments_closed(): bool {
	return false;
}
add_filter( 'comments_open', 'salt_comments_closed', 10 );
add_filter( 'pings_open', 'salt_comments_closed', 10 );

/**
 * Archive titles without the core prefix ("Archives:", "Category:", …).
 *
 * Core wraps the bare title in a raw <span> whenever a prefix is present
 * (get_the_archive_title() since WP 5.5) — that markup double-escapes to
 * literal text inside salt_the_document_h1(). Returning an empty prefix
 * makes core return the plain title, which is also the H1 we actually
 * want ("Work", not "Archives: Work"). Child themes re-add context at 20+.
 *
 * @return string
 */
function salt_archive_title_prefix(): string {
	return '';
}
add_filter( 'get_the_archive_title_prefix', 'salt_archive_title_prefix' );

/**
 * 301 legacy top-level post URLs to their current permalink.
 *
 * Moving posts under a prefix (the fleet's /blog/%postname%/ structure)
 * leaves old /{slug}/ URLs 404ing: WP::parse_request() verifies pagename
 * matches during rule matching, so a slug that is a POST (not a page)
 * falls through with EMPTY query vars — core's
 * redirect_guess_404_permalink() (which only reads the `name` var) never
 * fires. This closes that gap: on a 404 whose request path is one bare
 * slug segment matching a published post, redirect permanently.
 *
 * @return void
 */
function salt_redirect_moved_post(): void {
	if ( ! is_404() ) {
		return;
	}

	// The slug never reaches the query vars (see above) — read the path.
	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path segment is regex-validated below; the value is only compared against stored slugs.
	$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
	$slug    = trim( $path, '/' );

	// Top-level slugs only — nested paths are genuine 404s.
	if ( '' === $slug || 1 !== preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
		return;
	}

	$post = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
		wp_safe_redirect( (string) get_permalink( $post ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'salt_redirect_moved_post' );
