<?php
/**
 * WP shims for the active child theme (salt-overrides.json, ID-3613).
 *
 * get_stylesheet_directory() returns the parent directory — no child theme
 * active, as on every existing test — unless a test points it at a fixture
 * child theme with salt_test_use_child_theme(). SaltTestCase resets it before
 * every test. Loaded from tests/bootstrap.php because the render path asks
 * for it on every split section.
 *
 * locate_template() (tests/wp-shims.php) stays parent-only: tests hand a
 * child's template path to salt_render_resolved_section() themselves, the
 * path WordPress's locate_template() would return.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Point get_stylesheet_directory() at a fixture child theme (null = none).
 *
 * Also forgets the memoised declaration, as a theme switch would.
 *
 * @param string|null $dir Child theme directory, or null for no child theme.
 * @return void
 */
function salt_test_use_child_theme( ?string $dir ): void {
	$GLOBALS['salt_test_stylesheet_dir'] = $dir;
	if ( function_exists( 'salt_overrides_reset' ) ) {
		salt_overrides_reset();
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	/**
	 * Active (child) theme directory; the parent's when no child is active.
	 *
	 * @return string
	 */
	function get_stylesheet_directory(): string {
		$dir = $GLOBALS['salt_test_stylesheet_dir'] ?? null;
		return is_string( $dir ) ? $dir : get_template_directory();
	}
}
