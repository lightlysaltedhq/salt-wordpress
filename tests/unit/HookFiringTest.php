<?php
/**
 * Hook-firing smoke: the theme's registered wp_head / wp_footer / init
 * callbacks must survive do_action()'s empty-string argument (the exact
 * quirk that TypeError-fataled salt_schema_render on the live front end).
 *
 * The inc modules register their hooks at bootstrap load time via the
 * add_action shim, so they are already in the registry here.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class HookFiringTest extends SaltTestCase {

	/**
	 * Firing an action the WordPress way (with the '' arg) must not fatal
	 * for any salt callback registered on it.
	 *
	 * @return void
	 */
	public function test_core_render_hooks_survive_do_action_empty_arg(): void {
		foreach ( array( 'wp_head', 'wp_footer' ) as $hook ) {
			ob_start();
			do_action( $hook );
			ob_end_clean();
		}

		$this->assertTrue( true, 'wp_head and wp_footer fired without a TypeError.' );
	}

	public function test_schema_footer_wrapper_ignores_stray_hook_argument(): void {
		// Exactly how do_action( 'wp_footer' ) invokes it.
		salt_schema_render_footer( '' );

		// With an org configured it emits the graph; the point is no fatal.
		$GLOBALS['salt_test_fields']['option']['org_name'] = 'Fixture Co';
		ob_start();
		salt_schema_render_footer( '' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'application/ld+json', $html );
	}
}
