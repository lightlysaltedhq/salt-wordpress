<?php
/**
 * Tests for inc/media.php: the contract's image data (spec §8).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/media.php';
require_once dirname( __DIR__, 2 ) . '/inc/media.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Media helpers test case.
 */
final class ImageTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_attachments_reset();
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
	// salt_image_focal_point() (EP-72 M4 U3).
	// -----------------------------------------------------------------------

	/**
	 * Stored _salt_focal_point values and the focal point they give: each
	 * axis snapped to a step of 5, halves up, and held to 0-100
	 * (section#background-position).
	 *
	 * @return array<string, array{0: mixed, 1: array{x: int, y: int}|null}>
	 */
	public static function focal_points(): array {
		$point = static fn( $x, $y ): array => array(
			'x' => $x,
			'y' => $y,
		);
		return array(
			'steps stay as they are'    => array( $point( 30, 60 ), $point( 30, 60 ) ),
			'fractions snap, halves up' => array( $point( 32.5, 2.4 ), $point( 35, 0 ) ),
			'just under a half step'    => array( $point( 32.49, 97.5 ), $point( 30, 100 ) ),
			'numeric strings'           => array( $point( '0', '42.5' ), $point( 0, 45 ) ),
			'clamped to 0-100'          => array( $point( -3, 140 ), $point( 0, 100 ) ),
			'NaN is refused'            => array( $point( NAN, 50 ), null ),
			'infinity is refused'       => array( $point( 50, INF ), null ),
			'a non-numeric string'      => array( $point( 'left', 50 ), null ),
			'an empty string'           => array( $point( '', 50 ), null ),
			'a boolean is refused'      => array( $point( true, 60 ), null ),
			'one value missing'         => array( array( 'x' => 30 ), null ),
			'not a pair'                => array( '30% 60%', null ),
			'nothing stored'            => array( '', null ),
		);
	}

	/**
	 * @param mixed                      $stored   The meta value.
	 * @param array{x: int, y: int}|null $expected The focal point.
	 */
	#[DataProvider( 'focal_points' )]
	public function test_focal_point_is_snapped_to_steps_of_five_or_none( $stored, ?array $expected ): void {
		$this->seed_attachment( 12 );
		$GLOBALS['salt_test_meta'][12]['_salt_focal_point'] = $stored;
		try {
			$this->assertSame( $expected, salt_image_focal_point( 12 ) );
			$this->assertSame( $expected, salt_image_contract( 12, '100vw' )['focalPoint'] );
		} finally {
			unset( $GLOBALS['salt_test_meta'][12] );
		}
	}

	// -----------------------------------------------------------------------
	// salt_image_contract_srcset(): an upload made before the salt-<w> sizes.
	// -----------------------------------------------------------------------

	/**
	 * A 2000 x 1200 upload with core's sub-sizes only (made before the theme
	 * registered its candidates), the cropped thumbnail among them.
	 *
	 * @param array<string, array<string,mixed>> $extra Further sub-sizes.
	 * @return void
	 */
	private function seed_core_sized_upload( array $extra = array() ): void {
		$size                                 = static fn( int $w, int $h ): array => array(
			'width'  => $w,
			'height' => $h,
			'url'    => "https://example.test/uploads/old-{$w}x{$h}.jpg",
		);
		$GLOBALS['salt_test_attachments'][60] = array(
			'src'    => 'https://example.test/uploads/old.jpg',
			'width'  => 2000,
			'height' => 1200,
			'alt'    => '',
			'sizes'  => array(
				'thumbnail'    => $size( 150, 150 ),
				'large'        => $size( 1024, 614 ),
				'medium'       => $size( 300, 180 ),
				'medium_large' => $size( 768, 461 ),
				'1536x1536'    => $size( 1536, 922 ),
			) + $extra,
		);
	}

	public function test_an_upload_with_core_sizes_only_lists_them_in_its_srcset(): void {
		$this->seed_core_sized_upload();

		// Review #3: ascending, same proportions only (no square thumbnail),
		// then the upload.
		$this->assertSame(
			'https://example.test/uploads/old-300x180.jpg 300w, https://example.test/uploads/old-768x461.jpg 768w, https://example.test/uploads/old-1024x614.jpg 1024w, https://example.test/uploads/old-1536x922.jpg 1536w, https://example.test/uploads/old.jpg 2000w',
			salt_image_contract( 60, salt_image_slot_sizes( 'content', 'default' ) )['srcset']
		);
		// The slot's floor still applies: 100vw drops widths under 640.
		$this->assertSame(
			'https://example.test/uploads/old-768x461.jpg 768w, https://example.test/uploads/old-1024x614.jpg 1024w, https://example.test/uploads/old-1536x922.jpg 1536w, https://example.test/uploads/old.jpg 2000w',
			salt_image_contract( 60, '100vw' )['srcset']
		);
	}

	public function test_an_upload_with_the_candidate_sizes_lists_only_those(): void {
		$this->seed_core_sized_upload(
			array(
				'salt-640' => array(
					'width'  => 640,
					'height' => 384,
					'url'    => 'https://example.test/uploads/old-640.jpg',
				),
			)
		);

		$this->assertSame(
			'https://example.test/uploads/old-640.jpg 640w, https://example.test/uploads/old.jpg 2000w',
			salt_image_contract( 60, '100vw' )['srcset']
		);
	}
}
