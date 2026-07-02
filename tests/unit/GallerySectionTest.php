<?php
/**
 * Gallery slice tests (spec §10 #16; COMPL02, FAIL02, DATA02, DATA05).
 *
 * Covers the FC layout shape, the 0/1/N image states (1 image in ANY layout
 * — including carousel — renders one static figure with no JS trigger),
 * broken-ID skipping, the pure-CSS masonry (no JS hook), the no-JS carousel
 * base (no buttons in PHP markup), captions from the attachment caption,
 * and that the section queues no schema nodes and never claims the H1.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/gallery.php';

final class GallerySectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Seed the FC row and render the gallery section.
	 *
	 * @param array<string,mixed> $fields     Row sub-field fixtures.
	 * @param bool                $is_primary Loop context flag.
	 * @return string Rendered HTML.
	 */
	private function render_gallery( array $fields = array(), bool $is_primary = false ): string {
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
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Seed resolvable attachment fixtures 41..(40+$count), optional captions.
	 *
	 * @param int                    $count    How many attachments.
	 * @param array<int,string> $captions Captions keyed by attachment ID.
	 * @return int[] The seeded IDs.
	 */
	private function seed_attachments( int $count, array $captions = array() ): array {
		$ids = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$id                                      = 40 + $i;
			$GLOBALS['salt_test_attachments'][ $id ] = array(
				'src'     => 'https://example.test/uploads/gallery-' . $id . '.jpg',
				'width'   => 1200,
				'height'  => 800,
				'srcset'  => 'https://example.test/uploads/gallery-' . $id . '-600.jpg 600w, https://example.test/uploads/gallery-' . $id . '.jpg 1200w',
				'alt'     => 'Gallery image ' . $id,
				'caption' => $captions[ $id ] ?? '',
			);
			$ids[]                                   = $id;
		}
		return $ids;
	}

	// -----------------------------------------------------------------
	// FC layout shape (§17 recipe, DATA02/FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/gallery.php';

		$this->assertSame( 'gallery', $layout['name'] );
		$this->assertSame( 'layout_salt_gallery', $layout['key'] );

		$keys   = array_column( $layout['sub_fields'], 'key' );
		$fields = array();
		foreach ( $layout['sub_fields'] as $field ) {
			if ( isset( $field['name'] ) ) {
				$fields[ $field['name'] ] = $field;
			}
		}

		// Content fields first (DATA02 key convention).
		$this->assertSame( 'field_salt_gallery_layout', $keys[0] );
		foreach ( array( 'layout', 'images', 'columns', 'captions' ) as $name ) {
			$this->assertSame( 'field_salt_gallery_' . $name, $fields[ $name ]['key'], 'DATA02 key convention.' );
		}
		$this->assertSame( array( 'grid', 'masonry', 'carousel' ), array_keys( $fields['layout']['choices'] ) );
		$this->assertSame( 'gallery', $fields['images']['type'] );
		$this->assertSame( 'id', $fields['images']['return_format'] );
		$this->assertSame( array( 2, 3, 4 ), array_keys( $fields['columns']['choices'] ) );
		$this->assertSame( 'true_false', $fields['captions']['type'] );

		// No heading field — the section never claims the H1 (A11Y01).
		$this->assertArrayNotHasKey( 'heading', $fields );

		// Wrapper fields LAST (§11 Advanced tab).
		$this->assertContains( 'field_salt_gallery_advanced_tab', $keys );
		$this->assertSame( 'field_salt_gallery_section_divider', end( $keys ) );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_images_renders_nothing(): void {
		$this->assertSame( '', $this->render_gallery() );
		$this->assertSame( '', $this->render_gallery( array( 'images' => array() ) ) );
	}

	public function test_all_broken_ids_renders_nothing_and_logs(): void {
		$html = $this->render_gallery( array( 'images' => array( 998, 999 ) ) );

		$this->assertSame( '', $html );
		$this->assertStringContainsString( 'could not be resolved', implode( ' ', $this->logs() ), 'DATA05 broken-attachment log.' );
	}

	public function test_malformed_entries_skipped_valid_images_render(): void {
		$ids = $this->seed_attachments( 2 );

		$html = $this->render_gallery(
			array(
				'layout' => 'grid',
				'images' => array( 'not-an-id', 0, $ids[0], 999, $ids[1] ),
			)
		);

		$this->assertSame( 2, substr_count( $html, '<figure' ), 'Only the two resolvable images render.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'malformed image entry', $logs );
		$this->assertStringContainsString( 'could not be resolved', $logs );
	}

	public function test_single_image_in_carousel_layout_is_one_static_figure_with_no_js_trigger(): void {
		$this->seed_attachments( 1 );

		$html = $this->render_gallery(
			array(
				'layout' => 'carousel',
				'images' => array( 41 ),
			)
		);

		$this->assertStringContainsString( 'x-gallery-single', $html );
		$this->assertSame( 1, substr_count( $html, '<figure' ) );
		$this->assertStringNotContainsString( 'data-salt-carousel', $html, '1 image: no JS trigger attribute (FAIL02).' );
		$this->assertStringNotContainsString( 'snap-x', $html );
		$this->assertStringNotContainsString( '<button', $html );
		$this->assertStringContainsString( 'sizes="100vw"', $html, "The single figure uses the 'full' sizes context." );
	}

	public function test_single_image_in_grid_layout_is_also_the_static_figure(): void {
		$this->seed_attachments( 1 );

		$html = $this->render_gallery(
			array(
				'layout' => 'grid',
				'images' => array( 41 ),
			)
		);

		$this->assertStringContainsString( 'x-gallery-single', $html );
		$this->assertStringNotContainsString( 'x-gallery-grid', $html );
	}

	// -----------------------------------------------------------------
	// Layouts (N state).
	// -----------------------------------------------------------------

	public function test_grid_layout_renders_css_grid_with_columns(): void {
		$ids = $this->seed_attachments( 3 );

		$html = $this->render_gallery(
			array(
				'layout'  => 'grid',
				'columns' => 3,
				'images'  => $ids,
			)
		);

		$this->assertStringContainsString( 'x-gallery-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-3', $html );
		$this->assertSame( 3, substr_count( $html, '<figure' ) );
		$this->assertStringNotContainsString( 'data-salt-carousel', $html );
		$this->assertSame( 3, substr_count( $html, 'sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"' ), "x-image 'gallery' sizes context." );
	}

	public function test_grid_columns_four(): void {
		$ids = $this->seed_attachments( 4 );

		$html = $this->render_gallery(
			array(
				'layout'  => 'grid',
				'columns' => 4,
				'images'  => $ids,
			)
		);

		$this->assertStringContainsString( 'lg:grid-cols-4', $html );
	}

	public function test_invalid_columns_falls_back_to_three_and_logs(): void {
		$ids = $this->seed_attachments( 2 );

		$html = $this->render_gallery(
			array(
				'layout'  => 'grid',
				'columns' => 7,
				'images'  => $ids,
			)
		);

		$this->assertStringContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringContainsString( 'invalid columns', implode( ' ', $this->logs() ) );
	}

	public function test_masonry_is_pure_css_with_no_js_hook(): void {
		$ids = $this->seed_attachments( 3 );

		$html = $this->render_gallery(
			array(
				'layout'  => 'masonry',
				'columns' => 3,
				'images'  => $ids,
			)
		);

		$this->assertStringContainsString( 'x-gallery-masonry gap-4 columns-2 lg:columns-3', $html );
		$this->assertSame( 3, substr_count( $html, 'break-inside-avoid' ) );
		$this->assertStringNotContainsString( 'data-salt-carousel', $html, 'Masonry has NO JS hook — pure CSS columns.' );
		$this->assertStringNotContainsString( 'data-salt-', $html );
		$this->assertStringNotContainsString( '<button', $html );
	}

	public function test_carousel_base_is_a_functional_snap_track_with_no_buttons(): void {
		$ids = $this->seed_attachments( 3 );

		$html = $this->render_gallery(
			array(
				'layout' => 'carousel',
				'images' => $ids,
			)
		);

		$this->assertStringContainsString( 'data-salt-carousel', $html );
		$this->assertStringContainsString( 'snap-x', $html );
		$this->assertStringContainsString( 'overflow-x-auto', $html );
		$this->assertSame( 3, substr_count( $html, 'snap-start' ) );
		$this->assertStringContainsString( 'role="region"', $html );
		$this->assertStringContainsString( 'tabindex="0"', $html );
		$this->assertStringContainsString( 'aria-label="Image gallery"', $html );
		$this->assertStringContainsString( 'data-salt-prev-label="Previous images"', $html );
		$this->assertStringContainsString( 'data-salt-next-label="Next images"', $html );
		$this->assertStringNotContainsString( '<button', $html, 'FAIL02: buttons are JS-injected only — no dead buttons no-JS.' );
	}

	public function test_invalid_layout_falls_back_to_grid_and_logs(): void {
		$ids = $this->seed_attachments( 2 );

		$html = $this->render_gallery(
			array(
				'layout' => 'mosaic',
				'images' => $ids,
			)
		);

		$this->assertStringContainsString( 'x-gallery-grid', $html );
		$this->assertStringContainsString( 'invalid layout', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Captions (<figure>/<figcaption> rendered directly — no x-figure).
	// -----------------------------------------------------------------

	public function test_captions_on_renders_figcaption_from_attachment_caption(): void {
		$ids = $this->seed_attachments(
			2,
			array( 41 => 'Sunset over the harbour' )
		);

		$html = $this->render_gallery(
			array(
				'layout'   => 'grid',
				'captions' => 1,
				'images'   => $ids,
			)
		);

		$this->assertSame( 1, substr_count( $html, '<figcaption' ), 'Only the image WITH a caption gets a figcaption.' );
		$this->assertStringContainsString( '<figcaption class="mt-2 text-sm text-ink-muted">Sunset over the harbour</figcaption>', $html );
	}

	public function test_captions_off_renders_no_figcaption(): void {
		$ids = $this->seed_attachments(
			2,
			array(
				41 => 'Caption A',
				42 => 'Caption B',
			)
		);

		$html = $this->render_gallery(
			array(
				'layout'   => 'grid',
				'captions' => 0,
				'images'   => $ids,
			)
		);

		$this->assertStringNotContainsString( '<figcaption', $html );
	}

	public function test_caption_text_is_escaped(): void {
		$ids = $this->seed_attachments(
			2,
			array( 41 => 'Fish & <chips>' )
		);

		$html = $this->render_gallery(
			array(
				'layout'   => 'grid',
				'captions' => 1,
				'images'   => $ids,
			)
		);

		$this->assertStringContainsString( 'Fish &amp; &lt;chips&gt;', $html );
	}

	// -----------------------------------------------------------------
	// Headings + schema.
	// -----------------------------------------------------------------

	public function test_primary_gallery_never_claims_the_h1(): void {
		$ids = $this->seed_attachments( 2 );

		$html = $this->render_gallery(
			array(
				'layout' => 'grid',
				'images' => $ids,
			),
			true
		);

		$this->assertSame( 0, preg_match_all( '/<h[1-6][\s>]/', $html ), 'No heading field, no headings.' );
		$this->assertFalse( salt_h1_claimed(), 'has_heading false: the page-level fallback H1 must still fire (A11Y01).' );
	}

	public function test_no_schema_nodes_are_queued(): void {
		$ids = $this->seed_attachments( 2 );

		$this->render_gallery(
			array(
				'layout' => 'grid',
				'images' => $ids,
			)
		);

		$this->assertSame( array(), salt_schema_queue() );
	}
}
