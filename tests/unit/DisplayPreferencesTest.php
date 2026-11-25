<?php
/**
 * Display preferences service + display-preferences widget (spec §14;
 * A11Y05/06; FAIL02) — gating, controls filtering, args contract, enqueue.
 *
 * The service module is not in the foundation bootstrap, so this file
 * requires it directly (ABSPATH-guarded and idempotent).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/display-preferences.php';

final class DisplayPreferencesTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_asset_manifest_reset();
	}

	protected function tearDown(): void {
		salt_asset_manifest_reset();
		parent::tearDown();
	}

	/**
	 * Seed the display-preferences options.
	 *
	 * @param bool       $enabled  display_preferences_enabled option.
	 * @param array|null $controls display_controls option (null = never saved).
	 * @return void
	 */
	private function configure_widget( bool $enabled = true, ?array $controls = null ): void {
		$GLOBALS['salt_test_fields']['option']['display_preferences_enabled'] = $enabled;
		if ( null !== $controls ) {
			$GLOBALS['salt_test_fields']['option']['display_controls'] = $controls;
		}
	}

	/**
	 * Seed dark-mode availability (module flag + editor option).
	 *
	 * @param bool $module Module flag.
	 * @param bool $option dark_mode option.
	 * @return void
	 */
	private function configure_dark_mode( bool $module = true, bool $option = true ): void {
		$GLOBALS['salt_test_fields']['option']['module_dark_mode'] = $module;
		$GLOBALS['salt_test_fields']['option']['dark_mode']        = $option;
	}

	/**
	 * Capture salt_the_display_preferences() output.
	 *
	 * @return string
	 */
	private function render_seam(): string {
		ob_start();
		salt_the_display_preferences();
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Gating (two-level: module flag AND editor option).
	// -----------------------------------------------------------------

	public function test_inactive_by_default_and_renders_nothing(): void {
		$this->assertFalse( salt_display_preferences_active() );
		$this->assertSame( '', $this->render_seam() );
	}

	public function test_inactive_when_module_is_off(): void {
		$this->configure_widget( true );
		$GLOBALS['salt_test_fields']['option']['module_display_preferences'] = false;

		$this->assertFalse( salt_display_preferences_active() );
		$this->assertSame( '', $this->render_seam() );

		salt_display_preferences_enqueue();
		$this->assertArrayNotHasKey( 'salt-display-prefs', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	public function test_inactive_when_option_is_off(): void {
		$this->configure_widget( false );

		$this->assertFalse( salt_display_preferences_active() );
		$this->assertSame( '', $this->render_seam() );

		salt_display_preferences_enqueue();
		$this->assertArrayNotHasKey( 'salt-display-prefs', $GLOBALS['salt_test_enqueued']['scripts'] );
	}

	public function test_active_when_module_defaults_on_and_option_is_on(): void {
		$this->configure_widget( true );

		$this->assertTrue( salt_display_preferences_active() );
	}

	// -----------------------------------------------------------------
	// Controls filtering.
	// -----------------------------------------------------------------

	public function test_controls_default_to_all_five_when_dark_mode_is_available(): void {
		$this->configure_widget( true );
		$this->configure_dark_mode();

		$this->assertSame(
			array( 'font_size', 'contrast', 'grayscale', 'dark_mode', 'motion' ),
			salt_display_preferences_controls()
		);
	}

	/**
	 * Dark-mode unavailability variants.
	 *
	 * @return array<string,array{bool,bool}>
	 */
	public static function dark_mode_unavailable(): array {
		return array(
			'dark module off'      => array( false, true ),
			'dark option off'      => array( true, false ),
			'both off'             => array( false, false ),
			'nothing ever enabled' => array( true, false ),
		);
	}

	/**
	 * dark_mode is dropped from the controls unless dark mode is live.
	 *
	 * @param bool $module Module flag.
	 * @param bool $option dark_mode option.
	 */
	#[DataProvider( 'dark_mode_unavailable' )]
	public function test_dark_mode_control_dropped_when_dark_mode_is_off( bool $module, bool $option ): void {
		$this->configure_widget( true );
		$this->configure_dark_mode( $module, $option );

		$this->assertSame(
			array( 'font_size', 'contrast', 'grayscale', 'motion' ),
			salt_display_preferences_controls()
		);
	}

	public function test_controls_subset_is_honoured_in_canonical_order(): void {
		$this->configure_widget( true, array( 'motion', 'font_size', 'dark_mode' ) );
		$this->configure_dark_mode();

		$this->assertSame(
			array( 'font_size', 'dark_mode', 'motion' ),
			salt_display_preferences_controls()
		);
	}

	public function test_unknown_stored_control_values_are_dropped(): void {
		$this->configure_widget( true, array( 'font_size', 'bogus', 'contrast' ) );

		$this->assertSame( array( 'font_size', 'contrast' ), salt_display_preferences_controls() );
	}

	public function test_seam_renders_nothing_and_logs_when_all_controls_deselected(): void {
		$this->configure_widget( true, array() );
		$this->configure_dark_mode( false, false );

		$this->assertSame( '', $this->render_seam() );
		$this->assertStringContainsString( 'no controls', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// The rendered widget (FAIL02 + A11Y05/06 markup contract).
	// -----------------------------------------------------------------

	public function test_active_seam_renders_the_hidden_widget(): void {
		$this->configure_widget( true );
		$this->configure_dark_mode();

		$html = $this->render_seam();

		$this->assertStringContainsString( '<div class="salt-display-preferences" hidden>', $html );
		$this->assertStringContainsString( '<details', $html );
		$this->assertStringContainsString( '<summary', $html );
		$this->assertStringContainsString( 'Display preferences', $html );
		// The dark_mode control drives the 'theme' preference (init-script enum).
		$this->assertStringContainsString( 'data-salt-pref="theme"', $html );
		$this->assertStringContainsString( 'data-salt-pref="scale"', $html );
		$this->assertSame( 4, substr_count( $html, 'aria-pressed="false"' ) );
		// No inline styles or scripts (front-end CSP blocks both).
		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_motion_default_is_carried_as_a_data_attribute(): void {
		$this->configure_widget( true );
		$GLOBALS['salt_test_fields']['option']['reduce_motion'] = true;

		$this->assertStringContainsString( 'data-motion-default="reduce"', $this->render_seam() );
	}

	public function test_motion_default_attribute_absent_by_default(): void {
		$this->configure_widget( true );

		$this->assertStringNotContainsString( 'data-motion-default', $this->render_seam() );
	}

	public function test_component_requires_the_controls_arg(): void {
		$level = ob_get_level();
		try {
			salt_get_component( 'display-preferences', array() );
			$this->fail( 'Expected InvalidArgumentException was not thrown.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'required arg &quot;controls&quot; missing', $e->getMessage() );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
	}

	public function test_component_skips_unknown_controls_and_logs(): void {
		$html = salt_get_component( 'display-preferences', array( 'controls' => array( 'font_size', 'sparkle' ) ) );

		$this->assertStringContainsString( 'data-salt-pref="scale"', $html );
		$this->assertStringNotContainsString( 'sparkle', $html );
		$this->assertStringContainsString( 'unknown control "sparkle"', implode( ' ', $this->logs() ) );
	}

	public function test_component_renders_nothing_when_no_control_is_valid(): void {
		$this->assertSame( '', salt_get_component( 'display-preferences', array( 'controls' => array( 'sparkle' ) ) ) );
		$this->assertStringContainsString( 'no valid controls', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Enqueue gating.
	// -----------------------------------------------------------------

	public function test_script_enqueued_when_active(): void {
		$this->configure_widget( true );

		salt_display_preferences_enqueue();

		$this->assertArrayHasKey( 'salt-display-prefs', $GLOBALS['salt_test_enqueued']['scripts'] );
		$enqueued = $GLOBALS['salt_test_enqueued']['scripts']['salt-display-prefs'];
		$this->assertStringContainsString( '/assets/dist/display-prefs.', $enqueued['src'] );
		$this->assertSame( 'defer', $enqueued['args']['strategy'] );
		$this->assertTrue( $enqueued['args']['in_footer'] );
	}

	public function test_script_not_enqueued_when_inactive(): void {
		salt_display_preferences_enqueue();

		$this->assertArrayNotHasKey( 'salt-display-prefs', $GLOBALS['salt_test_enqueued']['scripts'] );
	}
}
