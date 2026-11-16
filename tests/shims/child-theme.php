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
 * It also defines locate_template() child-first, as WordPress's is: the
 * stylesheet directory, then the template directory. With no child theme
 * active the two are the same, so the lookup is exactly the parent-only one
 * in tests/wp-shims.php. To win over that shim's definition, this file is
 * required BEFORE tests/wp-shims.php (tests/bootstrap.php), which is why it
 * has no ABSPATH guard: ABSPATH is not defined yet when it loads. Nothing
 * here runs at load time.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

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

if ( ! function_exists( 'locate_template' ) ) {
	/**
	 * Child-first template lookup (WordPress order): the active child theme,
	 * then the parent.
	 *
	 * @param string|string[] $template_names Template file(s), relative to the theme.
	 * @return string Located path, or ''.
	 */
	function locate_template( $template_names ): string {
		foreach ( (array) $template_names as $name ) {
			foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
				$path = $dir . '/' . ltrim( $name, '/' );
				if ( is_readable( $path ) ) {
					return $path;
				}
			}
		}
		return '';
	}
}
