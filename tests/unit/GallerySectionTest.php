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

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'gallery' );

		$this->assertSame( 'layout_salt_gallery', $layout['key'] );
		$this->assertSame(
			array( 'heading', 'intro', 'layout', 'columns', 'label', 'captions', 'images', '', 'settings' ),
			array_column( $layout['sub_fields'], 'name' )
		);

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( array( 'grid', 'masonry', 'carousel' ), array_keys( $fields['layout']['choices'] ) );
		$this->assertSame( 'gallery', $fields['images']['type'] );
		$this->assertSame( 'id', $fields['images']['return_format'] );
		$this->assertSame( array( 2, 3, 4 ), array_keys( $fields['columns']['choices'] ) );
		$this->assertSame( 'true_false', $fields['captions']['type'] );
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

		$this->assertStringContainsString( '<div class="salt-block" data-block="gallery"><figure class="salt-gallery__figure">', $html );
		$this->assertSame( 1, substr_count( $html, '<figure' ) );
		$this->assertStringNotContainsString( 'salt-carousel', $html, '1 image: no track (FAIL02).' );
		$this->assertStringNotContainsString( '<button', $html );
		$this->assertStringContainsString( 'sizes="' . salt_image_slot_sizes( 'content', 'default' ) . '"', $html, 'A lone image fills the content box.' );
	}

	public function test_single_image_in_grid_layout_is_also_the_static_figure(): void {
		$this->seed_attachments( 1 );

		$html = $this->render_gallery(
			array(
				'layout' => 'grid',
				'images' => array( 41 ),
			)
		);

		$this->assertStringContainsString( '<figure class="salt-gallery__figure">', $html );
		$this->assertStringNotContainsString( 'salt-gallery__item', $html );
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

		$this->assertStringContainsString( '<ul class="salt-grid salt-gallery" role="list" data-layout="grid" data-columns="3">', $html );
		$this->assertSame( 3, substr_count( $html, '<li class="salt-gallery__item"><figure class="salt-gallery__figure">' ) );
		$this->assertStringNotContainsString( 'salt-carousel', $html );
		$this->assertSame( 3, substr_count( $html, 'sizes="' . salt_image_slot_sizes( 'card', 'default', '3' ) . '"' ), 'A card at three columns.' );
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

		$this->assertStringContainsString( 'data-columns="4"', $html );
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

		$this->assertStringContainsString( 'data-columns="3"', $html );
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

		$this->assertStringContainsString( 'data-layout="masonry" data-columns="3"', $html );
		$this->assertSame( 3, substr_count( $html, 'salt-gallery__item' ) );
		$this->assertStringNotContainsString( 'salt-carousel', $html, 'Masonry has no script: the stylesheet lays it out.' );
		$this->assertStringNotContainsString( '<button', $html );
	}

	public function test_carousel_base_is_a_functional_snap_track_with_no_buttons(): void {
		$ids = $this->seed_attachments( 3 );

		$html = $this->render_gallery(
			array(
				'layout' => 'carousel',
				'label'  => 'Garden photographs',
				'images' => $ids,
			)
		);

		$this->assertStringContainsString( '<div class="salt-carousel" role="region" aria-roledescription="carousel" aria-label="Garden photographs"><ul class="salt-carousel__track" role="list" tabindex="0">', $html );
		$this->assertSame( 3, substr_count( $html, '<li class="salt-carousel__slide">' ) );
		$this->assertStringContainsString( 'aria-label="Slide 2 of 3"', $html );
		$this->assertSame( 3, substr_count( $html, 'sizes="' . salt_image_slot_sizes( 'card' ) . '"' ), 'A slide is an unbanded card.' );
		$this->assertStringNotContainsString( '<button', $html, 'FAIL02: the controls are the script\'s — no dead buttons without it.' );

		// Unnamed (no heading, no label): the grid in place of the carousel (view-props named).
		$unnamed = $this->render_gallery(
			array(
				'layout' => 'carousel',
				'images' => $ids,
			)
		);
		$this->assertStringNotContainsString( 'salt-carousel', $unnamed );
		$this->assertStringContainsString( 'data-layout="grid"', $unnamed );
	}

	public function test_invalid_layout_falls_back_to_grid_and_logs(): void {
		$ids = $this->seed_attachments( 2 );

		$html = $this->render_gallery(
			array(
				'layout' => 'mosaic',
				'images' => $ids,
			)
		);

		$this->assertStringContainsString( 'data-layout="grid"', $html );
		$this->assertStringContainsString( 'invalid layout', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Captions (<figure>/<figcaption> rendered directly).
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
		$this->assertStringContainsString( '<figcaption class="salt-gallery__caption">Sunset over the harbour</figcaption>', $html );
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

		$this->assertSame( 0, preg_match_all( '/<h[1-6][\s>]/', $html ), 'No heading set, no headings.' );
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

	// -----------------------------------------------------------------
	// Logic/view split (ID-3587): resolver and view.
	// -----------------------------------------------------------------

	/**
	 * Seed one gallery FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_gallery( array $fields ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'gallery',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'gallery' );
		$this->assertSame( 'salt_resolve_section_gallery', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_when_no_image_resolves(): void {
		$this->assertNull( $this->resolve_gallery( array( 'images' => array( 0, 999 ) ) ) );
		$this->assertNull( $this->resolve_gallery( array() ) );
		// markup/gallery.json zeroState: a heading-only gallery is drawn.
		$this->assertSame( array(), $this->resolve_gallery( array( 'heading' => 'Recent gardens' ) )['props']['images'] );
	}

	public function test_resolver_props_and_wrapper_inputs(): void {
		$ids = $this->seed_attachments( 2, array( 41 => '  First caption ' ) );

		$resolved = $this->resolve_gallery(
			array(
				'layout'   => 'masonry',
				'columns'  => 4,
				'captions' => true,
				'images'   => array( $ids[0], 0, array( 'ID' => $ids[1] ) ),
				'settings' => array(
					'spacing' => 'lg',
				),
			)
		);

		$this->assertSame( array( 'heading', 'intro', 'layout', 'columns', 'label', 'named', 'captions', 'images', 'labels' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'masonry', $resolved['props']['layout'] );
		$this->assertSame( '4', $resolved['props']['columns'] );
		$this->assertFalse( $resolved['props']['named'] );
		$this->assertTrue( $resolved['props']['captions'] );
		$images = $resolved['props']['images'];
		$this->assertSame( array( 'https://example.test/uploads/gallery-41.jpg', 'https://example.test/uploads/gallery-42.jpg' ), array_column( $images, 'src' ), 'Malformed entry skipped; field order kept.' );
		$this->assertSame( 'First caption', $images[0]['caption'], 'Captions trimmed.' );
		$this->assertNull( $images[1]['caption'] );
		$this->assertContains( 'section gallery: malformed image entry — skipped.', $this->logs() );

		$this->assertSame( 'gallery', $resolved['section']['name'] );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertFalse( $resolved['section']['has_heading'], 'No heading: never claims the H1 (A11Y01).' );
		$this->assertTrue( $resolved['plan']['priorityMedia'], 'The first image asks for the page\'s priority image.' );
	}

	public function test_resolver_falls_back_on_invalid_layout_and_columns(): void {
		$ids = $this->seed_attachments( 2 );

		$resolved = $this->resolve_gallery(
			array(
				'layout'  => 'mosaic',
				'columns' => 7,
				'images'  => $ids,
			)
		);

		$this->assertSame( 'grid', $resolved['props']['layout'] );
		$this->assertSame( '3', $resolved['props']['columns'] );
		$this->assertTrue( $resolved['props']['captions'], 'An unsaved toggle is on (field default).' );
	}

	/**
	 * Draw the gallery view from props alone.
	 *
	 * @param array<string,mixed> $props View props.
	 * @return string
	 */
	private function draw_gallery_view( array $props ): string {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/gallery.php',
			false,
			array(
				'props' => array_merge(
					array(
						'heading'  => null,
						'intro'    => null,
						'label'    => null,
						'named'    => false,
						'captions' => true,
						'labels'   => array( 'slide' => 'Diapo {n} sur {N}' ),
					),
					$props
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'photos',
					'headingLevel'  => 2,
					'headingId'     => 'photos__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => true,
				),
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * A resolved image (salt_image_contract()'s shape).
	 *
	 * @param string      $src     Its address.
	 * @param string|null $caption Its caption.
	 * @return array<string,mixed>
	 */
	private static function image( string $src, ?string $caption = null ): array {
		return array(
			'src'     => $src,
			'srcset'  => null,
			'sizes'   => '100vw',
			'width'   => 800,
			'height'  => 600,
			'alt'     => 'Alt',
			'caption' => $caption,
		);
	}

	public function test_view_draws_from_props_alone(): void {
		$html = $this->draw_gallery_view(
			array(
				'heading' => 'Photos',
				'named'   => true,
				'layout'  => 'carousel',
				'columns' => '3',
				'images'  => array(
					self::image( 'https://u.test/a.jpg', 'From <props>' ),
					self::image( 'https://u.test/b.jpg' ),
				),
			)
		);

		$this->assertStringContainsString( '<h2 id="photos__heading">Photos</h2>', $html );
		$this->assertStringContainsString( 'aria-roledescription="carousel" aria-label="Photos"', $html );
		$this->assertStringContainsString( 'aria-label="Diapo 1 sur 2"', $html, 'The slide word is the labels\'.' );
		$this->assertSame( 1, substr_count( $html, '<figcaption' ), 'No caption: no figcaption.' );
		$this->assertStringContainsString( 'From &lt;props&gt;', $html );
		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ), 'Only the first image is the priority image.' );
		$this->assertStringNotContainsString( 'salt-section', $html, 'The band is the entry\'s, not the view\'s.' );
	}

	public function test_view_draws_one_item_as_a_single_figure_in_any_layout(): void {
		foreach ( array( 'grid', 'masonry', 'carousel' ) as $layout ) {
			$html = $this->draw_gallery_view(
				array(
					'named'   => true,
					'label'   => 'Photos',
					'layout'  => $layout,
					'columns' => '2',
					'images'  => array( self::image( 'https://u.test/a.jpg' ) ),
				)
			);

			$this->assertStringStartsWith( '<div class="salt-block" data-block="gallery"><figure class="salt-gallery__figure">', $html, $layout );
			$this->assertStringNotContainsString( 'salt-carousel', $html, $layout );
			$this->assertStringNotContainsString( '<ul', $html, $layout );
		}
	}
}
