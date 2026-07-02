<?php
/**
 * Pre-paint preferences init builder (§13a/§14; SEC06 inline-script contract).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class ThemeInitScriptTest extends SaltTestCase {

	public function test_nothing_enabled_emits_nothing(): void {
		$this->assertSame(
			'',
			salt_theme_init_js(
				array(
					'dark'                  => false,
					'prefs'                 => false,
					'motion_default_reduce' => false,
				)
			)
		);
	}

	public function test_dark_only_reproduces_the_v2_behaviour(): void {
		$js = salt_theme_init_js(
			array(
				'dark'                  => true,
				'prefs'                 => false,
				'motion_default_reduce' => false,
			)
		);

		$this->assertStringContainsString( 'salt-theme', $js );
		$this->assertStringContainsString( 'prefers-color-scheme: dark', $js );
		$this->assertStringContainsString( 'data-theme', $js );
		// The v2 regression guard: no preference keys leak into a dark-only build.
		foreach ( array( 'salt-motion', 'salt-contrast', 'salt-grayscale', 'salt-scale' ) as $key ) {
			$this->assertStringNotContainsString( $key, $js );
		}
		$this->assertSame( 1, substr_count( $js, 'try{' ), 'Single try/catch.' );
		$this->assertStringEndsWith( '}catch(e){}', $js );
	}

	public function test_prefs_enable_all_preference_keys(): void {
		$js = salt_theme_init_js(
			array(
				'dark'                  => true,
				'prefs'                 => true,
				'motion_default_reduce' => false,
			)
		);

		foreach ( array( 'salt-theme', 'salt-motion', 'salt-contrast', 'salt-grayscale', 'salt-scale', 'prefers-contrast: more' ) as $needle ) {
			$this->assertStringContainsString( $needle, $js );
		}
		$this->assertStringNotContainsString( '||!m', $js, 'No site-wide reduce default unless configured.' );
	}

	public function test_motion_default_applies_when_unset(): void {
		$js = salt_theme_init_js(
			array(
				'dark'                  => false,
				'prefs'                 => false,
				'motion_default_reduce' => true,
			)
		);

		$this->assertStringContainsString( '||!m', $js, 'Unset localStorage falls back to the site reduce default.' );
		$this->assertStringContainsString( 'data-motion', $js );
		$this->assertStringNotContainsString( 'salt-theme', $js );
	}

	public function test_scale_values_are_a_closed_enum(): void {
		$js = salt_theme_init_js(
			array(
				'dark'                  => false,
				'prefs'                 => true,
				'motion_default_reduce' => false,
			)
		);

		$this->assertStringContainsString( 's==="-1"||s==="1"||s==="2"', $js, 'Only the three stepped values ever reach the attribute.' );
	}
}
