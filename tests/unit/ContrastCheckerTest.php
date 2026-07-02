<?php
/**
 * Tests for inc/services/contrast-checker.php — the Brand & Design screen's
 * live contrast checker (spec §13/§14): screen predicate, pinned targets
 * (the app.css sync tripwire), and enqueue gating.
 *
 * The service module is not in the foundation bootstrap, so this file
 * requires it directly (ABSPATH-guarded and idempotent).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/admin.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/contrast-checker.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContrastCheckerTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_localized'] = array();
		unset( $_GET['page'] );
	}

	protected function tearDown(): void {
		unset( $_GET['page'] );
		parent::tearDown();
	}

	// -----------------------------------------------------------------
	// Screen predicate.
	// -----------------------------------------------------------------

	/**
	 * Page slugs and the expected predicate verdict.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function screenProvider(): array {
		return array(
			'brand sub-page'      => array( 'salt-settings-brand', true ),
			'parent settings'     => array( 'salt-settings', false ),
			'other sub-page'      => array( 'salt-settings-header', false ),
			'suffixed slug'       => array( 'salt-settings-brand-extra', false ),
			'empty'               => array( '', false ),
			'unrelated wp screen' => array( 'options-general', false ),
		);
	}

	/**
	 * salt_is_brand_options_screen() is an exact-match pure predicate.
	 *
	 * @param string $page     Page slug.
	 * @param bool   $expected Verdict.
	 */
	#[DataProvider( 'screenProvider' )]
	public function test_brand_screen_predicate( string $page, bool $expected ): void {
		$this->assertSame( $expected, salt_is_brand_options_screen( $page ) );
	}

	// -----------------------------------------------------------------
	// Pinned targets (sync tripwire with assets/src/css/app.css).
	// -----------------------------------------------------------------

	public function test_targets_pin_the_real_surface_and_step_values(): void {
		$targets = salt_contrast_targets();

		$this->assertSame( 4.5, $targets['thresholds']['text'] );
		$this->assertSame( 3.0, $targets['thresholds']['ui'] );

		$this->assertSame( array( '#ffffff', '#e7ebf1' ), $targets['light']['surfaces'] );
		$this->assertSame( array( 600 ), $targets['light']['steps'] );
		$this->assertSame( '#0f172a', $targets['light']['text'] );

		$this->assertSame( array( '#141619', '#26292e' ), $targets['dark']['surfaces'] );
		$this->assertSame( array( 400, 300 ), $targets['dark']['steps'] );
		$this->assertSame( '#f8fafc', $targets['dark']['text'] );

		$this->assertSame( '#d2d8e0', $targets['border'] );
		$this->assertSame( '#4b5563', $targets['fallback'] );

		$this->assertSame(
			array(
				'primary'   => 'field_salt_options_brand_primary',
				'secondary' => 'field_salt_options_brand_secondary',
				'accent'    => 'field_salt_options_brand_accent',
			),
			$targets['fields']
		);
	}

	/**
	 * Tripwire: the pinned hexes/steps are the values app.css actually ships.
	 * If a designer retunes the neutral ramp or the light/dark brand steps in
	 * app.css, this test fails and salt_contrast_targets() must be re-pinned.
	 */
	public function test_targets_match_app_css_source(): void {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/src/css/app.css' );
		$this->assertIsString( $css );

		// Neutral ramp steps the surfaces/border aliases resolve to.
		$this->assertStringContainsString( '--color-neutral-100: #e7ebf1;', $css );
		$this->assertStringContainsString( '--color-neutral-200: #d2d8e0;', $css );
		$this->assertStringContainsString( '--color-neutral-900: #26292e;', $css );
		$this->assertStringContainsString( '--color-neutral-950: #141619;', $css );

		// Light theme: white surface, neutral-100 alt, brand = primary-600.
		$this->assertStringContainsString( '--color-surface: #fff;', $css );
		$this->assertStringContainsString( '--color-surface-alt: var(--color-neutral-100);', $css );
		$this->assertStringContainsString( '--color-brand: var(--color-primary-600);', $css );

		// Dark theme: neutral-950/900 surfaces, brand steps 400/300.
		$this->assertStringContainsString( '--color-surface: var(--color-neutral-950);', $css );
		$this->assertStringContainsString( '--color-surface-alt: var(--color-neutral-900);', $css );
		$this->assertStringContainsString( '--color-brand: var(--color-primary-400);', $css );
		$this->assertStringContainsString( '--color-brand-strong: var(--color-primary-300);', $css );

		// Border role resolves to neutral-200.
		$this->assertStringContainsString( '--color-border: var(--color-neutral-200);', $css );
	}

	/**
	 * Coherence: the pinned neutral hexes ARE the ramp inc/colors.php derives
	 * from the FAIL03 fallback '#4b5563' — the two sources cannot drift apart.
	 */
	public function test_pinned_neutrals_are_the_derived_fallback_ramp(): void {
		$targets = salt_contrast_targets();
		$ramp    = salt_derive_ramp( $targets['fallback'] );

		$this->assertSame( $targets['light']['surfaces'][1], $ramp[100] );
		$this->assertSame( $targets['border'], $ramp[200] );
		$this->assertSame( $targets['dark']['surfaces'][1], $ramp[900] );
		$this->assertSame( $targets['dark']['surfaces'][0], $ramp[950] );
	}

	public function test_text_anchors_are_the_text_on_candidates(): void {
		$targets = salt_contrast_targets();

		$this->assertSame( $targets['light']['text'], salt_text_on( '#ffffff' ) );
		$this->assertSame( $targets['dark']['text'], salt_text_on( '#141619' ) );
	}

	public function test_checker_data_adds_i18n_strings_to_the_targets(): void {
		$data = salt_contrast_checker_data();

		foreach ( array_keys( salt_contrast_targets() ) as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}

		$this->assertArrayHasKey( 'i18n', $data );
		foreach ( array( 'heading', 'intro', 'light', 'dark', 'pass', 'fail', 'needs' ) as $string ) {
			$this->assertArrayHasKey( $string, $data['i18n'] );
			$this->assertNotSame( '', $data['i18n'][ $string ] );
		}
	}

	// -----------------------------------------------------------------
	// Register seam (deterministic enqueue behaviour).
	// -----------------------------------------------------------------

	public function test_register_enqueues_and_localizes_on_the_brand_screen(): void {
		$src = 'https://example.test/wp-content/themes/salt/assets/dist/admin/contrast-checker.abc123.js';

		$this->assertTrue( salt_contrast_checker_register( 'salt-settings-brand', $src ) );

		$this->assertArrayHasKey( 'salt-contrast', $GLOBALS['salt_test_enqueued']['scripts'] );
		$enqueued = $GLOBALS['salt_test_enqueued']['scripts']['salt-contrast'];
		$this->assertSame( $src, $enqueued['src'] );
		$this->assertSame( array( 'acf-input' ), $enqueued['deps'] );
		$this->assertNull( $enqueued['ver'] );
		$this->assertTrue( $enqueued['args']['in_footer'] );

		$this->assertArrayHasKey( 'salt-contrast', $GLOBALS['salt_test_localized'] );
		$this->assertSame(
			salt_contrast_checker_data(),
			$GLOBALS['salt_test_localized']['salt-contrast']['saltContrast']
		);
	}

	public function test_register_is_a_no_op_off_the_brand_screen(): void {
		$this->assertFalse( salt_contrast_checker_register( 'salt-settings-header', 'https://example.test/x.js' ) );

		$this->assertArrayNotHasKey( 'salt-contrast', $GLOBALS['salt_test_enqueued']['scripts'] );
		$this->assertSame( array(), $GLOBALS['salt_test_localized'] );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_register_fails_loud_when_the_built_asset_is_missing(): void {
		$this->assertFalse( salt_contrast_checker_register( 'salt-settings-brand', null ) );

		$this->assertArrayNotHasKey( 'salt-contrast', $GLOBALS['salt_test_enqueued']['scripts'] );
		$this->assertNotEmpty( preg_grep( '/admin\/contrast-checker\.js/', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Enqueue wrapper ($_GET gate).
	// -----------------------------------------------------------------

	public function test_enqueue_is_a_silent_no_op_off_page(): void {
		$_GET['page'] = 'salt-settings-header';

		salt_contrast_checker_enqueue();

		$this->assertArrayNotHasKey( 'salt-contrast', $GLOBALS['salt_test_enqueued']['scripts'] );
		$this->assertSame( array(), $GLOBALS['salt_test_localized'] );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_enqueue_is_a_silent_no_op_without_a_page_param(): void {
		salt_contrast_checker_enqueue();

		$this->assertArrayNotHasKey( 'salt-contrast', $GLOBALS['salt_test_enqueued']['scripts'] );
		$this->assertSame( array(), $this->logs() );
	}

	/**
	 * On the Brand screen the gate opens: the checker either enqueues (built
	 * manifest has the entry) or fails loud about the missing build — never
	 * silence. Tolerant of manifest state so parallel build agents cannot
	 * flake this test.
	 */
	public function test_enqueue_acts_on_the_brand_screen(): void {
		salt_asset_manifest_reset();
		$_GET['page'] = 'salt-settings-brand';

		salt_contrast_checker_enqueue();

		$enqueued = isset( $GLOBALS['salt_test_enqueued']['scripts']['salt-contrast'] );
		$logged   = array() !== preg_grep( '/admin\/contrast-checker\.js/', $this->logs() );

		$this->assertTrue( $enqueued || $logged, 'Brand screen must enqueue the checker or log the missing asset.' );

		salt_asset_manifest_reset();
	}
}
