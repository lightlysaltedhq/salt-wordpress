<?php
/**
 * Gallery section snapshots (FLEET01) — one per meaningful variant: grid
 * with captions, pure-CSS masonry, the no-JS carousel snap track, and the
 * single-image static state (carousel layout, no JS trigger).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/gallery.php';

final class GallerySectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();

		foreach ( array( 71, 72, 73 ) as $i => $id ) {
			$GLOBALS['salt_test_attachments'][ $id ] = array(
				'src'     => 'https://example.test/uploads/shot-' . $id . '.jpg',
				'width'   => 1200,
				'height'  => 800,
				'srcset'  => 'https://example.test/uploads/shot-' . $id . '-600.jpg 600w, https://example.test/uploads/shot-' . $id . '.jpg 1200w',
				'alt'     => 'Project shot ' . ( $i + 1 ),
				'caption' => 71 === $id ? 'Launch day, 2026' : '',
			);
		}
	}

	/**
	 * Seed the FC row and render the gallery section.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_gallery( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'gallery',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'gallery',
			array(
				'index'      => 0,
				'is_primary' => false,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_gallery_grid_three_columns_captions(): void {
		$this->assertMatchesHtmlSnapshot(
			'gallery-grid-three-captions',
			$this->render_gallery(
				array(
					'layout'   => 'grid',
					'columns'  => 3,
					'captions' => 1,
					'images'   => array( 71, 72, 73 ),
				)
			)
		);
	}

	public function test_gallery_masonry_two_columns(): void {
		$this->assertMatchesHtmlSnapshot(
			'gallery-masonry-two-columns',
			$this->render_gallery(
				array(
					'layout'   => 'masonry',
					'columns'  => 2,
					'captions' => 0,
					'images'   => array( 71, 72, 73 ),
				)
			)
		);
	}

	public function test_gallery_carousel_snap_track(): void {
		$this->assertMatchesHtmlSnapshot(
			'gallery-carousel-track',
			$this->render_gallery(
				array(
					'layout'   => 'carousel',
					'label'    => 'Studio photographs',
					'captions' => 0,
					'images'   => array( 71, 72, 73 ),
				)
			)
		);
	}

	public function test_gallery_single_image_static(): void {
		$this->assertMatchesHtmlSnapshot(
			'gallery-single-image',
			$this->render_gallery(
				array(
					'layout'   => 'carousel',
					'captions' => 1,
					'images'   => array( 71 ),
				)
			)
		);
	}
}
