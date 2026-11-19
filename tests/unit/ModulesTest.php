<?php
/**
 * Tests for inc/modules.php — module feature-flags.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module feature-flag tests.
 */
final class ModulesTest extends SaltTestCase {

	public function test_default_is_enabled_when_option_unset(): void {
		$this->assertTrue( salt_module_enabled( 'service' ) );
	}

	public function test_option_false_disables_module(): void {
		$GLOBALS['salt_test_fields']['option']['module_service'] = false;
		$this->assertFalse( salt_module_enabled( 'service' ) );
	}

	public function test_option_true_enables_module(): void {
		$GLOBALS['salt_test_fields']['option']['module_dark_mode'] = true;
		$this->assertTrue( salt_module_enabled( 'dark_mode' ) );
	}

	public function test_filter_at_priority_twenty_overrides_option(): void {
		$GLOBALS['salt_test_fields']['option']['module_faq'] = true;
		add_filter(
			'salt_module_enabled',
			static fn( bool $enabled, string $slug ): bool => 'faq' === $slug ? false : $enabled,
			20,
			2
		);
		$this->assertFalse( salt_module_enabled( 'faq' ) );
		$this->assertTrue( salt_module_enabled( 'work' ) );
	}

	public function test_unknown_slug_logs_once_and_returns_true(): void {
		$this->assertTrue( salt_module_enabled( 'not_a_module' ) );
		$this->assertTrue( salt_module_enabled( 'not_a_module' ) );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'not_a_module', $this->logs()[0] );
	}

	public function test_module_cpts_covers_exactly_the_six_cpt_modules(): void {
		$this->assertSame(
			array(
				'service'     => 'service',
				'testimonial' => 'testimonial',
				'work'        => 'work',
				'faq'         => 'faq',
				'location'    => 'location',
			),
			salt_module_cpts()
		);
	}

	public function test_modules_returns_all_known_slugs(): void {
		$this->assertSame(
			array( 'service', 'testimonial', 'team_member', 'work', 'faq', 'location', 'dark_mode', 'display_preferences' ),
			array_keys( salt_modules() )
		);
	}
}
