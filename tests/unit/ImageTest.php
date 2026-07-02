<?php
/**
 * Tests for inc/media.php + x-image behaviour (spec §8, AMBIG01, DATA05).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/media.php';
require_once dirname( __DIR__, 2 ) . '/inc/media.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Media helpers + x-image component test case.
 */
final class ImageTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_attachments_reset();
	}

	/**
	 * Render x-image with fixture args.
	 *
	 * @param array<string,mixed> $args Component args.
	 * @return string
	 */
	private function render_image( array $args ): string {
		return salt_get_component( 'image', $args );
	}

	/**
	 * Seed one attachment fixture.
	 *
	 * @param int                 $id    Attachment ID.
	 * @param array<string,mixed> $props Fixture overrides.
	 * @return void
	 */
	private function seed_attachment( int $id, array $props = array() ): void {
		$GLOBALS['salt_test_attachments'][ $id ] = array_merge(
			array(
				'src'    => 'https://example.test/uploads/fixture.jpg',
				'width'  => 1600,
				'height' => 900,
				'srcset' => 'https://example.test/uploads/fixture-800.jpg 800w, https://example.test/uploads/fixture.jpg 1600w',
				'alt'    => 'Stored fixture alt',
			),
			$props
		);
	}

	// -----------------------------------------------------------------------
	// salt_image_sizes().
	// -----------------------------------------------------------------------

	/**
	 * The frozen context => sizes map.
	 *
	 * @return array<string,array{string,string}>
	 */
	public static function sizes_map_provider(): array {
		return array(
			'hero'    => array( 'hero', '100vw' ),
			'full'    => array( 'full', '100vw' ),
			'half'    => array( 'half', '(min-width: 768px) 50vw, 100vw' ),
			'card'    => array( 'card', '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw' ),
			'gallery' => array( 'gallery', '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw' ),
		);
	}

	#[DataProvider( 'sizes_map_provider' )]
	public function test_sizes_map_is_frozen( string $context, string $expected ): void {
		$this->assertSame( $expected, salt_image_sizes( $context ) );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_sizes_unknown_context_degrades_to_100vw_and_logs(): void {
		$this->assertSame( '100vw', salt_image_sizes( 'bogus' ) );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'unknown context "bogus"', $this->logs()[0] );
	}

	// -----------------------------------------------------------------------
	// salt_image_data().
	// -----------------------------------------------------------------------

	public function test_image_data_resolves_attachment(): void {
		$this->seed_attachment( 12 );

		$this->assertSame(
			array(
				'src'    => 'https://example.test/uploads/fixture.jpg',
				'srcset' => 'https://example.test/uploads/fixture-800.jpg 800w, https://example.test/uploads/fixture.jpg 1600w',
				'sizes'  => '(min-width: 768px) 50vw, 100vw',
				'width'  => 1600,
				'height' => 900,
				'alt'    => 'Stored fixture alt',
			),
			salt_image_data( 12, 'half' )
		);
	}

	public function test_image_data_missing_attachment_returns_null_and_logs(): void {
		$this->assertNull( salt_image_data( 999, 'card' ) );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'attachment 999', $this->logs()[0] );
	}

	public function test_image_data_without_srcset_returns_empty_string(): void {
		$this->seed_attachment(
			5,
			array(
				'srcset' => '',
				'alt'    => '',
			)
		);

		$data = salt_image_data( 5, 'card' );
		$this->assertIsArray( $data );
		$this->assertSame( '', $data['srcset'] );
		$this->assertSame( '', $data['alt'] );
	}

	// -----------------------------------------------------------------------
	// x-image: default output shape (AMBIG01).
	// -----------------------------------------------------------------------

	public function test_default_output_is_a_plain_img_not_picture(): void {
		$html = $this->render_image(
			array(
				'src' => 'https://example.test/uploads/plain.jpg',
				'alt' => 'Plain image',
			)
		);

		$this->assertStringStartsWith( '<img ', $html );
		$this->assertStringNotContainsString( '<picture', $html );
		$this->assertStringNotContainsString( '<source', $html );
	}

	public function test_default_is_lazy_without_fetchpriority(): void {
		$html = $this->render_image(
			array(
				'src' => 'https://example.test/uploads/plain.jpg',
				'alt' => 'Plain image',
			)
		);

		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringContainsString( 'decoding="async"', $html );
		$this->assertStringNotContainsString( 'fetchpriority', $html );
	}

	public function test_priority_flips_to_eager_fetchpriority_high(): void {
		$html = $this->render_image(
			array(
				'src'      => 'https://example.test/uploads/lcp.jpg',
				'alt'      => 'LCP image',
				'priority' => true,
			)
		);

		$this->assertStringContainsString( 'loading="eager"', $html );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'decoding="async"', $html );
		$this->assertStringNotContainsString( 'loading="lazy"', $html );
	}

	// -----------------------------------------------------------------------
	// x-image: picture opt-in.
	// -----------------------------------------------------------------------

	public function test_picture_wraps_only_when_sources_given(): void {
		$html = $this->render_image(
			array(
				'src'             => 'https://example.test/uploads/photo.jpg',
				'alt'             => 'Photo',
				'picture_sources' => array(
					array(
						'srcset' => 'https://example.test/uploads/photo.avif',
						'type'   => 'image/avif',
					),
					array(
						'srcset' => 'https://example.test/uploads/photo.webp',
						'type'   => 'image/webp',
						'media'  => '(min-width: 768px)',
					),
				),
			)
		);

		$this->assertStringStartsWith( '<picture>', $html );
		$this->assertStringEndsWith( '</picture>', $html );
		$this->assertSame( 2, substr_count( $html, '<source ' ) );
		$this->assertStringContainsString( 'type="image/avif"', $html );
		$this->assertStringContainsString( 'media="(min-width: 768px)"', $html );
		$this->assertStringContainsString( '<img src=', $html );
	}

	public function test_picture_source_without_srcset_is_skipped_and_logged(): void {
		$html = $this->render_image(
			array(
				'src'             => 'https://example.test/uploads/photo.jpg',
				'alt'             => 'Photo',
				'picture_sources' => array(
					array( 'type' => 'image/avif' ),
					array( 'srcset' => 'https://example.test/uploads/photo.webp' ),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, '<source ' ) );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'without a srcset', $this->logs()[0] );
	}

	// -----------------------------------------------------------------------
	// x-image: attachment resolution + failure paths.
	// -----------------------------------------------------------------------

	public function test_image_id_resolves_src_srcset_sizes_and_dimensions(): void {
		$this->seed_attachment( 42 );

		$html = $this->render_image(
			array(
				'image_id'      => 42,
				'alt'           => 'Resolved image',
				'sizes_context' => 'card',
			)
		);

		$this->assertStringContainsString( 'src="https://example.test/uploads/fixture.jpg"', $html );
		$this->assertStringContainsString( 'fixture-800.jpg 800w', $html );
		$this->assertStringContainsString( 'sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"', $html );
		$this->assertStringContainsString( 'width="1600"', $html );
		$this->assertStringContainsString( 'height="900"', $html );
	}

	public function test_missing_attachment_renders_only_a_comment_and_logs(): void {
		$html = $this->render_image(
			array(
				'image_id' => 999,
				'alt'      => 'Whatever',
			)
		);

		$this->assertSame( '<!-- salt: x-image missing attachment -->', $html );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'attachment 999', $this->logs()[0] );
	}

	public function test_no_image_id_and_empty_src_renders_nothing_and_logs(): void {
		$html = $this->render_image( array( 'alt' => 'Nothing to show' ) );

		$this->assertSame( '', $html );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'empty src', $this->logs()[0] );
	}

	// -----------------------------------------------------------------------
	// x-image: alt handling.
	// -----------------------------------------------------------------------

	public function test_alt_attribute_is_always_present_even_when_decorative(): void {
		$html = $this->render_image(
			array(
				'src' => 'https://example.test/uploads/decorative.jpg',
				'alt' => '',
			)
		);

		$this->assertStringContainsString( 'alt=""', $html );
	}

	public function test_explicit_alt_wins_over_resolved_alt(): void {
		$this->seed_attachment( 42 );

		$html = $this->render_image(
			array(
				'image_id' => 42,
				'alt'      => 'Explicit alt',
			)
		);

		$this->assertStringContainsString( 'alt="Explicit alt"', $html );
		$this->assertStringNotContainsString( 'Stored fixture alt', $html );
	}

	public function test_empty_alt_arg_falls_back_to_resolved_alt(): void {
		$this->seed_attachment( 42 );

		$html = $this->render_image(
			array(
				'image_id' => 42,
				'alt'      => '',
			)
		);

		$this->assertStringContainsString( 'alt="Stored fixture alt"', $html );
	}

	public function test_empty_alt_arg_and_empty_resolved_alt_is_decorative(): void {
		$this->seed_attachment( 42, array( 'alt' => '' ) );

		$html = $this->render_image(
			array(
				'image_id' => 42,
				'alt'      => '',
			)
		);

		$this->assertStringContainsString( 'alt=""', $html );
	}

	// -----------------------------------------------------------------------
	// x-image: srcset / sizes coupling + overrides.
	// -----------------------------------------------------------------------

	public function test_sizes_emitted_only_when_srcset_present(): void {
		$without_srcset = $this->render_image(
			array(
				'src' => 'https://example.test/uploads/single.jpg',
				'alt' => 'Single file',
			)
		);
		$this->assertStringNotContainsString( ' srcset=', $without_srcset );
		$this->assertStringNotContainsString( ' sizes=', $without_srcset );

		$with_srcset = $this->render_image(
			array(
				'src'           => 'https://example.test/uploads/multi.jpg',
				'srcset'        => 'https://example.test/uploads/multi-400.jpg 400w',
				'sizes_context' => 'hero',
				'alt'           => 'Multi file',
			)
		);
		$this->assertStringContainsString( 'srcset="https://example.test/uploads/multi-400.jpg 400w"', $with_srcset );
		$this->assertStringContainsString( 'sizes="100vw"', $with_srcset );
	}

	public function test_explicit_dimensions_override_resolved_ones(): void {
		$this->seed_attachment( 42 );

		$html = $this->render_image(
			array(
				'image_id' => 42,
				'alt'      => 'Cropped rendition',
				'width'    => 400,
				'height'   => 300,
			)
		);

		$this->assertStringContainsString( 'width="400"', $html );
		$this->assertStringContainsString( 'height="300"', $html );
	}

	public function test_zero_dimensions_are_omitted(): void {
		$html = $this->render_image(
			array(
				'src' => 'https://example.test/uploads/unknown-size.jpg',
				'alt' => 'Unknown size',
			)
		);

		$this->assertStringNotContainsString( ' width=', $html );
		$this->assertStringNotContainsString( ' height=', $html );
	}

	public function test_classes_are_appended_to_x_image(): void {
		$html = $this->render_image(
			array(
				'src'     => 'https://example.test/uploads/classy.jpg',
				'alt'     => 'Classy',
				'classes' => 'rounded-lg border-border',
			)
		);

		$this->assertStringContainsString( 'class="x-image rounded-lg border-border"', $html );
	}

	public function test_missing_alt_arg_throws_under_debug(): void {
		$level = ob_get_level();
		try {
			$this->render_image( array( 'src' => 'https://example.test/uploads/no-alt.jpg' ) );
			$this->fail( 'Expected InvalidArgumentException for the missing required alt arg.' );
		} catch ( InvalidArgumentException $e ) {
			// The message is esc_html'd by salt_require_args().
			$this->assertStringContainsString( 'required arg &quot;alt&quot; missing', $e->getMessage() );
		} finally {
			// salt_get_component() output-buffers; the throw skips its ob_get_clean().
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
	}
}
