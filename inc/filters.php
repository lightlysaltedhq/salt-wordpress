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
