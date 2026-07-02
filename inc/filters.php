<?php
/**
 * Core filters: Gutenberg off, sitemap/robots ownership, security headers.
 *
 * Built out in Phase 1 (§5.8) — COMPL01, SEC06.
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
