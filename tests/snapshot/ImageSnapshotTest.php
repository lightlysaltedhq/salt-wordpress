<?php
/**
 * x-image snapshots (FLEET01).
 *
 * x-image is the single authority for image markup (AMBIG01) — every image
 * on every fleet site renders through it. Structural change here fails CI
 * and forces the semver decision.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/media.php';
require_once dirname( __DIR__, 2 ) . '/inc/media.php';

/**
 * x-image snapshot case.
 */
final class ImageSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_attachments_reset();
	}

	public function test_image_default_card(): void {
		$this->assertMatchesHtmlSnapshot(
			'image-default-card',
			salt_get_component(
				'image',
				array(
					'src'    => 'https://example.test/uploads/team-photo.jpg',
					'srcset' => 'https://example.test/uploads/team-photo-400.jpg 400w, https://example.test/uploads/team-photo-800.jpg 800w',
					'alt'    => 'The team at the summer offsite',
					'width'  => 800,
					'height' => 600,
				)
			)
		);
	}

	public function test_image_priority_hero(): void {
		$GLOBALS['salt_test_attachments'][7] = array(
			'src'    => 'https://example.test/uploads/hero.jpg',
			'width'  => 2400,
			'height' => 1350,
			'srcset' => 'https://example.test/uploads/hero-1200.jpg 1200w, https://example.test/uploads/hero.jpg 2400w',
			'alt'    => 'Sunrise over the harbour',
		);

		$this->assertMatchesHtmlSnapshot(
			'image-priority-hero',
			salt_get_component(
				'image',
				array(
					'image_id'      => 7,
					'sizes_context' => 'hero',
					'alt'           => '',
					'priority'      => true,
					'classes'       => 'hero-media',
				)
			)
		);
	}

	public function test_image_picture_dual_format(): void {
		$this->assertMatchesHtmlSnapshot(
			'image-picture-dual-format',
			salt_get_component(
				'image',
				array(
					'src'             => 'https://example.test/uploads/product.jpg',
					'srcset'          => 'https://example.test/uploads/product-600.jpg 600w, https://example.test/uploads/product-1200.jpg 1200w',
					'sizes_context'   => 'half',
					'alt'             => 'Product on a plain background',
					'width'           => 1200,
					'height'          => 800,
					'picture_sources' => array(
						array(
							'srcset' => 'https://example.test/uploads/product-600.avif 600w, https://example.test/uploads/product-1200.avif 1200w',
							'type'   => 'image/avif',
						),
						array(
							'srcset' => 'https://example.test/uploads/product-600.webp 600w, https://example.test/uploads/product-1200.webp 1200w',
							'type'   => 'image/webp',
						),
					),
				)
			)
		);
	}
}
