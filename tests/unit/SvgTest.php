<?php
/**
 * SVG services tests (SEC04).
 *
 * Proves the four security properties of the module: hostile markup dies in
 * salt_sanitize_svg(), hostile markup cannot survive THROUGH x-icon, upload
 * filters sanitise/reject in place and only admins get the mime type, and
 * every bundled icon is loadable, sanitiser-clean and currentColor-driven.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname( __DIR__, 2 ) . '/inc/services/svg.php';

final class SvgTest extends SaltTestCase {

	private const MALICIOUS_SVG = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
		. '<script>alert(1)</script>'
		. '<foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><img src="x" onerror="alert(1)"/></body></foreignObject>'
		. '<use xlink:href="https://evil.example/sprite.svg#x"/>'
		. '<path d="M4 12h16"/>'
		. '</svg>';

	protected function setUp(): void {
		parent::setUp();
		salt_icon_cache_reset();
	}

	// -----------------------------------------------------------------------
	// salt_sanitize_svg().
	// -----------------------------------------------------------------------

	public function test_sanitize_strips_attack_vectors_but_keeps_paths(): void {
		$clean = salt_sanitize_svg( self::MALICIOUS_SVG );

		$this->assertNotSame( '', $clean );
		$this->assertStringNotContainsString( '<script', $clean );
		$this->assertStringNotContainsString( 'onload', $clean );
		$this->assertStringNotContainsString( 'onerror', $clean );
		$this->assertStringNotContainsString( 'foreignObject', $clean );
		$this->assertStringNotContainsString( 'evil.example', $clean );
		$this->assertStringContainsString( '<path', $clean );
	}

	public function test_sanitize_empty_input_returns_empty(): void {
		$this->assertSame( '', salt_sanitize_svg( '' ) );
		$this->assertSame( '', salt_sanitize_svg( "  \n\t " ) );
	}

	public function test_sanitize_unparseable_input_returns_empty_and_logs(): void {
		$this->assertSame( '', salt_sanitize_svg( 'this is not markup at all <<<' ) );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'salt_sanitize_svg', implode( "\n", $this->logs() ) );
	}

	// -----------------------------------------------------------------------
	// x-icon integration: hostile svg arg cannot survive to output.
	// -----------------------------------------------------------------------

	public function test_malicious_svg_arg_cannot_survive_through_component(): void {
		$html = salt_get_component(
			'icon',
			array(
				'svg'        => self::MALICIOUS_SVG,
				'decorative' => true,
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onload', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
		$this->assertStringNotContainsString( 'foreignObject', $html );
		$this->assertStringNotContainsString( 'evil.example', $html );
		$this->assertStringContainsString( '<path', $html );
		$this->assertStringContainsString( 'x-icon', $html );
	}

	public function test_component_with_no_name_and_no_svg_renders_nothing(): void {
		$this->assertSame( '', trim( salt_get_component( 'icon', array() ) ) );
	}

	// -----------------------------------------------------------------------
	// salt_icon_svg().
	// -----------------------------------------------------------------------

	public function test_unknown_icon_name_returns_empty_and_logs(): void {
		$this->assertSame( '', salt_icon_svg( 'no-such-icon' ) );
		$this->assertStringContainsString( 'unknown icon "no-such-icon"', implode( "\n", $this->logs() ) );
	}

	public function test_icon_name_is_sanitized_so_traversal_cannot_escape(): void {
		$this->assertSame( '', salt_icon_svg( '../../wp-config' ) );
		$this->assertSame( '', salt_icon_svg( '' ) );
	}

	public function test_icon_cache_serves_repeat_lookups(): void {
		$first  = salt_icon_svg( 'check' );
		$second = salt_icon_svg( 'check' );

		$this->assertNotSame( '', $first );
		$this->assertSame( $first, $second );
		$this->assertArrayHasKey( 'check', salt_icon_store() );
	}

	/**
	 * Every bundled icon loads, sanitises to non-empty, uses currentColor.
	 *
	 * @param string $name Icon name.
	 */
	#[DataProvider( 'provide_bundled_icons' )]
	public function test_bundled_icon_loads_and_survives_sanitiser( string $name ): void {
		$svg = salt_icon_svg( $name );

		$this->assertNotSame( '', $svg, "icon {$name} failed to load" );
		$this->assertMatchesRegularExpression( '/(stroke|fill)="currentColor"/', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $svg );
		$this->assertNotSame( '', salt_sanitize_svg( $svg ), "icon {$name} was rejected by the sanitiser" );
	}

	/**
	 * Bundled icon names (mirrors assets/src/icons/*.svg).
	 *
	 * @return array<string,array{string}>
	 */
	public static function provide_bundled_icons(): array {
		$files = glob( dirname( __DIR__, 2 ) . '/assets/src/icons/*.svg' );
		$files = false === $files ? array() : $files;
		$cases = array();
		foreach ( $files as $file ) {
			$name           = basename( $file, '.svg' );
			$cases[ $name ] = array( $name );
		}
		return $cases;
	}

	public function test_full_icon_set_is_bundled(): void {
		$expected = array( 'arrow-right', 'bluesky', 'check', 'chevron-down', 'clock', 'close', 'facebook', 'github', 'globe', 'instagram', 'link', 'linkedin', 'mail', 'map-pin', 'menu', 'moon', 'phone', 'pinterest', 'reddit', 'star', 'star-filled', 'sun', 'tiktok', 'whatsapp', 'x', 'youtube' );
		$found    = array_keys( self::provide_bundled_icons() );
		sort( $found );

		$this->assertSame( $expected, $found );
	}

	// -----------------------------------------------------------------------
	// Upload filters.
	// -----------------------------------------------------------------------

	public function test_upload_filters_register_both_hooks(): void {
		salt_svg_upload_filters();

		$this->assertArrayHasKey( 'upload_mimes', $GLOBALS['salt_test_hooks'] );
		$this->assertArrayHasKey( 'wp_handle_upload_prefilter', $GLOBALS['salt_test_hooks'] );
	}

	public function test_upload_mimes_adds_svg_for_admins_only(): void {
		$GLOBALS['salt_test_user']['caps'] = array();
		$this->assertArrayNotHasKey( 'svg', salt_svg_upload_mimes( array() ) );

		$GLOBALS['salt_test_user']['caps'] = array( 'manage_options' );
		$this->assertSame( array( 'svg' => 'image/svg+xml' ), salt_svg_upload_mimes( array() ) );
	}

	public function test_prefilter_ignores_non_svg_uploads(): void {
		$file = array(
			'name'     => 'photo.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/nonexistent/never-read',
		);

		$this->assertSame( $file, salt_svg_upload_prefilter( $file ) );
	}

	public function test_prefilter_sanitises_svg_file_in_place(): void {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'salt-svg-' );
		file_put_contents( $tmp, self::MALICIOUS_SVG );

		$result = salt_svg_upload_prefilter(
			array(
				'name'     => 'upload.svg',
				'type'     => 'image/svg+xml',
				'tmp_name' => $tmp,
			)
		);

		$rewritten = (string) file_get_contents( $tmp );
		unlink( $tmp );

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertStringNotContainsString( '<script', $rewritten );
		$this->assertStringNotContainsString( 'onload', $rewritten );
		$this->assertStringContainsString( '<path', $rewritten );
	}

	public function test_prefilter_rejects_unsanitisable_svg(): void {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'salt-svg-' );
		file_put_contents( $tmp, 'not svg <<<' );

		$result = salt_svg_upload_prefilter(
			array(
				'name'     => 'bad.svg',
				'type'     => 'image/svg+xml',
				'tmp_name' => $tmp,
			)
		);
		unlink( $tmp );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertNotSame( '', $result['error'] );
		$this->assertStringContainsString( 'rejected unsanitisable SVG upload', implode( "\n", $this->logs() ) );
	}
}
