<?php
/**
 * Tests for inc/performance.php — image format capability + asset manifest.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/performance.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Performance module test case.
 */
final class PerformanceTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['salt_test_image_support'] = array(
			'image/avif' => false,
			'image/webp' => false,
		);
		salt_best_image_format_reset();
		salt_asset_manifest_reset();
	}

	/**
	 * Seed the image-support fixture and clear the per-request cache.
	 *
	 * @param bool $avif AVIF supported.
	 * @param bool $webp WebP supported.
	 * @return void
	 */
	private function set_image_support( bool $avif, bool $webp ): void {
		$GLOBALS['salt_test_image_support'] = array(
			'image/avif' => $avif,
			'image/webp' => $webp,
		);
		salt_best_image_format_reset();
	}

	/**
	 * Real committed manifest, decoded.
	 *
	 * @return array<string,string>
	 */
	private function real_manifest(): array {
		$raw = file_get_contents( dirname( __DIR__, 2 ) . '/assets/dist/manifest.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertIsString( $raw );
		$manifest = json_decode( $raw, true );
		$this->assertIsArray( $manifest );
		return $manifest;
	}

	// -----------------------------------------------------------------------
	// salt_best_image_format().
	// -----------------------------------------------------------------------

	/**
	 * Capability matrix cases.
	 *
	 * @return array<string,array{bool,bool,string}>
	 */
	public static function capability_matrix(): array {
		return array(
			'avif supported'    => array( true, true, 'image/avif' ),
			'avif only'         => array( true, false, 'image/avif' ),
			'webp only'         => array( false, true, 'image/webp' ),
			'neither supported' => array( false, false, 'original' ),
		);
	}

	/**
	 * @param bool   $avif     AVIF supported.
	 * @param bool   $webp     WebP supported.
	 * @param string $expected Expected best format.
	 */
	#[DataProvider( 'capability_matrix' )]
	public function test_best_image_format_capability_matrix( bool $avif, bool $webp, string $expected ): void {
		$this->set_image_support( $avif, $webp );
		$this->assertSame( $expected, salt_best_image_format() );
	}

	public function test_best_image_format_is_cached_per_request_and_reset_restores(): void {
		$this->set_image_support( true, true );
		$this->assertSame( 'image/avif', salt_best_image_format() );

		// Capability flips mid-request: cached answer sticks.
		$GLOBALS['salt_test_image_support']['image/avif'] = false;
		$this->assertSame( 'image/avif', salt_best_image_format() );

		// Reset recomputes.
		salt_best_image_format_reset();
		$this->assertSame( 'image/webp', salt_best_image_format() );
	}

	// -----------------------------------------------------------------------
	// salt_image_output_format().
	// -----------------------------------------------------------------------

	public function test_output_format_maps_to_avif_when_supported(): void {
		$this->set_image_support( true, true );

		$this->assertSame(
			array(
				'image/jpeg' => 'image/avif',
				'image/png'  => 'image/avif',
			),
			salt_image_output_format( array() )
		);
	}

	public function test_output_format_maps_to_webp_when_avif_missing(): void {
		$this->set_image_support( false, true );

		$this->assertSame(
			array(
				'image/jpeg' => 'image/webp',
				'image/png'  => 'image/webp',
			),
			salt_image_output_format( array() )
		);
	}

	public function test_output_format_unchanged_when_neither_supported(): void {
		$this->set_image_support( false, false );

		$incoming = array( 'image/bmp' => 'image/jpeg' );
		$this->assertSame( $incoming, salt_image_output_format( $incoming ) );
	}

	public function test_output_format_preserves_unrelated_entries(): void {
		$this->set_image_support( true, false );

		$result = salt_image_output_format( array( 'image/bmp' => 'image/jpeg' ) );
		$this->assertSame( 'image/jpeg', $result['image/bmp'] );
		$this->assertSame( 'image/avif', $result['image/jpeg'] );
	}

	// -----------------------------------------------------------------------
	// salt_image_support_admin_notice().
	// -----------------------------------------------------------------------

	public function test_admin_notice_rendered_when_no_modern_format(): void {
		$this->set_image_support( false, false );

		ob_start();
		salt_image_support_admin_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice notice-warning', $html );
		$this->assertStringContainsString( 'AVIF', $html );
		$this->assertStringContainsString( 'WebP', $html );
	}

	public function test_admin_notice_silent_when_avif_supported(): void {
		$this->set_image_support( true, false );

		ob_start();
		salt_image_support_admin_notice();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_admin_notice_silent_when_webp_supported(): void {
		$this->set_image_support( false, true );

		ob_start();
		salt_image_support_admin_notice();
		$this->assertSame( '', ob_get_clean() );
	}

	// -----------------------------------------------------------------------
	// salt_asset().
	// -----------------------------------------------------------------------

	public function test_asset_resolves_hashed_filename_from_real_manifest(): void {
		$manifest = $this->real_manifest();

		$this->assertSame(
			get_template_directory_uri() . '/assets/dist/' . $manifest['app.css'],
			salt_asset( 'app.css' )
		);
		$this->assertSame(
			get_template_directory_uri() . '/assets/dist/' . $manifest['app.js'],
			salt_asset( 'app.js' )
		);
		$this->assertSame( array(), $this->logs() );
	}

	public function test_asset_missing_key_returns_null_and_logs(): void {
		$this->assertNull( salt_asset( 'does-not-exist.js' ) );

		$logs = $this->logs();
		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( 'does-not-exist.js', $logs[0] );
	}

	public function test_asset_manifest_reset_restores_resolution(): void {
		$this->assertNotNull( salt_asset( 'app.css' ) );

		salt_asset_manifest_reset();

		$manifest = $this->real_manifest();
		$this->assertSame(
			get_template_directory_uri() . '/assets/dist/' . $manifest['app.css'],
			salt_asset( 'app.css' )
		);
	}

	// -----------------------------------------------------------------------
	// salt_enqueue_assets().
	// -----------------------------------------------------------------------

	public function test_enqueue_assets_registers_hashed_style_and_script_without_ver(): void {
		$manifest = $this->real_manifest();

		salt_enqueue_assets();

		$styles  = $GLOBALS['salt_test_enqueued']['styles'];
		$scripts = $GLOBALS['salt_test_enqueued']['scripts'];

		$this->assertArrayHasKey( 'salt-app', $styles );
		$this->assertArrayHasKey( 'salt-app', $scripts );

		$this->assertNull( $styles['salt-app']['ver'] );
		$this->assertNull( $scripts['salt-app']['ver'] );

		$this->assertStringEndsWith( '/assets/dist/' . $manifest['app.css'], $styles['salt-app']['src'] );
		$this->assertStringEndsWith( '/assets/dist/' . $manifest['app.js'], $scripts['salt-app']['src'] );

		$this->assertSame(
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			),
			$scripts['salt-app']['args']
		);

		$this->assertSame( array(), $this->logs() );
	}
}
