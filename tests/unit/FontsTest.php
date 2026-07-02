<?php
/**
 * Self-hosted fonts service (spec §13): upload mime gating, filetype fix,
 * the @font-face CSS builder's same-origin posture, the runtime seam and
 * the preload cap.
 *
 * The module under test is not in the foundation bootstrap yet, so this
 * file requires it directly (ABSPATH-guarded and idempotent).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/fonts.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/fonts.php';

final class FontsTest extends SaltTestCase {

	private const HOME = 'https://example.test';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_attachment_urls'] = array();
	}

	/**
	 * Build a normalized face fixture (salt_fonts_config() shape).
	 *
	 * @param array<string,mixed> $overrides Face overrides.
	 * @return array<string,mixed>
	 */
	private function face( array $overrides = array() ): array {
		return array_merge(
			array(
				'role'   => 'heading',
				'url'    => self::HOME . '/wp-content/uploads/heading-700.woff2',
				'format' => 'woff2',
				'weight' => 700,
				'style'  => 'normal',
			),
			$overrides
		);
	}

	/**
	 * Seed the font repeater options + attachment fixtures.
	 *
	 * @param array<int,array<string,mixed>> $heading Heading rows.
	 * @param array<int,array<string,mixed>> $body    Body rows.
	 * @param array<int,string>              $urls    Attachment id => URL map.
	 * @return void
	 */
	private function configure_fonts( array $heading, array $body, array $urls ): void {
		$GLOBALS['salt_test_fields']['option']['font_heading_files'] = $heading;
		$GLOBALS['salt_test_fields']['option']['font_body_files']    = $body;
		$GLOBALS['salt_test_attachment_urls']                        = $urls;
	}

	// -----------------------------------------------------------------
	// Upload mimes (capability-gated).
	// -----------------------------------------------------------------

	/**
	 * @return array<string,array{0:string[],1:bool}>
	 */
	public static function mime_capability_cases(): array {
		return array(
			'admin gets font mimes'       => array( array( 'manage_options' ), true ),
			'editor does not'             => array( array( 'edit_pages' ), false ),
			'logged-out visitor does not' => array( array(), false ),
		);
	}

	#[DataProvider( 'mime_capability_cases' )]
	public function test_font_mimes_gated_by_capability( array $caps, bool $expected ): void {
		$GLOBALS['salt_test_user']['caps'] = $caps;

		$mimes = salt_font_upload_mimes( array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );

		// Pre-existing entries always survive.
		$this->assertSame( 'image/jpeg', $mimes['jpg|jpeg|jpe'] );

		if ( $expected ) {
			$this->assertSame( 'font/woff2', $mimes['woff2'] );
			$this->assertSame( 'font/woff', $mimes['woff'] );
			$this->assertSame( 'font/ttf', $mimes['ttf'] );
		} else {
			$this->assertSame( array( 'jpg|jpeg|jpe' => 'image/jpeg' ), $mimes );
		}
	}

	// -----------------------------------------------------------------
	// Filetype fix (fileinfo sniffs fonts as octet-stream).
	// -----------------------------------------------------------------

	/**
	 * @return array<string,array{0:array<string,mixed>,1:string,2:array<string,mixed>}>
	 */
	public static function filetype_cases(): array {
		$empty = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		return array(
			'woff2 filled from extension'    => array(
				$empty,
				'brand.woff2',
				array(
					'ext'             => 'woff2',
					'type'            => 'font/woff2',
					'proper_filename' => false,
				),
			),
			'uppercase extension normalised' => array(
				$empty,
				'Brand.WOFF',
				array(
					'ext'             => 'woff',
					'type'            => 'font/woff',
					'proper_filename' => false,
				),
			),
			'ttf filled from extension'      => array(
				$empty,
				'brand.ttf',
				array(
					'ext'             => 'ttf',
					'type'            => 'font/ttf',
					'proper_filename' => false,
				),
			),
			'php left alone'                 => array( $empty, 'shell.php', $empty ),
			'svg left alone'                 => array( $empty, 'icon.svg', $empty ),
			'no extension left alone'        => array( $empty, 'mystery', $empty ),
			'resolved result untouched'      => array(
				array(
					'ext'             => 'woff2',
					'type'            => 'font/woff2',
					'proper_filename' => false,
				),
				'brand.woff2',
				array(
					'ext'             => 'woff2',
					'type'            => 'font/woff2',
					'proper_filename' => false,
				),
			),
		);
	}

	#[DataProvider( 'filetype_cases' )]
	public function test_filetype_fix( array $data, string $filename, array $expected ): void {
		$this->assertSame( $expected, salt_font_filetype_fix( $data, $filename ) );
	}

	public function test_filetype_filter_requires_manage_options(): void {
		$empty = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$GLOBALS['salt_test_user']['caps'] = array();
		$this->assertSame( $empty, salt_font_filetype_filter( $empty, '/tmp/php123', 'brand.woff2' ) );

		$GLOBALS['salt_test_user']['caps'] = array( 'manage_options' );
		$this->assertSame( 'font/woff2', salt_font_filetype_filter( $empty, '/tmp/php123', 'brand.woff2' )['type'] );
	}

	// -----------------------------------------------------------------
	// CSS builder (pure).
	// -----------------------------------------------------------------

	public function test_css_empty_faces_yield_empty_string(): void {
		$this->assertSame( '', salt_fonts_css( array(), 'woff2', self::HOME ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function format_order_cases(): array {
		return array(
			'woff2 preferred lists woff2 first' => array(
				'woff2',
				'src:url("https://example.test/wp-content/uploads/a.woff2") format("woff2"),url("https://example.test/wp-content/uploads/a.woff") format("woff")',
			),
			'woff preferred flips the order'    => array(
				'woff',
				'src:url("https://example.test/wp-content/uploads/a.woff") format("woff"),url("https://example.test/wp-content/uploads/a.woff2") format("woff2")',
			),
		);
	}

	#[DataProvider( 'format_order_cases' )]
	public function test_css_src_order_follows_preferred_format( string $preferred, string $expected_src ): void {
		$faces = array(
			$this->face( array( 'url' => self::HOME . '/wp-content/uploads/a.woff2' ) ),
			$this->face(
				array(
					'url'    => self::HOME . '/wp-content/uploads/a.woff',
					'format' => 'woff',
				)
			),
		);

		$css = salt_fonts_css( $faces, $preferred, self::HOME );

		$this->assertStringContainsString( $expected_src, $css );
		$this->assertSame( 1, substr_count( $css, '@font-face' ), 'Same role/weight/style groups into ONE rule.' );
	}

	public function test_css_every_rule_carries_font_display_swap(): void {
		$faces = array(
			$this->face(),
			$this->face( array( 'weight' => 400 ) ),
			$this->face(
				array(
					'role' => 'body',
					'url'  => self::HOME . '/wp-content/uploads/body.woff2',
				)
			),
		);

		$css = salt_fonts_css( $faces, 'woff2', self::HOME );

		$this->assertSame( 3, substr_count( $css, '@font-face' ) );
		$this->assertSame(
			substr_count( $css, '@font-face' ),
			substr_count( $css, 'font-display:swap' )
		);
	}

	public function test_css_ttf_uses_truetype_format_hint(): void {
		$css = salt_fonts_css(
			array(
				$this->face(
					array(
						'url'    => self::HOME . '/wp-content/uploads/a.ttf',
						'format' => 'ttf',
					)
				),
			),
			'woff2',
			self::HOME
		);

		$this->assertStringContainsString( 'format("truetype")', $css );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function hostile_url_cases(): array {
		return array(
			'cross-origin URL'      => array( 'https://fonts.evil.example/a.woff2' ),
			'origin prefix spoof'   => array( 'https://example.test.evil.example/a.woff2' ),
			'double-quote breakout' => array( self::HOME . '/uploads/a%22)%7D.woff2"' ),
			'single quote'          => array( self::HOME . "/uploads/a'.woff2" ),
			'backslash'             => array( self::HOME . '/uploads/a\\.woff2' ),
			'control character'     => array( self::HOME . "/uploads/a\x03.woff2" ),
		);
	}

	#[DataProvider( 'hostile_url_cases' )]
	public function test_css_rejects_hostile_urls_loudly( string $url ): void {
		$css = salt_fonts_css( array( $this->face( array( 'url' => $url ) ) ), 'woff2', self::HOME );

		$this->assertSame( '', $css, 'A rejected face must never leave a partial rule behind.' );
		$this->assertNotEmpty( $this->logs(), 'Rejection is logged, never silent.' );
	}

	public function test_css_hostile_face_skipped_but_clean_faces_survive(): void {
		$css = salt_fonts_css(
			array(
				$this->face( array( 'url' => 'https://fonts.evil.example/a.woff2' ) ),
				$this->face(
					array(
						'role' => 'body',
						'url'  => self::HOME . '/wp-content/uploads/body.woff2',
					)
				),
			),
			'woff2',
			self::HOME
		);

		$this->assertStringNotContainsString( 'evil.example', $css );
		$this->assertStringContainsString( 'salt-body', $css );
	}

	/**
	 * @return array<string,array{0:string[],1:bool,2:bool}>
	 */
	public static function role_token_cases(): array {
		return array(
			'heading only' => array( array( 'heading' ), true, false ),
			'body only'    => array( array( 'body' ), false, true ),
			'both roles'   => array( array( 'heading', 'body' ), true, true ),
		);
	}

	#[DataProvider( 'role_token_cases' )]
	public function test_css_role_tokens_only_for_present_roles( array $present, bool $heading, bool $body ): void {
		$faces = array();
		foreach ( $present as $role ) {
			$faces[] = $this->face(
				array(
					'role' => $role,
					'url'  => self::HOME . '/wp-content/uploads/' . $role . '.woff2',
				)
			);
		}

		$css = salt_fonts_css( $faces, 'woff2', self::HOME );

		$heading_token = ':root{--font-heading:"salt-heading",var(--font-sans)}';
		$body_token    = ':root{--font-body:"salt-body",var(--font-sans)}';

		$this->assertSame( $heading, str_contains( $css, $heading_token ) );
		$this->assertSame( $body, str_contains( $css, $body_token ) );
	}

	// -----------------------------------------------------------------
	// Options → faces (salt_fonts_config) + the runtime seam.
	// -----------------------------------------------------------------

	public function test_config_normalizes_rows_and_skips_broken_ids(): void {
		$this->configure_fonts(
			array(
				array(
					'file'   => 11,
					'weight' => '700',
					'style'  => 'normal',
				),
				array(
					'file'   => 99, // No attachment fixture — broken id.
					'weight' => '400',
					'style'  => 'normal',
				),
			),
			array(
				array(
					'file'   => 12,
					'weight' => '400',
					'style'  => 'italic',
				),
			),
			array(
				11 => self::HOME . '/wp-content/uploads/heading.woff2',
				12 => self::HOME . '/wp-content/uploads/body.ttf',
			)
		);

		$faces = salt_fonts_config();

		$this->assertSame(
			array(
				array(
					'role'   => 'heading',
					'url'    => self::HOME . '/wp-content/uploads/heading.woff2',
					'format' => 'woff2',
					'weight' => 700,
					'style'  => 'normal',
				),
				array(
					'role'   => 'body',
					'url'    => self::HOME . '/wp-content/uploads/body.ttf',
					'format' => 'ttf',
					'weight' => 400,
					'style'  => 'italic',
				),
			),
			$faces
		);
		$this->assertNotEmpty( $this->logs(), 'Broken attachment ids are logged.' );
	}

	public function test_config_skips_unsupported_extension_and_defaults_weight(): void {
		$this->configure_fonts(
			array(
				array(
					'file'   => 21,
					'weight' => 0, // Out of range — falls back to the role default.
					'style'  => 'normal',
				),
				array(
					'file'   => 22,
					'weight' => 700,
					'style'  => 'normal',
				),
			),
			array(),
			array(
				21 => self::HOME . '/wp-content/uploads/heading.woff2',
				22 => self::HOME . '/wp-content/uploads/heading.otf', // Unsupported.
			)
		);

		$faces = salt_fonts_config();

		$this->assertCount( 1, $faces );
		$this->assertSame( 700, $faces[0]['weight'], 'Heading role default weight applies.' );
		$this->assertNotEmpty( $this->logs() );
	}

	public function test_config_empty_when_nothing_uploaded(): void {
		$this->assertSame( array(), salt_fonts_config() );
	}

	public function test_runtime_css_empty_without_uploads(): void {
		$this->assertSame( '', salt_fonts_runtime_css() );
	}

	public function test_runtime_css_builds_from_options(): void {
		$this->configure_fonts(
			array(
				array(
					'file'   => 11,
					'weight' => 700,
					'style'  => 'normal',
				),
			),
			array(),
			array( 11 => self::HOME . '/wp-content/uploads/heading.woff2' )
		);
		$GLOBALS['salt_test_fields']['option']['font_format'] = 'woff2';

		$css = salt_fonts_runtime_css();

		$this->assertStringContainsString( '@font-face{font-family:"salt-heading"', $css );
		$this->assertStringContainsString( 'font-display:swap', $css );
		$this->assertStringContainsString( ':root{--font-heading:"salt-heading",var(--font-sans)}', $css );
	}

	// -----------------------------------------------------------------
	// Preload hints (wp_head 2).
	// -----------------------------------------------------------------

	/**
	 * Capture salt_output_font_preloads() output.
	 *
	 * @return string
	 */
	private function preload_output(): string {
		ob_start();
		salt_output_font_preloads();
		return (string) ob_get_clean();
	}

	public function test_preloads_skip_entirely_without_faces(): void {
		$this->assertSame( '', $this->preload_output() );
	}

	public function test_preloads_cap_at_two_woff2_body_first(): void {
		$this->configure_fonts(
			array(
				array(
					'file'   => 11,
					'weight' => 700,
					'style'  => 'normal',
				),
				array(
					'file'   => 12,
					'weight' => 400,
					'style'  => 'normal',
				),
			),
			array(
				array(
					'file'   => 13,
					'weight' => 400,
					'style'  => 'normal',
				),
				array(
					'file'   => 14,
					'weight' => 700,
					'style'  => 'normal',
				),
			),
			array(
				11 => self::HOME . '/wp-content/uploads/heading-700.woff2',
				12 => self::HOME . '/wp-content/uploads/heading-400.woff2',
				13 => self::HOME . '/wp-content/uploads/body-400.woff2',
				14 => self::HOME . '/wp-content/uploads/body-700.woff2',
			)
		);

		$html = $this->preload_output();

		$this->assertSame( 2, substr_count( $html, '<link rel="preload"' ), 'HARD CAP of two hints.' );
		$this->assertSame( 2, substr_count( $html, 'crossorigin' ) );
		$this->assertSame( 2, substr_count( $html, 'as="font" type="font/woff2"' ) );

		// First body face first, then first heading face; the rest never print.
		$body_pos    = strpos( $html, 'body-400.woff2' );
		$heading_pos = strpos( $html, 'heading-700.woff2' );
		$this->assertNotFalse( $body_pos );
		$this->assertNotFalse( $heading_pos );
		$this->assertLessThan( $heading_pos, $body_pos );
		$this->assertStringNotContainsString( 'heading-400.woff2', $html );
		$this->assertStringNotContainsString( 'body-700.woff2', $html );
	}

	public function test_preloads_ignore_non_woff2_faces(): void {
		$this->configure_fonts(
			array(
				array(
					'file'   => 11,
					'weight' => 700,
					'style'  => 'normal',
				),
			),
			array(
				array(
					'file'   => 12,
					'weight' => 400,
					'style'  => 'normal',
				),
			),
			array(
				11 => self::HOME . '/wp-content/uploads/heading.ttf',
				12 => self::HOME . '/wp-content/uploads/body.woff',
			)
		);

		$this->assertSame( '', $this->preload_output(), 'woff2 faces ONLY are preloaded.' );
	}

	public function test_preload_urls_are_escaped(): void {
		$this->configure_fonts(
			array(
				array(
					'file'   => 11,
					'weight' => 700,
					'style'  => 'normal',
				),
			),
			array(),
			array( 11 => self::HOME . '/wp-content/uploads/head"ing.woff2' )
		);

		$html = $this->preload_output();

		$this->assertStringContainsString( 'href="https://example.test/wp-content/uploads/head%22ing.woff2"', $html );
	}
}
