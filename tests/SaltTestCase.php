<?php
/**
 * Base test case — resets all shim + per-request salt state between tests.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class SaltTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_reset();

		// Per-request salt state (registered lazily as modules land).
		foreach ( array( 'salt_render_state_reset', 'salt_schema_reset', 'salt_anchor_registry_reset' ) as $reset ) {
			if ( function_exists( $reset ) ) {
				$reset();
			}
		}
	}

	/**
	 * Seed the fixture post set.
	 *
	 * @param array<int,array<string,mixed>> $posts Post property arrays.
	 * @return void
	 */
	protected function seed_posts( array $posts ): void {
		$GLOBALS['salt_test_posts'] = array_map(
			static fn( array $props ): WP_Post => new WP_Post( $props ),
			$posts
		);
	}

	/**
	 * Captured salt_log() lines.
	 *
	 * @return string[]
	 */
	protected function logs(): array {
		return $GLOBALS['salt_test_logs'];
	}
}
